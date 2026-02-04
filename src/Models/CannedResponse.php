<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function scopeForDepartment($query, ?int $departmentId)
    {
        return $query->where(function ($q) use ($departmentId) {
            $q->whereNull('department_id');
            if ($departmentId) {
                $q->orWhere('department_id', $departmentId);
            }
        });
    }
}
