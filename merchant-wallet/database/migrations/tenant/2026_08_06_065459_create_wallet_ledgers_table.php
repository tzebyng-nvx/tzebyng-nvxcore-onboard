<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->string('entry_type'); // deposit_credit | withdrawal_hold | withdrawal_release | withdrawal_debit
            $table->decimal('amount', 18, 2);        // signed: positive = credit, negative = debit
            $table->decimal('balance_after', 18, 2);  // snapshot for audit/debugging
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);

            // This is the actual idempotency guarantee: the DB itself
            // refuses a second row for the same (transaction, entry_type)
            // pair. A replayed callback trying to insert a duplicate
            // 'deposit_credit' fails here, not by relying on app-level
            // "if status === completed" checks alone.
            $table->unique(['transaction_id', 'entry_type'], 'uq_ledger_txn_entry_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_ledgers');
    }
};
