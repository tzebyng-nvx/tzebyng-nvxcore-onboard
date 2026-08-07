<?php

use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// This test uses real (file-based) tenant databases via the tenancy
// bootstrappers, which reconnects the default connection. Without ending
// tenancy and removing the created database files afterwards, it corrupts the
// shared in-memory connection used by every RefreshDatabase test that follows.
afterEach(function () {
    tenancy()->end();

    foreach (glob(database_path('tenanttenant-*')) as $file) {
        @unlink($file);
    }
});

test('tenant data is isolated across different tenant databases', function () {
    $tenantAId = 'tenant-a-'.Str::uuid()->toString();
    $tenantBId = 'tenant-b-'.Str::uuid()->toString();

    $tenantA = Tenant::create(['id' => $tenantAId]);
    $tenantB = Tenant::create(['id' => $tenantBId]);

    $tenantA->domains()->create(['domain' => $tenantAId.'.merchant-wallet.test']);
    $tenantB->domains()->create(['domain' => $tenantBId.'.merchant-wallet.test']);

    $tenantA->run(function () {
        Schema::create('isolation_probe', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('isolation_probe')->insert([
            'name' => 'Tenant A wallet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $tenantB->run(function () {
        Schema::create('isolation_probe', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('isolation_probe')->insert([
            'name' => 'Tenant B wallet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $tenantA->run(function () {
        expect(DB::table('isolation_probe')->pluck('name')->all())->toBe(['Tenant A wallet']);
    });

    $tenantB->run(function () {
        expect(DB::table('isolation_probe')->pluck('name')->all())->toBe(['Tenant B wallet']);
    });
});
