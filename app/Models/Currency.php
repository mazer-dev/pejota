<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Select options for currency pickers: active currencies (ordered by code),
     * ensuring $ensure is present even if inactive/unknown.
     *
     * @return array<string, string>
     */
    public static function selectOptions(?string $ensure = null): array
    {
        $options = self::active()
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Currency $currency): array => [
                $currency->code => $currency->code.' — '.__($currency->name),
            ])
            ->all();

        if ($ensure && ! array_key_exists($ensure, $options)) {
            $options[$ensure] = $ensure;
        }

        return $options;
    }
}
