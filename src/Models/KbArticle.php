<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Concerns\HasSlug;
use JeffersonGoncalves\HelpDesk\Concerns\UsesHelpDeskConnection;

/**
 * @property int $id
 * @property int|null $department_id
 * @property int|null $category_id
 * @property string|null $app_key
 * @property string $title
 * @property string $slug
 * @property string $body
 * @property bool $is_published
 * @property int $views_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Department|null $department
 * @property-read Category|null $category
 */
class KbArticle extends Model
{
    use HasSlug, SoftDeletes, UsesHelpDeskConnection;

    protected $table = 'help_desk_kb_articles';

    protected $fillable = [
        'department_id',
        'category_id',
        'app_key',
        'title',
        'slug',
        'body',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'views_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // Same isolation mechanism as Ticket: lets a satellite application
        // keep its own knowledge base when several apps share one help-desk
        // database, without inventing a second multi-app convention.
        static::addGlobalScope('helpDeskApp', function ($query): void {
            $key = config('help-desk.app.key');

            if ($key !== null && config('help-desk.scope_to_app', false)) {
                $query->where($query->getModel()->getTable().'.app_key', $key);
            }
        });

        static::creating(function (KbArticle $article) {
            if ($article->app_key === null) {
                $article->app_key = config('help-desk.app.key');
            }
        });
    }

    public function getSlugSource(): string
    {
        return 'title';
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
