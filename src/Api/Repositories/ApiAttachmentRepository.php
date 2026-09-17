<?php

namespace JeffersonGoncalves\HelpDesk\Api\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskClient;
use JeffersonGoncalves\HelpDesk\Api\HydratesModels;
use JeffersonGoncalves\HelpDesk\Api\ResolvesActor;
use JeffersonGoncalves\HelpDesk\Contracts\AttachmentRepository;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

/**
 * Attachments, over the signed API.
 *
 * The file travels base64 encoded inside the JSON body, so the signature
 * covers it like any other payload and no multipart handling is needed. The
 * cost is the cap: see help-desk.api.max_inline_attachment.
 */
class ApiAttachmentRepository implements AttachmentRepository
{
    use HydratesModels, ResolvesActor;

    public function __construct(protected HelpDeskClient $client) {}

    public function store(Ticket $ticket, UploadedFile $file, Model $uploadedBy, ?TicketComment $comment = null): TicketAttachment
    {
        return $this->send(
            $ticket,
            (string) file_get_contents($file->getRealPath()),
            $file->getClientOriginalName(),
            (string) ($file->getMimeType() ?: 'application/octet-stream'),
            $uploadedBy,
            $comment,
        );
    }

    public function storeFromPath(Ticket $ticket, string $filePath, string $fileName, string $mimeType, int $fileSize, Model $uploadedBy, ?TicketComment $comment = null): TicketAttachment
    {
        return $this->send(
            $ticket,
            (string) file_get_contents($filePath),
            $fileName,
            $mimeType,
            $uploadedBy,
            $comment,
        );
    }

    /**
     * The bytes back, for showing a file to the person who uploaded it. The
     * satellite has no disk, so this is how it gets them.
     */
    public function contents(TicketAttachment $attachment, string $ticketUuid): string
    {
        $payload = $this->client->get(
            "tickets/{$ticketUuid}/attachments/{$attachment->uuid}",
            ['actor' => $this->actorPayload()],
        );

        $contents = base64_decode((string) ($payload['data']['contents'] ?? ''), true);

        if ($contents === false) {
            throw HelpDeskApiException::failed(200, 'The attachment contents were not valid base64.');
        }

        return $contents;
    }

    public function delete(TicketAttachment $attachment, ?Model $removedBy = null): bool
    {
        throw HelpDeskApiException::operatorOnly('deleting an attachment');
    }

    /**
     * Configuration, not the database, so a satellite can reject a file before
     * spending a round trip on it. The central application checks again.
     */
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

    protected function send(Ticket $ticket, string $contents, string $fileName, string $mimeType, Model $uploadedBy, ?TicketComment $comment): TicketAttachment
    {
        $sizeInKb = (int) ceil(strlen($contents) / 1024);
        $limit = $this->inlineLimit();

        // Fail here rather than sending several megabytes for the server to
        // refuse. The server checks too — a satellite is not a trust boundary.
        if ($sizeInKb > $limit) {
            throw HelpDeskApiException::tooLargeInline($sizeInKb, $limit);
        }

        $payload = $this->client->post("tickets/{$ticket->uuid}/attachments", array_filter([
            'actor' => $this->actorPayload($uploadedBy),
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'contents' => base64_encode($contents),
            'comment_id' => $comment?->getKey(),
        ], fn ($value) => $value !== null));

        return $this->hydrateAttachment($payload['data']);
    }

    protected function inlineLimit(): int
    {
        $limit = config('help-desk.api.max_inline_attachment', 2048);

        return is_numeric($limit) ? (int) $limit : 2048;
    }
}
