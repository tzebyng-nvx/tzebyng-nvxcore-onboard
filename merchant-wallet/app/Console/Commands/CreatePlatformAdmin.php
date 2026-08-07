<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin
        {--name=Platform Admin}
        {--email=}
        {--phone=0000000000}
        {--password=password}';

    protected $description = 'Create a platform (central) administrator account.';

    public function handle(): int
    {
        $name = $this->option('name');
        $email = $this->option('email') ?: 'admin@platform.example.com';
        $phone = $this->option('phone');
        $password = $this->option('password');

        $admin = PlatformAdmin::query()->firstOrCreate([
            'email' => $email,
        ], [
            'name' => $name,
            'phone_number' => $phone,
            'password' => Hash::make($password),
        ]);

        if ($admin->wasRecentlyCreated) {
            $this->info("Platform admin [$email] created.");
        } else {
            $this->warn("Platform admin [$email] already exists; no changes made.");
        }

        $this->table(
            ['Name', 'Email', 'Phone'],
            [[$admin->name, $admin->email, $admin->phone_number]]
        );

        return self::SUCCESS;
    }
}
