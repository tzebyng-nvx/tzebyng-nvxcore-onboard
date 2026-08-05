<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGatewaySetting;
use App\Services\PaymentGateway\Dtos\Request\PaymentGatewayAuthRequestDto;
use App\Services\PaymentGateway\Dtos\Request\PaymentGatewayBankListRequestDto;
use App\Services\PaymentGateway\Dtos\Request\PaymentGatewayCurrencyRequestDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayAuthDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayBankListDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayCurrencyDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayGeneralInfoDto;
use App\Services\PaymentGateway\Enums\PaymentGatewayEndpoint;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PaymentGatewayService
{
    private string $baseUrl;

    private PaymentGatewaySetting $settings;

    public function __construct()
    {
        $this->baseUrl = config('services.third_party_api.payment.base_url');

        $this->settings = PaymentGatewaySetting::firstOrFail();

    }

    private function httpClient(): PendingRequest
    {
        return Http::timeout(400)
            ->asMultipart()
            ->acceptJson();
    }

    public function getGeneralInfo(): PaymentGatewayGeneralInfoDto
    {
        $currencyDto = $this->getCurrency();
        $bankDto = $this->getDepositBankList();

        return new PaymentGatewayGeneralInfoDto(
            currencies: $currencyDto->rate,
            banks: $bankDto->data,
        );
    }

    public function getAuth(): PaymentGatewayAuthDto
    {

        $settings = $this->settings;
        $requestDto = new PaymentGatewayAuthRequestDto(
            username: $settings->merchant_username,
            api_key: $settings->api_key,
        );

        $response = $this->httpClient()
            ->post(
                $settings->base_url.PaymentGatewayEndpoint::AUTH->value,
                $requestDto->toArray()
            );

        if ($response->failed()) {
            throw new Exception(
                'Payment gateway authentication failed: '.$response->body()
            );
        }

        return PaymentGatewayAuthDto::fromApi($response->json());
    }

    public function getCurrency(): PaymentGatewayCurrencyDto
    {
        $settings = $this->settings;
        $requestDto = new PaymentGatewayCurrencyRequestDto(
            username: $settings->merchant_username,
        );

        $response = $this->httpClient()
            ->post(
                $settings->base_url.PaymentGatewayEndpoint::CURRENCY->value,
                $requestDto->toArray()
            );

        if ($response->failed()) {
            throw new Exception(
                'Payment gateway currency info failed: '.$response->body()
            );
        }

        return PaymentGatewayCurrencyDto::fromApi($response->json());
    }

    public function getDepositBankList(array $data = []): PaymentGatewayBankListDto
    {
        $settings = $this->settings;
        $requestDto = new PaymentGatewayBankListRequestDto(
            username: $settings->merchant_username,
            currency: $data['currency'] ?? null
        );

        $response = $this->httpClient()
            ->post(
                $settings->base_url.PaymentGatewayEndpoint::BANK_LIST_DEPOSIT->value,
                $requestDto->toArray()
            );

        if ($response->failed()) {
            throw new Exception(
                'Payment gateway deposit bank list failed: '.$response->body()
            );
        }

        return PaymentGatewayBankListDto::fromApi($response->json());
    }
}
