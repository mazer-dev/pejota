<?php

namespace App\Enums;

enum BankAccountType: string
{
    case CHECKING = 'checking';
    case SAVINGS = 'savings';
    case INVESTMENT = 'investment';
    case CREDIT_CARD = 'credit_card';
    case LOAN = 'loan';
    case CASH = 'cash';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::CHECKING => __('Checking Account'),
            self::SAVINGS => __('Savings Account'),
            self::INVESTMENT => __('Investment'),
            self::CREDIT_CARD => __('Credit Card'),
            self::LOAN => __('Loan'),
            self::CASH => __('Cash'),
            self::OTHER => __('Other'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}