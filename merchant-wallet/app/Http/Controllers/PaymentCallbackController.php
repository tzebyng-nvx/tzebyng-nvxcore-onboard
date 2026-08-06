<?php

namespace App\Http\Controllers;

use App\Services\PaymentCallbackService;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentCallbackService $paymentCallbackService
    ) {}

    public function handle(Request $request)
    {
        $this->paymentCallbackService->handle($request->all());

        return response()->json([
            'status' => true,
        ]);
    }
}
