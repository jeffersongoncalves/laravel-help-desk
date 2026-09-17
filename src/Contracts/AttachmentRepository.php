<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

/**
 * Where ticket attachments live. See TicketRepository for why "repository".
 */
interface AttachmentRepository
{
    public function store(Ticket $ticket, UploadedFile $file, Model $uploadedBy, ?TicketComment $comment = null): TicketAttachment;

    public function storeFromPath(Ticket $ticket, string $filePath, string $fileName, string $mimeType, int $fileSize, Model $uploadedBy, ?TicketComment $comment = null): TicketAttachment;

    /**
     * Removes the stored file as well as the row.
     */
    public function delete(TicketAttachment $attachment, ?Model $removedBy = null): bool;

    /**
     * Neither of these enforces anything. They exist so a caller can reject a
     * file with its own message before storing it.
     */
    public function isAllowedExtension(string $extension): bool;

    public function isWithinSizeLimit(int $sizeInKb): bool;
}
