<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $email_channel_id
 * @property string $message_id
 * @property string|null $in_reply_to
 * @property string|null $references
 * @property string $from_address
 * @property string|null $from_name
 * @property array $to_addresses
 * @property array|null $cc_addresses
 * @property string|null $subject
 * @property string|null $text_body
 * @property string|null $html_body
 * @property string|null $raw_payload
 * @property int|null $ticket_id
 * @property int|null $comment_id
 * @property string $status
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class InboundEmail extends Model
{
    use HasFactory;

    protected $table = 'help_desk_inbound_emails';

    protected $fillable = [
        'email_channel_id',
        'message_id',
        'in_reply_to',
        'references',
        'from_address',
        'from_name',
        'to_addresses',
        'cc_addresses',
        'subject',
        'text_body',
        'html_body',
        'raw_payload',
        'ticket_id',
        'comment_id',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'to_addresses' => 'array',
        'cc_addresses' => 'array',
        'processed_at' => 'datetime',
    ];

    /** @return BelongsTo<EmailChannel, $this> */
    public function emailChannel(): BelongsTo
    {
        return $this->belongsTo(EmailChannel::class, 'email_channel_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'comment_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function markProcessed(?int $ticketId = null, ?int $commentId = null): void
    {
        $this->update([
            'status' => 'processed',
            'ticket_id' => $ticketId,
            'comment_id' => $commentId,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function markIgnored(): void
    {
        $this->update([
            'status' => 'ignored',
            'processed_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }
}
