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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');

            $table->string('type');

            $table->decimal('amount', 18, 2);

            $table->string('currency', 10);

            $table->string('status')
                ->default('pending');

            // Central payment_transactions reference
            $table->uuid('payment_transaction_id');
            $table->string('payment_method')->nullable();
            $table->string('bank_id');

            // payment gateway resp
            $table->string('payment_id')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('type');
            $table->index('payment_transaction_id');
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
