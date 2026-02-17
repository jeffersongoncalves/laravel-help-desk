<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $department_id
 * @property string $title
 * @property string $body
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read Department|null $department
 */
class CannedResponse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'help_desk_canned_responses';

    protected $fillable = [
        'department_id',
        'title',
        'body',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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

    /** @param Builder<static> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    /** @param Builder<static> $query */
    public function scopeForDepartment(Builder $query, ?int $departmentId): Builder
    {
        return $query->where(function ($q) use ($departmentId) {
            $q->whereNull('department_id');
            if ($departmentId) {
                $q->orWhere('department_id', $departmentId);
            }
        });
    }
}
