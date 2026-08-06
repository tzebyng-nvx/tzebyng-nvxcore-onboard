<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 18, 2)->default(0);
            $table->decimal('held_balance', 18, 2)->default(0); // reserved for pending withdrawals
            $table->string('currency', 3)->default('MYR');
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
        });

        // DB-level guard: balance/held_balance can never go negative,
        // even if application code has a bug.
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT chk_wallet_balance_non_negative CHECK (balance >= 0)');
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT chk_wallet_held_non_negative CHECK (held_balance >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
