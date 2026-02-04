<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use JeffersonGoncalves\HelpDesk\Enums\CommentType;

class TicketComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'help_desk_ticket_comments';

    protected $fillable = [
        'ticket_id',
        'author_type',
        'author_id',
        'body',
        'type',
        'is_internal',
        'email_message_id',
        'metadata',
    ];

    protected $casts = [
        'type' => CommentType::class,
        'is_internal' => 'boolean',
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo('author');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'comment_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeReplies($query)
    {
        return $query->where('type', CommentType::Reply);
    }

    public function scopeNotes($query)
    {
        return $query->where('type', CommentType::Note);
    }

    public function isReply(): bool
    {
        return $this->type === CommentType::Reply;
    }

    public function isNote(): bool
    {
        return $this->type === CommentType::Note;
    }

    public function isSystem(): bool
    {
        return $this->type === CommentType::System;
    }

    public function isInternal(): bool
    {
        return $this->is_internal;
    }
}
