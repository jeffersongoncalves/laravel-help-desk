<?php

namespace JeffersonGoncalves\HelpDesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Http\Requests\Api\StoreCommentRequest;
use JeffersonGoncalves\HelpDesk\Http\Resources\TicketCommentResource;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CommentController
{
    use ScopesToCaller;

    public function store(StoreCommentRequest $request, string $uuid): JsonResponse
    {
        $ticket = $this->scoped($request)->where('uuid', $uuid)->first();

        if (! $ticket instanceof Ticket) {
            throw new NotFoundHttpException;
        }

        // addComment, never addNote: an internal note is the operator side's,
        // and nothing a satellite sends should be able to become one.
        $comment = HelpDesk::addComment($ticket, $request->actor(), $request->validated()['body']);

        return TicketCommentResource::make($comment)->response()->setStatusCode(201);
    }
}
