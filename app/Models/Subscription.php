<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\SubscriptionStatusEnum;
use App\Helpers\PejotaHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;
use Spatie\Tags\HasTags;

class Subscription extends Model
{
    use BelongsToTenants, HasFactory, HasTags;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'trial_ends_at' => 'date',
            'canceled_at' => 'date',
            'price' => MoneyCast::class,
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * O estado da assinatura, derivado das datas — não há coluna `status`.
     *
     * Guardar o rótulo ao lado das datas produzia duas verdades sem nada
     * sincronizando: preencher `canceled_at` deixando o rótulo em "ativa" era
     * construível pela própria tela, e o resultado eram duas superfícies discordando
     * sobre o mesmo fato. Derivar torna a divergência inconstruível.
     *
     * QUALQUER `canceled_at` conta, inclusive data futura. Não é descuido: é o que a
     * geração de contas a pagar do cloud já faz, e uma segunda leitura divergente
     * recriaria aqui o problema que esta derivação existe para acabar.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (): SubscriptionStatusEnum => match (true) {
                $this->canceled_at !== null => SubscriptionStatusEnum::CANCELED,
                $this->trial_ends_at !== null
                    && $this->trial_ends_at->toDateString() >= self::currentDay()->toDateString() => SubscriptionStatusEnum::TRIAL,
                default => SubscriptionStatusEnum::ACTIVE,
            },
        );
    }

    /**
     * O braço SQL do accessor acima. Os dois precisam classificar o mesmo conjunto, e
     * `SubscriptionStateTest` é o que prende esse acordo — um filtro de lista precisa de
     * `WHERE`, e `WHERE` não enxerga accessor.
     */
    public function scopeCanceled(Builder $query): void
    {
        $query->whereNotNull('canceled_at');
    }

    public function scopeInTrial(Builder $query): void
    {
        $query->whereNull('canceled_at')
            ->whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', '>=', self::currentDay()->toDateString());
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('canceled_at')
            ->where(function (Builder $query): void {
                $query->whereNull('trial_ends_at')
                    ->orWhereDate('trial_ends_at', '<', self::currentDay()->toDateString());
            });
    }

    private static function currentDay(): CarbonImmutable
    {
        return CarbonImmutable::now(PejotaHelper::getUserTimeZone())->startOfDay();
    }
}
