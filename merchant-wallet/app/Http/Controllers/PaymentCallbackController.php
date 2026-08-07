<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentCallbackJob;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function __construct(
    ) {}

    public function handle(Request $request)
    {
        ProcessPaymentCallbackJob::dispatch(
            $request->all()
        );

        return response()->json([
            'status' => true,
        ]);
    }
}
