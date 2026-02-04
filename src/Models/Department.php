<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'department_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }

    public function cannedResponses(): HasMany
    {
        return $this->hasMany(CannedResponse::class, 'department_id');
    }

    public function emailChannels(): HasMany
    {
        return $this->hasMany(EmailChannel::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
