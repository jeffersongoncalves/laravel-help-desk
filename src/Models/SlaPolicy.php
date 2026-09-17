<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Concerns\UsesHelpDeskConnection;

/**
 * @property int $id
 * @property int|null $department_id
 * @property string|null $priority
 * @property int $first_response_minutes
 * @property int $resolution_minutes
 * @property array<string, array{0: string, 1: string}>|null $business_hours
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $department
 */
class SlaPolicy extends Model
{
    use UsesHelpDeskConnection;

    protected $table = 'help_desk_sla_policies';

    protected $fillable = [
        'department_id',
        'priority',
        'first_response_minutes',
        'resolution_minutes',
        'business_hours',
        'is_active',
    ];

    protected $casts = [
        'first_response_minutes' => 'integer',
        'resolution_minutes' => 'integer',
        'business_hours' => 'array',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
