<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetPaymentDepositBankListRequest;
use App\Models\Payment;
use App\Services\PaymentGateway\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(protected PaymentGatewayService $gateway) {}

    public function getGeneralInfo(): JsonResponse
    {
        $generalInfo = $this->gateway->getGeneralInfo();

        return response()->json($generalInfo);
    }

    public function getCurrency(): JsonResponse
    {
        $currency = $this->gateway->getCurrency();

        return response()->json($currency);
    }

    public function getDepositBankList(GetPaymentDepositBankListRequest $request): JsonResponse
    {
        $bankList = $this->gateway->getDepositBankList($request->validated());

        return response()->json($bankList);
    }

    /**
     * GET /payments
     * List records, paginated.
     */

    // public function index(Request $request): JsonResponse
    // {
    //     $perPage = min((int) $request->integer('per_page', 15), 100);

    //     $payments = Payment::query()
    //         ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status'))
    //         )
    //         ->latest()
    //         ->paginate($perPage);

    //     return response()->json($payments);
    // }

    /**
     * POST /payments
     * Create a new record.
     */
    // public function store(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'amount' => ['required', 'numeric', 'min:0.01'],
    //         'currency' => ['required', 'string', 'size:3'],
    //         'status' => ['sometimes', Rule::in(['pending', 'success', 'failed'])],
    //     ]);

    //     $payment = Payment::create($validated);

    //     return response()->json($payment, 201);
    // }

    /**
     * GET /payments/{payment}
     * Show a single record.
     */
    // public function show(Payment $payment): JsonResponse
    // {
    //     return response()->json($payment);
    // }

    /**
     * PUT/PATCH /payments/{payment}
     * Update an existing record.
     */
    // public function update(Request $request, Payment $payment): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'amount' => ['sometimes', 'numeric', 'min:0.01'],
    //         'currency' => ['sometimes', 'string', 'size:3'],
    //         'status' => ['sometimes', Rule::in(['pending', 'success', 'failed'])],
    //     ]);

    //     $payment->update($validated);

    //     return response()->json($payment);
    // }

    /**
     * DELETE /payments/{payment}
     * Delete a record.
     */
    // public function destroy(Payment $payment): JsonResponse
    // {
    //     $payment->delete();

    //     return response()->json(null, 204);
    // }
}
