<?php

namespace App\Filament\App\Concerns;

use App\Enums\QuotaEnum;
use App\Support\Entitlements;
use Filament\Notifications\Notification;

trait EnforcesCreateQuota
{
    abstract protected function quotaKey(): QuotaEnum;

    abstract protected function currentQuotaCount(): int;

    protected function beforeCreate(): void
    {
        if (Entitlements::withinQuota($this->quotaKey(), $this->currentQuotaCount())) {
            return;
        }

        Notification::make()
            ->warning()
            ->title(__('Plan limit reached'))
            ->body(__('You have reached your current plan limit. Upgrade to add more.'))
            ->persistent()
            ->send();

        $this->halt();
    }
}
