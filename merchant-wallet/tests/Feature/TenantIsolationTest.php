<?php

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('tenant data is isolated across different tenant databases', function () {
    $tenantAId = 'tenant-a-'.Str::uuid()->toString();
    $tenantBId = 'tenant-b-'.Str::uuid()->toString();

    $tenantA = Tenant::create(['id' => $tenantAId]);
    $tenantB = Tenant::create(['id' => $tenantBId]);

    $tenantA->domains()->create(['domain' => $tenantAId.'.merchant-wallet.test']);
    $tenantB->domains()->create(['domain' => $tenantBId.'.merchant-wallet.test']);

    $tenantA->run(function () {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('wallets')->insert([
            'name' => 'Tenant A wallet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $tenantB->run(function () {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('wallets')->insert([
            'name' => 'Tenant B wallet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $tenantA->run(function () {
        expect(DB::table('wallets')->pluck('name')->all())->toBe(['Tenant A wallet']);
    });

    $tenantB->run(function () {
        expect(DB::table('wallets')->pluck('name')->all())->toBe(['Tenant B wallet']);
    });
});
