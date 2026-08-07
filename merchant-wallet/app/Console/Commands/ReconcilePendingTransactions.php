<?php

namespace App\Console\Commands;

use App\Services\ReconcilePendingTransactionsService;
use Illuminate\Console\Command;

class ReconcilePendingTransactions extends Command
{
    protected $signature = 'payments:reconcile {--stale-after=5 : Minutes a transaction must be pending before reconciling}';

    protected $description = 'Resolve stale pending payment transactions via the gateway status-check endpoints';

    public function handle(ReconcilePendingTransactionsService $service): int
    {
        $summary = $service->reconcile((int) $this->option('stale-after'));

        $this->table(
            ['Checked', 'Resolved', 'Skipped', 'Failed'],
            [[$summary['checked'], $summary['resolved'], $summary['skipped'], $summary['failed']]]
        );

        return self::SUCCESS;
    }
}
