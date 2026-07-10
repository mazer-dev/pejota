<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill an owner membership for every company that predates the
     * company_user pivot (linked only via companies.user_id). Idempotent,
     * and a no-op on fresh installs (no companies exist at migrate time —
     * pj:install creates them afterwards).
     */
    public function up(): void
    {
        DB::table('companies')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->each(function (object $company): void {
                $alreadyMember = DB::table('company_user')
                    ->where('company_id', $company->id)
                    ->where('user_id', $company->user_id)
                    ->exists();

                if ($alreadyMember) {
                    return;
                }

                DB::table('company_user')->insert([
                    'company_id' => $company->id,
                    'user_id' => $company->user_id,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Not reversed: backfilled rows are indistinguishable from memberships
        // created afterwards, and dropping them would revoke legitimate access.
    }
};
