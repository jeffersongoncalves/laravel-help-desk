<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JeffersonGoncalves\HelpDesk\Concerns\ResolvesMorphedIdentity;
use JeffersonGoncalves\HelpDesk\Concerns\UsesHelpDeskConnection;
use JeffersonGoncalves\HelpDesk\Database\Factories\TicketFactory;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;

/**
 * @property int $id
 * @property string $uuid
 * @property string $reference_number
 * @property int $department_id
 * @property int|null $category_id
 * @property string $user_type
 * @property int $user_id
 * @property string|null $assigned_to_type
 * @property int|null $assigned_to_id
 * @property string $title
 * @property string $description
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property string $source
 * @property string|null $app_key
 * @property-read string|null $app_name
 * @property string|null $email_message_id
 * @property Carbon|null $closed_at
 * @property Carbon|null $due_at
 * @property Carbon|null $last_replied_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Model|null $user
 * @property-read string|null $requester_name
 * @property-read string|null $requester_email
 * @property-read Model|null $assignedTo
 * @property-read Department $department
 * @property-read Category|null $category
 * @property-read Collection<int, TicketComment> $comments
 * @property-read Collection<int, TicketAttachment> $attachments
 * @property-read Collection<int, TicketHistory> $history
 * @property-read Collection<int, TicketWatcher> $watchers
 * @property-read TicketFeedback|null $feedback
 */
class Ticket extends Model
{
    use HasFactory, ResolvesMorphedIdentity, SoftDeletes, UsesHelpDeskConnection;

    /**
     * The columns a list may be sorted by. See assertSortable().
     *
     * @var list<string>
     */
    public const SORTABLE = ['created_at', 'last_replied_at', 'priority', 'status'];

    protected $table = 'help_desk_tickets';

