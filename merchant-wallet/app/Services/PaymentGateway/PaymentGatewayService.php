<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGatewaySetting;
use App\Services\PaymentGateway\Dtos\Request\PaymentGatewayAuthRequestDto;
use App\Services\PaymentGateway\Dtos\Request\PaymentGatewayBankListRequestDto;
use App\Services\PaymentGateway\Dtos\Request\PaymentGatewayCurrencyRequestDto;
use App\Services\PaymentGateway\Dtos\Request\PaymentGatewayGenerateOrdersRequestDto;
use App\Services\PaymentGateway\Dtos\Request\PaymentGatewayWithdrawOrdersRequestDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayAuthDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayBankListDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayCurrencyDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayGeneralInfoDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayGenerateOrdersDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayWithdrawOrdersDto;
use App\Services\PaymentGateway\Enums\PaymentGatewayEndpoint;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function getGeneralInfo(array $data): PaymentGatewayGeneralInfoDto
    {
        $currencyDto = $this->getCurrency();

        $bankDto = null;
        if (isset($data['withdraw']) && $data['withdraw']) {
            $bankDto = $this->getBankList('withdraw');
        }

        if (isset($data['deposit']) && $data['deposit']) {
            $bankDto = $this->getBankList('deposit');
        }

        return new PaymentGatewayGeneralInfoDto(
            currencies: $currencyDto->rate,
            banks: $bankDto?->data ?? [],
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

        if ($response['status'] === false) {
            throw new Exception(
                'Payment gateway authentication failed: '.$response['message']
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

    /**
     * @param  'deposit'|'withdraw'  $type
     */
    public function getBankList(string $type = 'deposit', array $data = []): PaymentGatewayBankListDto
    {

        $settings = $this->settings;
        $requestDto = new PaymentGatewayBankListRequestDto(
            username: $settings->merchant_username,
            currency: $data['currency'] ?? null
        );
        $endpoint = match ($type) {
            'deposit' => PaymentGatewayEndpoint::BANK_LIST_DEPOSIT->value,
            'withdraw' => PaymentGatewayEndpoint::BANK_LIST_WITHDRAW->value,
            default => throw new Exception('Invalid bank list type: '.$type),
        };

        $response = $this->httpClient()
            ->post(
                $settings->base_url.$endpoint,
                $requestDto->toArray()
            );

        if ($response->failed()) {
            throw new Exception(
                'Payment gateway bank list failed: '.$response->body()
            );
        }

        return PaymentGatewayBankListDto::fromApi($response->json());

    }

    public function createDeposit(array $data = []): PaymentGatewayGenerateOrdersDto
    {
        Log::info('Payment callback url', [
            'url' => config('app.payment_callback_url'),
        ]);

        $auth = $this->getAuth();
        $payload = new PaymentGatewayGenerateOrdersRequestDto(
            username: $this->settings->merchant_username,
            auth: $auth->auth,
            amount: $data['amount'],
            currency: $data['currency'],
            order_id: $data['order_id'],
            email: $data['email'],
            phone_number: $data['phone_number'],
            redirect_url: route('tenant.dashboard'),
            payment_method: $data['payment_method'],
            bank_id: $data['bank_id'],
            callback_url: config('app.payment_callback_url'),
        );

        $response = $this->httpClient()
            ->post(
                $this->settings->base_url.PaymentGatewayEndpoint::ORDERS_GENERATE->value,
                $payload->toArray()
            )
            ->json();

        return PaymentGatewayGenerateOrdersDto::fromApi($response);
    }

    public function createWithdrawal(array $data = []): PaymentGatewayWithdrawOrdersDto
    {
        Log::info('Payment withdrawal callback url', [
            'url' => config('app.payment_callback_url'),
        ]);

        $auth = $this->getAuth();

        $payload = new PaymentGatewayWithdrawOrdersRequestDto(
            auth: $auth->auth,
            amount: $data['amount'],
            currency: $data['currency'],
            order_id: $data['order_id'],
            bank_id: $data['bank_id'],
            holder_name: 'John Doe', // $data['holder_name']
            account_no: '12332343432', // $data['account_no'],
            callback_url: config('app.payment_callback_url'),
        );

        $response = $this->httpClient()
            ->post(
                $this->settings->base_url.PaymentGatewayEndpoint::ORDERS_WITHDRAW->value,
                $payload->toArray()
            )
            ->json();
        $response = PaymentGatewayWithdrawOrdersDto::fromApi($response);
        Log::error(' withdrawal', [
            'message' => $response->message,
        ]);

        return $response;
    }
}
