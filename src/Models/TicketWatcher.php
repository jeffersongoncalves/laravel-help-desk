<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketWatcher extends Model
{
    public $timestamps = false;

    protected $table = 'help_desk_ticket_watchers';

    protected $fillable = [
        'ticket_id',
        'watcher_type',
        'watcher_id',
        'created_at',
    ];

    protected $casts = [
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function watcher(): MorphTo
    {
        return $this->morphTo('watcher');
    }
}
