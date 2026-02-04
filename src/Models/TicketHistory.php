<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class TicketHistory extends Model
{
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function performer(): MorphTo
    {
        return $this->morphTo('performer');
    }
}
