<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetPaymentBankListRequest;
use App\Http\Requests\PaymentGeneralInfoRequest;
use App\Http\Requests\TransactionCreateDepositRequest;
use App\Http\Requests\TransactionCreateWithdrawalRequest;
use App\Services\PaymentGateway\Contracts\PaymentGatewayContract;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(protected PaymentGatewayContract $paymentGatewayService, protected PaymentService $paymentService) {}

    // INFO RETRIEVAL

    public function getGeneralInfo(PaymentGeneralInfoRequest $request): JsonResponse
    {
        $generalInfo = $this->paymentGatewayService->getGeneralInfo($request->validated());

        return response()->json($generalInfo);
    }

    public function getCurrency(): JsonResponse
    {
        $currency = $this->paymentGatewayService->getCurrency();

        return response()->json($currency);
    }

    public function getDepositBankList(GetPaymentBankListRequest $request): JsonResponse
    {
        $bankList = $this->paymentGatewayService->getBankList('deposit', $request->validated());

        return response()->json($bankList);
    }

    public function getWithdrawBankList(GetPaymentBankListRequest $request): JsonResponse
    {
        $bankList = $this->paymentGatewayService->getBankList('withdraw', $request->validated());

        return response()->json($bankList);
    }

    // ACTIONS

    /**
     * Insufficient-balance and gateway-rejection failures are thrown by the
     * service and rendered as 422 in bootstrap/app.php.
     */
    public function deposit(TransactionCreateDepositRequest $request): JsonResponse
    {
        $result = $this->paymentService->processDeposit(
            auth('api')->user(),
            $request->validated(),
        );

        return response()->json($result);
    }

    public function withdraw(TransactionCreateWithdrawalRequest $request): JsonResponse
    {
        $result = $this->paymentService->processWithdrawal(
            auth('api')->user(),
            $request->validated(),
        );

        return response()->json($result);
    }
}
