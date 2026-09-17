<?php

namespace JeffersonGoncalves\HelpDesk\Api\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use JeffersonGoncalves\HelpDesk\Contracts\AttachmentRepository;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

/**
 * Attachments over the signed API are not implemented — signing a multipart
 * body, and getting the bytes onto a disk the satellite has no credentials
 * for, are their own problem. Tracked separately.
 *
 * The two validation helpers do work: they read configuration, not the
 * database, so a satellite can still reject a file before trying to send it.
 */
class ApiAttachmentRepository implements AttachmentRepository
{
    public function store(Ticket $ticket, UploadedFile $file, Model $uploadedBy, ?TicketComment $comment = null): TicketAttachment
    {
        throw HelpDeskApiException::notImplemented('Storing an attachment');
    }

    public function storeFromPath(Ticket $ticket, string $filePath, string $fileName, string $mimeType, int $fileSize, Model $uploadedBy, ?TicketComment $comment = null): TicketAttachment
    {
        throw HelpDeskApiException::notImplemented('Storing an attachment');
    }

    public function delete(TicketAttachment $attachment, ?Model $removedBy = null): bool
    {
        throw HelpDeskApiException::notImplemented('Deleting an attachment');
    }

    public function isAllowedExtension(string $extension): bool
    {
        $allowed = config('help-desk.ticket.allowed_extensions', []);

        return in_array(strtolower($extension), is_array($allowed) ? $allowed : [], true);
    }

    public function isWithinSizeLimit(int $sizeInKb): bool
    {
        $maxSize = config('help-desk.ticket.max_file_size', 10240);

        return $sizeInKb <= (is_numeric($maxSize) ? (int) $maxSize : 10240);
    }
}
