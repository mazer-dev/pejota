<?php

namespace App\Enums;

enum CompanyRoleEnum: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
