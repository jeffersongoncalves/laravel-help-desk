<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $email
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $categories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CannedResponse> $cannedResponses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EmailChannel> $emailChannels
 */
class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'help_desk_departments';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'email',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'department_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }

    /** @return HasMany<CannedResponse, $this> */
    public function cannedResponses(): HasMany
    {
        return $this->hasMany(CannedResponse::class, 'department_id');
    }

    /** @return HasMany<EmailChannel, $this> */
    public function emailChannels(): HasMany
    {
        return $this->hasMany(EmailChannel::class, 'department_id');
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<static> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
