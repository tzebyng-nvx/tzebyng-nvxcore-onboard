<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentGatewaySettingsRequest;
use App\Http\Requests\UpdatePaymentGatewaySettingsRequest;
use App\Models\PaymentGatewaySettings;

class PaymentGatewaySettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentGatewaySettingsRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentGatewaySettings $paymentGatewaySettings)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentGatewaySettings $paymentGatewaySettings)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentGatewaySettingsRequest $request, PaymentGatewaySettings $paymentGatewaySettings)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentGatewaySettings $paymentGatewaySettings)
    {
        //
    }
}
