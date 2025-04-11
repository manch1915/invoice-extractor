<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?array<string, mixed> $credentials
 * @property ?array<string, mixed> $settings
 * @property ?CarbonImmutable $expires_at
 */
#[Fillable(['provider', 'credentials', 'account_name', 'settings', 'connected_by', 'expires_at'])]
#[Hidden(['credentials'])]
class Integration extends Model
{
    public const GOOGLE = 'google';

    public const XERO = 'xero';

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? null;
    }
}
