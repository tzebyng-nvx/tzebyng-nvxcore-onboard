<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentGatewaySettingsRequest;
use App\Http\Requests\UpdatePaymentGatewaySettingsRequest;
use App\Services\PaymentGateway\PaymentGatewayService;
use App\Services\PaymentGatewaySettingsService;
use Exception;
use Illuminate\Http\Request;

class PaymentGatewaySettingsController extends Controller
{
    protected PaymentGatewaySettingsService $paymentGatewaySettingsService;

    public function __construct(PaymentGatewaySettingsService $paymentGatewaySettingsService)
    {
        $this->paymentGatewaySettingsService = $paymentGatewaySettingsService;
    }

    /**
     * Current merchant float balance held at the payment gateway.
     */
    public function floatBalance(Request $request, PaymentGatewayService $paymentGatewayService)
    {
        try {
            $currency = (string) $request->query('currency', 'MYR');

            return response()->json(
                $paymentGatewayService->getBalance($currency)
            );
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve gateway float balance',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show()
    {
        try {
            return response()->json(
                $this->paymentGatewaySettingsService->getSettings()
            );
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve payment gateway settings',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // public function store(StorePaymentGatewaySettingsRequest $request)
    // {
    //     try {
    //         $setting = $this->paymentGatewaySettingsService->createSettings($request->validated());

    //         return response()->json($setting, 201);
    //     } catch (Exception $e) {
    //         return response()->json(['error' => 'Failed to store payment gateway settings', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function update(UpdatePaymentGatewaySettingsRequest $request)
    {
        try {
            $setting = $this->paymentGatewaySettingsService
                ->saveSettings($request->validated());

            return response()->json($setting);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to update payment gateway settings',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
