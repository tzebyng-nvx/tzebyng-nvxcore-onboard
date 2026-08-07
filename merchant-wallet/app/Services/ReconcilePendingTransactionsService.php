<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Enums\TransactionType;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use App\Services\PaymentGateway\Contracts\PaymentGatewayContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePendingTransactionsService
{
    /**
     * Gateway order statuses that are terminal failures.
     */
    private const FAILED_STATUSES = ['failed', 'cancelled', 'expired', 'rejected'];

    public function __construct(
        private PaymentCallbackService $paymentCallbackService,
    ) {}

    /**
     * Resolve payment transactions that are still pending/processing after the
     * given age, by asking the gateway for their current status.
     *
     * @return array{checked:int,resolved:int,skipped:int,failed:int}
     */
    public function reconcile(int $staleAfterMinutes = 5): array
    {
        $stale = PaymentTransaction::query()
            ->whereIn('status', [
                PaymentTransactionStatus::Pending->value,
                PaymentTransactionStatus::Processing->value,
            ])
            ->where('updated_at', '<=', Carbon::now()->subMinutes($staleAfterMinutes))
            ->get();

        $summary = ['checked' => 0, 'resolved' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($stale as $paymentTransaction) {
            $summary['checked']++;

            try {
                $resolved = $this->reconcileOne($paymentTransaction);
                $summary[$resolved ? 'resolved' : 'skipped']++;
            } catch (Throwable $e) {
                $summary['failed']++;
                Log::error('Reconciliation failed', [
                    'order_id' => $paymentTransaction->order_id,
                    'message' => $e->getMessage(),
                ]);
            } finally {
                tenancy()->end();
            }
        }

        Log::info('Reconciliation run complete', $summary);

        return $summary;
    }

    private function reconcileOne(PaymentTransaction $paymentTransaction): bool
    {
        tenancy()->initialize($paymentTransaction->tenant_id);

        $transaction = Transaction::find($paymentTransaction->order_id);

        if (! $transaction) {
            Log::warning('Reconciliation tenant transaction missing', [
                'order_id' => $paymentTransaction->order_id,
            ]);

            return false;
        }

        $gateway = app(PaymentGatewayContract::class);

        $dto = $transaction->type === TransactionType::Withdrawal
            ? $gateway->checkWithdrawStatus($paymentTransaction->order_id)
            : $gateway->checkStatus($paymentTransaction->order_id);

        $orderStatus = strtolower($dto->order_status ?? '');

        if ($orderStatus === 'completed') {
            $this->paymentCallbackService->resolve($paymentTransaction, 'completed');

            return true;
        }

        if (in_array($orderStatus, self::FAILED_STATUSES, true)) {
            $this->paymentCallbackService->resolve($paymentTransaction, 'failed');

            return true;
        }

        Log::info('Reconciliation still pending', [
            'order_id' => $paymentTransaction->order_id,
            'order_status' => $orderStatus,
        ]);

        return false;
    }
}
