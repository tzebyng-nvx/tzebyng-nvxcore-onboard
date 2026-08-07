<?php

namespace App\Jobs;

use App\Services\PaymentCallbackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentCallbackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(protected array $payload)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentCallbackService $paymentCallbackService): void
    {
        $paymentCallbackService->handle($this->payload);
    }
}
