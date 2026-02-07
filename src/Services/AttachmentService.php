<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\HelpDesk\Events\AttachmentAdded;
use JeffersonGoncalves\HelpDesk\Events\AttachmentRemoved;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

class AttachmentService
{
    public function store(Ticket $ticket, UploadedFile $file, Model $uploadedBy, ?TicketComment $comment = null): TicketAttachment
    {
        $disk = config('help-desk.ticket.attachment_disk', 'local');
        $path = config('help-desk.ticket.attachment_path', 'help-desk/attachments');

        $filePath = $file->store(
            $path.'/'.$ticket->uuid,
            $disk
        );

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment?->id,
            'uploaded_by_type' => $uploadedBy->getMorphClass(),
            'uploaded_by_id' => $uploadedBy->getKey(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        event(new AttachmentAdded($ticket, $attachment));

        return $attachment;
    }

    public function storeFromPath(Ticket $ticket, string $filePath, string $fileName, string $mimeType, int $fileSize, Model $uploadedBy, ?TicketComment $comment = null): TicketAttachment
    {
        $disk = config('help-desk.ticket.attachment_disk', 'local');
        $storagePath = config('help-desk.ticket.attachment_path', 'help-desk/attachments');

        $destination = $storagePath.'/'.$ticket->uuid.'/'.basename($filePath);
        Storage::disk($disk)->put($destination, file_get_contents($filePath));

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment?->id,
            'uploaded_by_type' => $uploadedBy->getMorphClass(),
            'uploaded_by_id' => $uploadedBy->getKey(),
            'file_name' => $fileName,
            'file_path' => $destination,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
        ]);

        event(new AttachmentAdded($ticket, $attachment));

        return $attachment;
    }

    public function delete(TicketAttachment $attachment, ?Model $removedBy = null): bool
    {
        Storage::disk($attachment->disk)->delete($attachment->file_path);

        /** @var Ticket $ticket */
        $ticket = $attachment->ticket;

        event(new AttachmentRemoved($ticket, $attachment, $removedBy));

        return $attachment->delete();
    }

    public function isAllowedExtension(string $extension): bool
    {
        $allowed = config('help-desk.ticket.allowed_extensions', []);

        return in_array(strtolower($extension), $allowed);
    }

    public function isWithinSizeLimit(int $sizeInKb): bool
    {
        $maxSize = config('help-desk.ticket.max_file_size', 10240);

        return $sizeInKb <= $maxSize;
    }
}
