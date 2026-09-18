<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Concerns\UsesHelpDeskConnection;

/**
 * @property int $id
 * @property string $name
 * @property int|null $department_id
 * @property array<string, mixed> $conditions
 * @property array<int, array<string, mixed>>|array<string, mixed> $actions
 * @property bool $is_active
 * @property Carbon|null $last_run_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $department
 */
class AutomationRule extends Model
{
    use UsesHelpDeskConnection;

    protected $table = 'help_desk_automation_rules';

    protected $fillable = [
        'name',
        'department_id',
        'conditions',
        'actions',
        'is_active',
        'last_run_at',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
