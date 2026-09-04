<?php

namespace App\Services;

use App\Enums\UserSettingsEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class NormalizeTaskListColumnSettings
{
    /**
     * Column option keys that were renamed in the task table after users had
     * already stored them in `tasks.default_list_columns`.
     *
     * A stored key that matches no current option fails the `in` validation
     * rule Filament derives from the CheckboxList options, which used to block
     * every save of the preferences page.
     *
     * @var array<string, string>
     */
    private const RENAMED_KEYS = [
        'client.labelName' => 'client',
    ];

    /**
     * Column option keys that only became controllable after users had stored
     * their preference, and whose column was unconditionally visible until
     * then. Adding them keeps those users seeing what they saw before.
     *
     * @var array<int, string>
     */
    private const NEWLY_CONTROLLABLE_KEYS = [
        'done_today',
    ];

    /**
     * Rewrite the renamed keys in every user's stored task list columns and
     * append the newly controllable ones, preserving position and dropping the
     * duplicate a rename may create. Users with no stored preference are left
     * alone. Idempotent — safe to re-run.
     *
     * @return int number of users updated
     */
    public function __invoke(): int
    {
        $key = UserSettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value;

        $updated = 0;

        DB::table('users')->orderBy('id')->each(function (object $user) use ($key, &$updated): void {
            $settings = $this->decode($user->settings);

            $columns = Arr::get($settings, $key);

            if (! is_array($columns)) {
                return;
            }

            $normalized = array_values(array_unique(array_map(
                static fn ($column) => is_string($column) ? (self::RENAMED_KEYS[$column] ?? $column) : $column,
                $columns,
            )));

            foreach (self::NEWLY_CONTROLLABLE_KEYS as $controllableKey) {
                if (! in_array($controllableKey, $normalized, true)) {
                    $normalized[] = $controllableKey;
                }
            }

            if ($normalized === array_values($columns)) {
                return;
            }

            Arr::set($settings, $key, $normalized);

            DB::table('users')->where('id', $user->id)->update([
                'settings' => json_encode($settings),
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
