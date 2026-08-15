<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use NunoMazer\Samehouse\BelongsToTenants;

/**
 * Read-only MCP access, always bound to a single client.
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property string $name
 * @property string $token_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
class ClientMcpToken extends Model
{
    use BelongsToTenants;

    public const TOKEN_PREFIX = 'pjm_';

    protected $guarded = ['id'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function generatePlainToken(): string
    {
        return self::TOKEN_PREFIX.Str::random(48);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Resolves a plain token ignoring tenant scopes: the MCP request carries no
     * authenticated user, so the token itself is what tells us the company.
     */
    public static function findActiveByPlainToken(string $plainToken): ?self
    {
        return self::query()
            ->withoutGlobalScopes()
            ->where('token_hash', self::hashToken($plainToken))
            ->whereNull('revoked_at')
            ->first();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
