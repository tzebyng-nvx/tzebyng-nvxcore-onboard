<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentGatewaySettingsRequest;
use App\Http\Requests\UpdatePaymentGatewaySettingsRequest;
use App\Services\PaymentGatewaySettingsService;
use Exception;

class PaymentGatewaySettingsController extends Controller
{
    protected PaymentGatewaySettingsService $paymentGatewaySettingsService;

    public function __construct(PaymentGatewaySettingsService $paymentGatewaySettingsService)
    {
        $this->paymentGatewaySettingsService = $paymentGatewaySettingsService;
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
