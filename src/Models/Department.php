<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Concerns\HasSlug;
use JeffersonGoncalves\HelpDesk\Concerns\UsesHelpDeskConnection;
use JeffersonGoncalves\HelpDesk\Database\Factories\DepartmentFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $email
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Ticket> $tickets
 * @property-read Collection<int, CannedResponse> $cannedResponses
 * @property-read Collection<int, EmailChannel> $emailChannels
 */
class Department extends Model
{
    use HasFactory, HasSlug, SoftDeletes, UsesHelpDeskConnection;

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

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }

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

    /**
     * The department's operators who have not turned off email notifications
     * for it, loaded from their own models.
     *
     * Read from the pivot table on the help desk connection, then each morph
     * type is loaded separately on its own model's connection — a join would
     * break when the two live in different databases. Operator types this
     * application does not have are skipped, as with watchers.
     *
     * @param  list<string>  $exceptKeys  "type:id" pairs to leave out.
     * @return list<Model>
     */
    public function operatorsToNotify(array $exceptKeys = []): array
    {
        $rows = $this->getConnection()
            ->table('help_desk_department_operator')
            ->where('department_id', $this->getKey())
            ->where('email_notifications', true)
            ->get(['operator_type', 'operator_id']);

        $operators = [];

        foreach ($rows->groupBy('operator_type') as $type => $group) {
            if (! Ticket::morphIsResolvable($type)) {
                continue;
            }

            /** @var class-string<Model> $class */
            $class = Relation::getMorphedModel($type) ?? $type;

            foreach ($class::query()->whereKey($group->pluck('operator_id')->all())->get() as $operator) {
                if (! in_array($operator->getMorphClass().':'.$operator->getKey(), $exceptKeys, true)) {
                    $operators[] = $operator;
                }
            }
        }

        return $operators;
    }

    /**
     * Whether the operator turned off email notifications for this department.
     */
    public function operatorOptedOutOfEmail(Model $operator): bool
    {
        return $this->getConnection()
            ->table('help_desk_department_operator')
            ->where('department_id', $this->getKey())
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->where('email_notifications', false)
            ->exists();
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
