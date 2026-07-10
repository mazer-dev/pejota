<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Manual repair tool for backfilling owner memberships. The automatic,
 * canonical backfill runs on deploy via the
 * 2026_07_09_201011_backfill_company_memberships migration; keep the two
 * in sync if the owner/joined_at semantics ever change. Use this command
 * only to re-run the backfill manually (e.g. after a data fix).
 */
class MigrateMemberships extends Command
{
    protected $signature = 'pj:migrate-memberships';

    protected $description = 'Manually re-run the owner-membership backfill (automatic path is the deploy migration)';

    public function handle(): int
    {
        $created = 0;

        Company::query()
            ->whereNotNull('user_id')
            ->each(function (Company $company) use (&$created): void {
                if ($company->users()->whereKey($company->user_id)->exists()) {
                    return;
                }

                $company->users()->attach($company->user_id, [
                    'joined_at' => now(),
                ]);

                $created++;
            });

        $this->info("Memberships backfilled: {$created}");

        return self::SUCCESS;
    }
}
