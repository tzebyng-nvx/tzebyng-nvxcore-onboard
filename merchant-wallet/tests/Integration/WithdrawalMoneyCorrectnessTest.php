<?php

use App\Enums\TransactionStatus;
use App\Enums\WalletLedgerEntryType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Services\PaymentCallbackService;
use App\Services\TransactionService;
use App\Services\WalletService;
use Illuminate\Support\Str;

/**
 * Money-correctness invariants for the withdrawal lifecycle: funds are held at
 * request time, the callback finalizes or releases the hold, no balance moves
 * without a matching ledger entry, and the wallet can never be overdrawn.
 */
beforeEach(function () {
    $tenant = createTenantWithSchema('wd-test');
    tenancy()->initialize($tenant);

    $this->tenant = $tenant;
    $this->user = User::create([
        'name' => 'Wallet Owner',
        'email' => 'owner@wd-test.com',
        'phone_number' => '0123456789',
        'password' => 'secret123',
    ]);

    $this->wallet = Wallet::create([
        'user_id' => $this->user->id,
        'currency' => 'MYR',
    ]);

    // Fund the wallet with 100.00 spendable via a ledger-backed credit, so the
    // ledger fully reconstructs the wallet state (no untracked opening balance).
    app(WalletService::class)->creditDeposit(
        $this->wallet,
        (string) Str::uuid(),
        '100.00'
    );
});

afterEach(function () {
    tenancy()->end();
});

/**
 * Reconstruct balance and held_balance purely from ledger rows and assert they
 * match the wallet columns — i.e. balance never drifts from the ledger.
 */
function assertWalletMatchesLedger(Wallet $wallet): void
{
    $entries = WalletLedger::where('wallet_id', $wallet->id)->get();

    $sum = fn (WalletLedgerEntryType $type) => (string) $entries
        ->where('entry_type', $type->value)
        ->reduce(fn ($carry, $e) => bcadd($carry, $e->amount, 2), '0.00');

    $deposits = $sum(WalletLedgerEntryType::DepositCredit);
    $holds = $sum(WalletLedgerEntryType::WithdrawalHold);
    $debits = $sum(WalletLedgerEntryType::WithdrawalDebit);
    $releases = $sum(WalletLedgerEntryType::WithdrawalRelease);

    $expectedBalance = bcsub($deposits, $debits, 2);
    $expectedHeld = bcsub(bcsub($holds, $debits, 2), $releases, 2);

    $fresh = $wallet->fresh();
    expect($fresh->balance)->toBe($expectedBalance)
        ->and($fresh->held_balance)->toBe($expectedHeld);
}

test('creating a withdrawal holds the funds and records a hold ledger entry', function () {
    $transaction = app(TransactionService::class)->createWithdrawal([
        'user_id' => $this->user->id,
        'amount' => '40.00',
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
    ]);

    $fresh = $this->wallet->fresh();
    expect($fresh->balance)->toBe('100.00')       // real balance untouched
        ->and($fresh->held_balance)->toBe('40.00'); // reserved

    $entry = WalletLedger::where('transaction_id', $transaction->id)->sole();
    expect($entry->entry_type)->toBe(WalletLedgerEntryType::WithdrawalHold->value)
        ->and($entry->amount)->toBe('40.00');

    assertWalletMatchesLedger($this->wallet);
});

test('a withdrawal above the available balance is rejected and holds nothing', function () {
    expect(fn () => app(TransactionService::class)->createWithdrawal([
        'user_id' => $this->user->id,
        'amount' => '150.00',
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
    ]))->toThrow(InsufficientBalanceException::class);

    $fresh = $this->wallet->fresh();
    expect($fresh->balance)->toBe('100.00')
        ->and($fresh->held_balance)->toBe('0.00');

    // The whole transaction rolled back: no transaction row and no hold entry
    // (only the opening deposit-credit ledger row from funding remains).
    expect(Transaction::count())->toBe(0)
        ->and(WalletLedger::where('entry_type', WalletLedgerEntryType::WithdrawalHold->value)->count())->toBe(0);
});

test('two full-balance withdrawals cannot both succeed (double-spend prevention)', function () {
    $service = app(TransactionService::class);

    // First withdrawal reserves the entire balance.
    $service->createWithdrawal([
        'user_id' => $this->user->id,
        'amount' => '100.00',
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
    ]);

    // Second withdrawal for the same amount now has zero available balance.
    expect(fn () => $service->createWithdrawal([
        'user_id' => $this->user->id,
        'amount' => '100.00',
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
    ]))->toThrow(InsufficientBalanceException::class);

    $fresh = $this->wallet->fresh();
    expect($fresh->held_balance)->toBe('100.00') // only one hold survived
        ->and(WalletLedger::where('entry_type', WalletLedgerEntryType::WithdrawalHold->value)->count())->toBe(1);

    assertWalletMatchesLedger($this->wallet);
});

test('a completed withdrawal callback debits balance and clears the hold', function () {
    $transaction = app(TransactionService::class)->createWithdrawal([
        'user_id' => $this->user->id,
        'amount' => '40.00',
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
    ]);

    $paymentTransaction = PaymentTransaction::where('order_id', $transaction->id)->sole();

    app(PaymentCallbackService::class)->resolve($paymentTransaction, 'completed');

    $fresh = $this->wallet->fresh();
    expect($fresh->balance)->toBe('60.00')          // funds actually left
        ->and($fresh->held_balance)->toBe('0.00')   // hold cleared
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Success);

    assertWalletMatchesLedger($this->wallet);
});

test('a failed withdrawal callback releases the hold back to spendable balance', function () {
    $transaction = app(TransactionService::class)->createWithdrawal([
        'user_id' => $this->user->id,
        'amount' => '40.00',
        'currency' => 'MYR',
        'bank_id' => 'BANK1',
    ]);

    $paymentTransaction = PaymentTransaction::where('order_id', $transaction->id)->sole();

    app(PaymentCallbackService::class)->resolve($paymentTransaction, 'failed');

    $fresh = $this->wallet->fresh();
    expect($fresh->balance)->toBe('100.00')         // nothing left the wallet
        ->and($fresh->held_balance)->toBe('0.00')   // hold returned
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Failed);

    assertWalletMatchesLedger($this->wallet);
});
