<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetPaymentBankListRequest;
use App\Http\Requests\PaymentGeneralInfoRequest;
use App\Http\Requests\TransactionCreateDepositRequest;
use App\Http\Requests\TransactionCreateWithdrawalRequest;
use App\Services\PaymentGateway\PaymentGatewayService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(protected PaymentGatewayService $paymentGatewayService, protected TransactionService $transactionService) {}

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
    public function deposit(TransactionCreateDepositRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        // create records
        $transaction = $this->transactionService->createDeposit([
            ...$request->validated(),
            'user_id' => $user->id,
        ]);

        // connect to 3rd party
        $gatewayDepositResult = $this->paymentGatewayService->createDeposit([
            ...$request->validated(),
            'order_id' => $transaction->id,
            'email' => $user->email,
            'phone_number' => '0123445678', // TODO: add this column in all users table, hardcoded now
        ]);

        // update record
        $this->transactionService->updatePaymentId([$transaction->id, $gatewayDepositResult->payment_id]);

        return response()->json($gatewayDepositResult);
    }

    public function withdraw(TransactionCreateWithdrawalRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        // create withdrawal transaction
        $transaction = $this->transactionService->createWithdrawal([
            ...$request->validated(),
            'user_id' => $user->id,
        ]);

        // connect to 3rd party withdrawal gateway
        $gatewayWithdrawalResult = $this->paymentGatewayService->createWithdrawal([
            ...$request->validated(),
            'order_id' => $transaction->id,
            'holder_name' => $request->holder_name,
            'account_no' => $request->account_no,
        ]);

        // update payment id from gateway response
        $this->transactionService->updatePaymentId([
            $transaction->id,
            $gatewayWithdrawalResult->payment_id,
        ]);

        return response()->json($gatewayWithdrawalResult);
    }
}
