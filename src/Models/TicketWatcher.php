<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Concerns\ResolvesMorphedIdentity;
use JeffersonGoncalves\HelpDesk\Concerns\UsesHelpDeskConnection;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string $watcher_type
 * @property int $watcher_id
 * @property Carbon|null $created_at
 * @property-read Ticket $ticket
 * @property array|null $metadata
 * @property-read Model|null $watcher
 * @property-read string|null $watcher_name
 * @property-read string|null $watcher_email
 */
class TicketWatcher extends Model
{
    use ResolvesMorphedIdentity, UsesHelpDeskConnection;

    public $timestamps = false;

    protected $table = 'help_desk_ticket_watchers';

    protected $fillable = [
        'ticket_id',
        'watcher_type',
        'watcher_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketWatcher $watcher) {
            if (empty($watcher->created_at)) {
                $watcher->created_at = now();
            }
        });
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function watcher(): MorphTo
    {
        return $this->morphTo('watcher');
    }

    /**
     * The watcher, or null when their model is not installed here.
     */
    public function resolvedWatcher(): ?Model
    {
        return $this->resolveMorphed('watcher', 'watcher_type');
    }

    protected function watcherName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshotField('watcher', 'watcher_type', 'watcher', 'name'));
    }

    protected function watcherEmail(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshotField('watcher', 'watcher_type', 'watcher', 'email'));
    }
}
