<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CompanySettingsEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;

class Client extends Model
{
    use BelongsToTenants,
        HasFactory,
        HasFilamentComments;

    protected $guarded = ['id'];

    public function labelName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => auth()->user()->company
                ->settings()->get(CompanySettingsEnum::CLIENT_PREFER_TRADENAME->value)
                        ? $this->tradename
                        : $this->name,
        );
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return array<int, string>
     */
    public function billingEmailRecipients(): array
    {
        $emails = $this->contacts()
            ->where('receives_billing', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        if ($emails === [] && filled($this->email)) {
            return [$this->email];
        }

        return $emails;
    }

    /**
     * @return array<int, string>
     */
    public function billingWhatsappRecipients(): array
    {
        return $this->contacts()
            ->where('receives_billing', true)
            ->whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->pluck('whatsapp')
            ->unique()
            ->values()
            ->all();
    }

    public function resolvedEmailSubject(): ?string
    {
        return $this->resolvedBilling($this->billing_email_subject, CompanySettingsEnum::BILLING_EMAIL_SUBJECT);
    }

    public function resolvedEmailBody(): ?string
    {
        return $this->resolvedBilling($this->billing_email_body, CompanySettingsEnum::BILLING_EMAIL_BODY);
    }

    public function resolvedEmailSignature(): ?string
    {
        return $this->resolvedBilling($this->billing_email_signature, CompanySettingsEnum::BILLING_EMAIL_SIGNATURE);
    }

    public function resolvedWhatsappTemplate(): ?string
    {
        return $this->resolvedBilling($this->billing_whatsapp_template, CompanySettingsEnum::BILLING_WHATSAPP_TEMPLATE);
    }

    private function resolvedBilling(?string $override, CompanySettingsEnum $default): ?string
    {
        if (! self::billingOverrideIsBlank($override)) {
            return $override;
        }

        return $this->company?->settings()->get($default->value);
    }

    private static function billingOverrideIsBlank(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        $text = str_replace(['&nbsp;', '&#160;', "\xC2\xA0"], ' ', strip_tags($value));

        return trim($text) === '';
    }

    protected function casts(): array
    {
        return [
            'default_hourly_rate' => MoneyCast::class,
            'billable_default' => 'boolean',
            'bill_by_email' => 'boolean',
            'bill_by_whatsapp' => 'boolean',
        ];
    }
}