    protected $fillable = [
        'uuid',
        'reference_number',
        'department_id',
        'category_id',
        'user_type',
        'user_id',
        'assigned_to_type',
        'assigned_to_id',
        'title',
        'description',
        'status',
        'priority',
        'source',
        'app_key',
        'email_message_id',
        'closed_at',
        'due_at',
        'last_replied_at',
        'metadata',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'metadata' => 'array',
        'closed_at' => 'datetime',
        'due_at' => 'datetime',
        'last_replied_at' => 'datetime',
    ];

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }

    protected static function booted(): void
    {
        // Opt-in, so the central application that handles every application's
        // tickets is unaffected. The config is read per query rather than at
        // boot, which keeps it testable and honours a runtime change.
        static::addGlobalScope('helpDeskApp', function (Builder $query): void {
            $key = config('help-desk.app.key');

            if ($key !== null && config('help-desk.scope_to_app', false)) {
                $query->where($query->getModel()->getTable().'.app_key', $key);
            }
        });

        static::creating(function (Ticket $ticket) {
            if ($ticket->app_key === null) {
                $ticket->app_key = config('help-desk.app.key');
            }

            if ($ticket->app_key !== null) {
                // The central application has no configuration describing the
                // applications it serves, so the label travels with the ticket.
                $ticket->metadata = array_merge($ticket->metadata ?? [], [
                    'app' => array_filter([
                        'name' => config('help-desk.app.name'),
                    ], fn ($value) => filled($value)),
                ]);
            }

            if (empty($ticket->uuid)) {
                $ticket->uuid = (string) Str::uuid();
            }

            if (empty($ticket->status)) {
                $ticket->status = config('help-desk.ticket.default_status', 'open');
            }

            if (empty($ticket->priority)) {
                $ticket->priority = config('help-desk.ticket.default_priority', 'medium');
            }

            if (empty($ticket->reference_number)) {
                // Temporary unique placeholder. The definitive reference number
                // is derived from the auto-incremented primary key in the
                // "created" hook, which avoids the race condition that occurs
                // when concurrent inserts read the same "last id" value.
                $ticket->reference_number = 'TMP-'.Str::random(24);
            }
        });

        static::created(function (Ticket $ticket) {
            if (str_starts_with((string) $ticket->reference_number, 'TMP-')) {
                $ticket->reference_number = static::generateReferenceNumber($ticket->id);
                $ticket->saveQuietly();
            }
        });
    }

    public static function generateReferenceNumber(int $id): string
    {
        $prefix = config('help-desk.ticket.reference_prefix', 'HD');

        return sprintf('%s-%05d', $prefix, $id);
    }

    public function user(): MorphTo
    {
        return $this->morphTo('user');
    }

    /**
     * The requester, or null when their model is not installed here.
     */
    public function requester(): ?Model
    {
        return $this->resolveMorphed('user', 'user_type');
    }

    protected function requesterName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshotField('user', 'user_type', 'requester', 'name'));
    }

    protected function requesterEmail(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshotField('user', 'user_type', 'requester', 'email'));
    }

    /**
     * Notify the requester through their model, or by email alone when that
     * model belongs to another application sharing this help desk database.
     */
    public function notifyRequester(Notification $notification): void
    {
        $requester = $this->requester();

        if ($requester && method_exists($requester, 'notify')) {
            $requester->notify($notification);

            return;
        }

        if ($this->requester_email !== null) {
            NotificationFacade::route('mail', $this->requester_email)->notify($notification);
        }
    }

    public function assignedTo(): MorphTo
    {
        return $this->morphTo('assignedTo');
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

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticket_id');
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'ticket_id');
    }

    /** @return HasMany<TicketHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(TicketHistory::class, 'ticket_id');
    }

    /** @return HasMany<TicketWatcher, $this> */
    public function watchers(): HasMany
    {
        return $this->hasMany(TicketWatcher::class, 'ticket_id');
    }

    /** @return HasOne<TicketFeedback, $this> */
    public function feedback(): HasOne
    {
        return $this->hasOne(TicketFeedback::class, 'ticket_id');
    }

    /**
     * The label of the application the ticket came from, falling back to its
     * key so the column is never blank for a ticket that has one.
     */
    protected function appName(): Attribute
    {
        return Attribute::get(function (): ?string {
            $name = data_get($this->metadata, 'app.name');

            return filled($name) ? (string) $name : $this->app_key;
        });
    }

    /** @param Builder<static> $query */
    public function scopeForApp(Builder $query, ?string $key): Builder
    {
        return $key === null
            ? $query->whereNull('app_key')
            : $query->where('app_key', $key);
    }

    /** @param Builder<static> $query */
    public function scopeByStatus(Builder $query, TicketStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /** @param Builder<static> $query */
    public function scopeByPriority(Builder $query, TicketPriority $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * The statuses a list is narrowed to, as strings, enums or a mix of both.
     *
     * An unknown value throws rather than quietly matching nothing: a list
     * that came back empty because of a typo looks exactly like a list that
     * came back empty because there is nothing to show.
     *
     * @param  array<int, TicketStatus|string>|TicketStatus|string|null  $status
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeStatusIn(Builder $query, array|TicketStatus|string|null $status): Builder
    {
        $values = self::statusValues($status);

        return $values === [] ? $query : $query->whereIn('status', $values);
    }

    /**
     * @param  array<int, TicketPriority|string>|TicketPriority|string|null  $priority
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePriorityIn(Builder $query, array|TicketPriority|string|null $priority): Builder
    {
        $values = self::priorityValues($priority);

        return $values === [] ? $query : $query->whereIn('priority', $values);
    }

    /**
     * Title and reference number only.
     *
     * Not the description: it arrives as rich text, and matching the markup
     * would produce hits the user cannot see anywhere in the row.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        // `!` rather than the usual backslash: the escape character has to be
        // written into the SQL, and a backslash literal is spelled differently
        // on MySQL than on SQLite and Postgres. LOWER() because LIKE is
        // case-sensitive on Postgres and a search box that is not on one
        // database and is on another is worse than either.
        $term = '%'.mb_strtolower(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term)).'%';

        return $query->where(fn (Builder $query) => $query
            ->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term])
            ->orWhereRaw("LOWER(reference_number) LIKE ? ESCAPE '!'", [$term]));
    }

    /**
     * Newest first unless told otherwise, so the default list is unchanged.
     *
     * `priority` and `status` are stored as their string values, so ordering
     * by the column alphabetically would put `high` above `low` and call it
     * sorted. Both are ordered by the enum's own sequence instead — severity
     * for priority, lifecycle for status.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSorted(Builder $query, ?string $sort = null, string $direction = 'desc'): Builder
    {
        self::assertSortable($sort, $direction);

        if ($sort === null) {
            return $query->latest();
        }

        return match ($sort) {
            'priority' => $query->orderByRaw(self::enumOrder('priority', TicketPriority::cases()).' '.$direction),
            'status' => $query->orderByRaw(self::enumOrder('status', TicketStatus::cases()).' '.$direction),
            default => $query->orderBy($sort, $direction),
        };
    }

    /**
     * A caller-supplied column reaches the query builder, so this is an
     * allow-list on principle, not because of what is in the table today.
     *
     * Shared with the API driver, which checks before spending a round trip on
     * a sort the central application would refuse anyway.
     */
    public static function assertSortable(?string $sort, string $direction = 'desc'): void
    {
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("Sort direction [{$direction}] is not asc or desc.");
        }

        if ($sort !== null && ! in_array($sort, self::SORTABLE, true)) {
            throw new InvalidArgumentException(
                "Tickets cannot be sorted by [{$sort}]. Sortable: ".implode(', ', self::SORTABLE).'.'
            );
        }
    }

    /**
     * @param  array<int, TicketStatus|string>|TicketStatus|string|null  $status
     * @return list<string>
     */
    public static function statusValues(array|TicketStatus|string|null $status): array
    {
        return array_values(array_map(
            fn (TicketStatus|string $value): string => $value instanceof TicketStatus
                ? $value->value
                : (TicketStatus::tryFrom($value) ?? throw new InvalidArgumentException("Unknown ticket status [{$value}]."))->value,
            Arr::wrap($status),
        ));
    }

    /**
     * @param  array<int, TicketPriority|string>|TicketPriority|string|null  $priority
     * @return list<string>
     */
    public static function priorityValues(array|TicketPriority|string|null $priority): array
    {
        return array_values(array_map(
            fn (TicketPriority|string $value): string => $value instanceof TicketPriority
                ? $value->value
                : (TicketPriority::tryFrom($value) ?? throw new InvalidArgumentException("Unknown ticket priority [{$value}]."))->value,
            Arr::wrap($priority),
        ));
    }

    /**
     * @param  array<int, TicketPriority|TicketStatus>  $cases
     */
    protected static function enumOrder(string $column, array $cases): string
    {
        $whens = '';

        foreach ($cases as $index => $case) {
            $whens .= " WHEN '{$case->value}' THEN {$index}";
        }

        return "CASE {$column}{$whens} END";
    }

    /** @param Builder<static> $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            TicketStatus::Closed->value,
            TicketStatus::Resolved->value,
        ]);
    }

    /** @param Builder<static> $query */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TicketStatus::Closed->value,
            TicketStatus::Resolved->value,
        ]);
    }

    /** @param Builder<static> $query */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', [
                TicketStatus::Closed->value,
                TicketStatus::Resolved->value,
            ]);
    }

    /** @param Builder<static> $query */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [TicketStatus::Closed, TicketStatus::Resolved]);
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }

    public function isResolved(): bool
    {
        return $this->status === TicketStatus::Resolved;
    }

    public function isAssigned(): bool
    {
        return $this->assigned_to_id !== null;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && $this->isOpen();
    }

    /**
     * Whether a requester may still submit CSAT feedback: the ticket has
     * reached a terminal-enough state (closed or resolved), nobody has
     * submitted feedback for it yet, and the configured window since it got
     * there has not elapsed.
     */
    public function canReceiveFeedback(): bool
    {
        if (! $this->isClosed() && ! $this->isResolved()) {
            return false;
        }

        if ($this->feedback !== null) {
            return false;
        }

        $since = $this->closed_at ?? $this->updated_at;

        if ($since === null) {
            return false;
        }

        $windowDays = config('help-desk.feedback.window_days', 14);

        return $since->copy()->addDays($windowDays)->isFuture();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
