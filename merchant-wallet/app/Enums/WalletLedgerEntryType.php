<?php

namespace App\Enums;

enum WalletLedgerEntryType: string
{
    case DepositCredit = 'deposit_credit';
    case WithdrawalHold = 'withdrawal_hold';
    case WithdrawalDebit = 'withdrawal_debit';
    case WithdrawalRelease = 'withdrawal_release';
}
