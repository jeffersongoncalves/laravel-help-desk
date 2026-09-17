<?php

namespace JeffersonGoncalves\HelpDesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Http\Requests\Api\StoreAttachmentRequest;
use JeffersonGoncalves\HelpDesk\Http\Resources\TicketAttachmentResource;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttachmentController
{
    use ScopesToCaller;

    public function store(StoreAttachmentRequest $request, string $uuid): JsonResponse
    {
        $ticket = $this->ticketOr404($request, $uuid);

        // A temporary file, because storeFromPath is the existing path that
        // writes the row, fires AttachmentAdded and records the uploader
        // snapshot. Reimplementing that here to avoid one write would be two
        // code paths for one job.
        $temporary = tempnam(sys_get_temp_dir(), 'help-desk-api-');
        file_put_contents($temporary, $request->decoded());

        try {
            $attachment = HelpDesk::attachments()->storeFromPath(
                $ticket,
                $temporary,
                $request->validated()['file_name'],
                $request->validated()['mime_type'],
                filesize($temporary) ?: 0,
                $request->actor(),
                $this->commentOn($ticket, $request->validated()['comment_id'] ?? null),
            );
        } finally {
            @unlink($temporary);
        }

        return TicketAttachmentResource::make($attachment)->response()->setStatusCode(201);
    }

    /**
     * The bytes, base64 encoded, so the satellite can show a file back to the
     * person who uploaded it without holding credentials for the disk.
     */
    public function show(Request $request, string $uuid, string $attachmentUuid): JsonResponse
    {
        $ticket = $this->ticketOr404($request, $uuid);

        $attachment = $ticket->attachments()->where('uuid', $attachmentUuid)->first();

        if (! $attachment instanceof TicketAttachment) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'data' => TicketAttachmentResource::make($attachment)->resolve($request) + [
                'contents' => base64_encode(Storage::disk($attachment->disk)->get($attachment->file_path) ?? ''),
            ],
        ]);
    }

    protected function ticketOr404(Request $request, string $uuid): Ticket
    {
        $ticket = $this->scoped($request)->where('uuid', $uuid)->first();

        if (! $ticket instanceof Ticket) {
            throw new NotFoundHttpException;
        }

        return $ticket;
    }

    /**
     * A comment id from the payload is only honoured when it belongs to this
     * ticket, so a caller cannot attach a file to someone else's thread.
     */
    protected function commentOn(Ticket $ticket, ?int $commentId)
    {
        return $commentId === null
            ? null
            : $ticket->comments()->whereKey($commentId)->first();
    }
}
