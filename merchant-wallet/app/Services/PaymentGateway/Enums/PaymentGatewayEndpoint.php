<?php

namespace App\Services\PaymentGateway\Enums;

enum PaymentGatewayEndpoint: string
{
    case CURRENCY = '/merchant/currency';

    case AUTH = '/merchant/auth';

    case ORDERS_GENERATE = '/merchant/generate_orders';
    case ORDERS_WITHDRAW = '/merchant/withdraw_orders';

    case BANK_LIST_WITHDRAW = '/wallet/withdraw_bank_list';
    case BANK_LIST_DEPOSIT = '/wallet/bank_list';

    case CHECK_STATUS = '/merchant/check_status';
    case CHECK_STATUS_WITHDRAW = '/merchant/check_withdraw_status';

    case BALANCE = 'wallet/get_balance';

    // after making payment, Payment will call "callback_url" in PaymentGatewaySetting
    // callback might need hook, and url expose
    // handle callback response dto later

}
