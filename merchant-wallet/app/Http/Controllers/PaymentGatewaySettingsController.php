<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePaymentGatewaySettingsRequest;
use App\Services\PaymentGateway\Contracts\PaymentGatewayContract;
use App\Services\PaymentGatewaySettingsService;
use Exception;
use Illuminate\Http\JsonResponse;
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
    public function floatBalance(Request $request, PaymentGatewayContract $paymentGatewayService)
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

    /**
     * Sync (fetch) the current deposit + withdraw bank lists from the gateway.
     */
    public function syncBankList(Request $request, PaymentGatewayContract $paymentGatewayService): JsonResponse
    {
        try {
            $currency = (string) $request->query('currency', 'MYR');

            return response()->json([
                'deposit' => $paymentGatewayService->getBankList('deposit', ['currency' => $currency])->data,
                'withdraw' => $paymentGatewayService->getBankList('withdraw', ['currency' => $currency])->data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to sync bank list',
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
