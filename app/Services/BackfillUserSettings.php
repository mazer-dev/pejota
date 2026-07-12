<?php

namespace App\Services;

use App\Enums\UserSettingsEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BackfillUserSettings
{
    /**
     * Copy each user's personal preference keys from the company they OWN
     * (companies.user_id) into users.settings. Non-destructive: only fills
     * keys that are present on the company and absent on the user; leaves the
     * company copies intact. Idempotent — safe to re-run.
     *
     * @return int number of users updated
     */
    public function __invoke(): int
    {
        $keys = array_map(
            static fn (UserSettingsEnum $case): string => $case->value,
            UserSettingsEnum::cases(),
        );

        $updated = 0;

        DB::table('users')->orderBy('id')->each(function (object $user) use ($keys, &$updated): void {
            $company = DB::table('companies')
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->first();

            if ($company === null) {
                return;
            }

            $companySettings = $this->decode($company->settings);
            $userSettings = $this->decode($user->settings);

            $copiedAny = false;
            foreach ($keys as $key) {
                if (Arr::has($companySettings, $key) && ! Arr::has($userSettings, $key)) {
                    Arr::set($userSettings, $key, Arr::get($companySettings, $key));
                    $copiedAny = true;
                }
            }

            if (! $copiedAny) {
                return;
            }

            DB::table('users')->where('id', $user->id)->update([
                'settings' => json_encode($userSettings),
            ]);

            $updated++;
        });

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(?string $json): array
    {
        $decoded = json_decode($json ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }
}
