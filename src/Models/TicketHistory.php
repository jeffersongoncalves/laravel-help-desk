<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Concerns\ResolvesMorphedIdentity;
use JeffersonGoncalves\HelpDesk\Concerns\UsesHelpDeskConnection;
use JeffersonGoncalves\HelpDesk\Enums\HistoryAction;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string|null $performer_type
 * @property int|null $performer_id
 * @property HistoryAction $action
 * @property string|null $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $description
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property-read Ticket $ticket
 * @property-read Model|null $performer
 * @property-read string|null $performer_name
 * @property-read string|null $performer_email
 */
class TicketHistory extends Model
{
    use ResolvesMorphedIdentity, UsesHelpDeskConnection;

    public $timestamps = false;

    protected $table = 'help_desk_ticket_history';

    protected $fillable = [
        'ticket_id',
        'performer_type',
        'performer_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'action' => HistoryAction::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketHistory $history) {
            if (empty($history->created_at)) {
                $history->created_at = now();
            }
        });
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function performer(): MorphTo
    {
        return $this->morphTo('performer');
    }

    /**
     * The performer, or null for a system action and for a performer whose
     * model is not installed here.
     */
    public function resolvedPerformer(): ?Model
    {
        return $this->resolveMorphed('performer', 'performer_type');
    }

    protected function performerName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshotField('performer', 'performer_type', 'performer', 'name'));
    }

    protected function performerEmail(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshotField('performer', 'performer_type', 'performer', 'email'));
    }
}
