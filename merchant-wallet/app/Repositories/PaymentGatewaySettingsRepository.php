<?php

namespace App\Repositories;

use App\Models\PaymentGatewaySetting;

class PaymentGatewaySettingsRepository
{
    public function get()
    {
        return PaymentGatewaySetting::query()
            ->first();
    }

    public function updateOrCreate(array $data)
    {
        $setting = PaymentGatewaySetting::first();

        if ($setting) {
            $setting->update($data);

            return $setting;
        }

        return PaymentGatewaySetting::create($data);
    }
}
