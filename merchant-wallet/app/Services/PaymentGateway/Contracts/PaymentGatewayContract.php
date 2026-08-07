<?php

namespace App\Services\PaymentGateway\Contracts;

use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayAuthDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayBalanceDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayBankListDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayCheckStatusDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayCurrencyDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayGeneralInfoDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayGenerateOrdersDto;
use App\Services\PaymentGateway\Dtos\Response\PaymentGatewayWithdrawOrdersDto;

/**
 * Contract for the third-party payment gateway driver. Business code depends on
 * this interface, not the concrete HTTP implementation, so the gateway can be
 * swapped or faked without touching controllers or services.
 */
interface PaymentGatewayContract
{
    public function getGeneralInfo(array $data): PaymentGatewayGeneralInfoDto;

    public function getAuth(): PaymentGatewayAuthDto;

    public function getCurrency(): PaymentGatewayCurrencyDto;

    /**
     * @param  'deposit'|'withdraw'  $type
     */
    public function getBankList(string $type = 'deposit', array $data = []): PaymentGatewayBankListDto;

    public function getBalance(string $currency = 'MYR'): PaymentGatewayBalanceDto;

    public function checkStatus(string $orderId): PaymentGatewayCheckStatusDto;

    public function checkWithdrawStatus(string $orderId): PaymentGatewayCheckStatusDto;

    public function createDeposit(array $data = []): PaymentGatewayGenerateOrdersDto;

    public function createWithdrawal(array $data = []): PaymentGatewayWithdrawOrdersDto;
}
