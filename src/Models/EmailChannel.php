<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $department_id
 * @property string $name
 * @property string $driver
 * @property string $email_address
 * @property array $settings
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_polled_at
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Department|null $department
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InboundEmail> $inboundEmails
 */
class EmailChannel extends Model
{
    use HasFactory;

    protected $table = 'help_desk_email_channels';

    protected $fillable = [
        'department_id',
        'name',
        'driver',
        'email_address',
        'settings',
        'is_active',
        'last_polled_at',
        'last_error',
    ];

    protected $casts = [
        'settings' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_polled_at' => 'datetime',
    ];

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return HasMany<InboundEmail, $this> */
    public function inboundEmails(): HasMany
    {
        return $this->hasMany(InboundEmail::class, 'email_channel_id');
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<static> $query */
    public function scopeByDriver(Builder $query, string $driver): Builder
    {
        return $query->where('driver', $driver);
    }

    public function markPolled(): void
    {
        $this->update([
            'last_polled_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markError(string $error): void
    {
        $this->update([
            'last_error' => $error,
        ]);
    }
}
