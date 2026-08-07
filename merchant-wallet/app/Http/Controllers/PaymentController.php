<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Exceptions\InsufficientBalanceException;
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
            'phone_number' => $user->phone_number,
        ]);

        // gateway rejected the order: mark failed, do not fake success
        if (! $gatewayDepositResult->status) {
            $this->transactionService->updateStatus($transaction, TransactionStatus::Failed);

            return response()->json([
                'status' => false,
                'message' => $gatewayDepositResult->message ?? 'Deposit could not be initiated.',
            ], 422);
        }

        // update record
        $this->transactionService->updatePaymentId([$transaction->id, $gatewayDepositResult->payment_id]);

        return response()->json($gatewayDepositResult);
    }

    public function withdraw(TransactionCreateWithdrawalRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        // create withdrawal transaction + reserve funds (hold). Rejects with
        // 422 if the available balance cannot cover the amount.
        try {
            $transaction = $this->transactionService->createWithdrawal([
                ...$request->validated(),
                'user_id' => $user->id,
            ]);
        } catch (InsufficientBalanceException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // connect to 3rd party withdrawal gateway
        $gatewayWithdrawalResult = $this->paymentGatewayService->createWithdrawal([
            ...$request->validated(),
            'order_id' => $transaction->id,
            'holder_name' => $request->holder_name,
            'account_no' => $request->account_no,
        ]);

        // gateway rejected the order: release the hold, mark failed, don't fake success
        if (! $gatewayWithdrawalResult->status) {
            $this->transactionService->failWithdrawal($transaction);

            return response()->json([
                'status' => false,
                'message' => $gatewayWithdrawalResult->message ?? 'Withdrawal could not be initiated.',
            ], 422);
        }

        // update payment id from gateway response
        $this->transactionService->updatePaymentId([
            $transaction->id,
            $gatewayWithdrawalResult->payment_id,
        ]);

        return response()->json($gatewayWithdrawalResult);
    }
}
