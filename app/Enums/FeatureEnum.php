<?php

namespace App\Enums;

enum FeatureEnum: string
{
    case Contracts = 'contracts';
    case Products = 'products';
    case Accounts = 'accounts';
    case DomainSubscriptions = 'domain_subscriptions';
    case Vendors = 'vendors';
    case MultiCurrency = 'multi_currency';
    case RecurringTasks = 'recurring_tasks';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
