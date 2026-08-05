<?php

namespace App\Services;

use App\Models\PaymentGatewaySetting;
use App\Repositories\PaymentGatewaySettingsRepository;

class PaymentGatewaySettingsService
{
    public function __construct(
        protected PaymentGatewaySettingsRepository $repository
    ) {}

    public function createSettings(array $data)
    {
        return PaymentGatewaySetting::create($data);
    }

    public function getSettings()
    {
        return $this->repository->get();
    }

    public function saveSettings(array $data)
    {
        return $this->repository->updateOrCreate($data);
    }
}
