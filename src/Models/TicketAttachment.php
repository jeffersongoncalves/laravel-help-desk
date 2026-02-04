<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $table = 'help_desk_ticket_attachments';

    protected $fillable = [
        'uuid',
        'ticket_id',
        'comment_id',
        'uploaded_by_type',
        'uploaded_by_id',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_size',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketAttachment $attachment) {
            if (empty($attachment->uuid)) {
                $attachment->uuid = (string) Str::uuid();
            }

            if (empty($attachment->disk)) {
                $attachment->disk = config('help-desk.ticket.attachment_disk', 'local');
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'comment_id');
    }

    public function uploadedBy(): MorphTo
    {
        return $this->morphTo('uploaded_by');
    }

    public function getUrl(): ?string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    public function getTemporaryUrl(int $minutes = 5): ?string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->file_path,
            now()->addMinutes($minutes)
        );
    }

    public function getFileSizeForHumans(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
