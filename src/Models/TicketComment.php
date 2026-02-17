<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use JeffersonGoncalves\HelpDesk\Enums\CommentType;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string $author_type
 * @property int $author_id
 * @property string $body
 * @property CommentType $type
 * @property bool $is_internal
 * @property string|null $email_message_id
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read Ticket $ticket
 * @property-read \Illuminate\Database\Eloquent\Model|null $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketAttachment> $attachments
 */
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

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo('author');
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'comment_id');
    }

    /** @param Builder<static> $query */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    /** @param Builder<static> $query */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('is_internal', true);
    }

    /** @param Builder<static> $query */
    public function scopeReplies(Builder $query): Builder
    {
        return $query->where('type', CommentType::Reply);
    }

    /** @param Builder<static> $query */
    public function scopeNotes(Builder $query): Builder
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
