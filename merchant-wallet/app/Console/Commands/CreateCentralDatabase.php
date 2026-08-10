<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class CreateCentralDatabase extends Command
{
    protected $signature = 'db:create-central {--force : Recreate the database if it already exists}';

    protected $description = 'Create the central PostgreSQL database (from the default connection) if it does not exist.';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.$connection");

        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->error("This command only supports the pgsql driver (connection [$connection] is [{$config['driver']}]).");

            return self::FAILURE;
        }

        $database = $config['database'];

        // Connect to the "postgres" maintenance database so we can issue
        // CREATE/DROP DATABASE for the target database from a separate session.
        Config::set("database.connections.$connection.database", 'postgres');
        DB::purge($connection);

        $exists = (bool) DB::connection($connection)
            ->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$database]);

        if ($exists && $this->option('force')) {
            DB::connection($connection)->statement("DROP DATABASE \"$database\"");
            $this->warn("Dropped existing database [$database].");
            $exists = false;
        }

        if ($exists) {
            $this->info("Database [$database] already exists; nothing to do.");
        } else {
            DB::connection($connection)->statement("CREATE DATABASE \"$database\"");
            $this->info("Database [$database] created.");
        }

        // Restore the connection to point back at the real central database.
        Config::set("database.connections.$connection.database", $database);
        DB::purge($connection);

        return self::SUCCESS;
    }
}
