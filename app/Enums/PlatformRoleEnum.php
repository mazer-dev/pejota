<?php

namespace App\Enums;

enum PlatformRoleEnum: string
{
    case SuperAdmin = 'super-admin';
    case SupportTier1 = 'support-tier-1';

    /**
     * Reserved spatie team id for platform-axis (global) role assignments.
     * NOT null: team_id is part of the pivot's composite PK and MySQL/MariaDB
     * forbid null in a PK column. No company uses id 0 (companies start at 1;
     * spatie's team_id is a bare integer, not an FK to companies).
     */
    public const TeamId = 0;

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
