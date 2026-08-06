<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case OnlineBanking = 'online_banking';
    case DuitNowQr = 'duitnowqr';
}
