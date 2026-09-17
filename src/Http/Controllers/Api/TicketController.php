<?php

namespace JeffersonGoncalves\HelpDesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use JeffersonGoncalves\HelpDesk\Exceptions\InvalidStatusTransitionException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Http\Requests\Api\StoreTicketRequest;
use JeffersonGoncalves\HelpDesk\Http\Requests\Api\UpdateTicketStatusRequest;
use JeffersonGoncalves\HelpDesk\Http\Resources\TicketResource;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketController
{
    use ScopesToCaller;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tickets = $this->scoped($request)
            // Still inside the caller's scope, so a reference belonging to
            // someone else simply returns nothing.
            ->when(
                $request->filled('reference_number'),
                fn ($query) => $query->where('reference_number', $request->string('reference_number')),
            )
            ->latest()
            ->paginate((int) min($request->integer('per_page', 25), 100));

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        // The app key is stamped by Ticket::creating from config, which on the
        // central application is not the caller's, so it is set explicitly here
        // from the signature.
        $ticket = HelpDesk::createTicket(
            $request->ticketAttributes() + ['app_key' => $request->appKey()],
            $request->actor(),
        );

        return TicketResource::make($ticket)->response()->setStatusCode(201);
    }

    /**
     * Closing and reopening, the two status changes that belong to the person
     * who opened the ticket.
     *
     * Routed through the service rather than writing the column, so the
     * transition table still applies — including `help-desk.ticket.allow_reopen`,
     * which it reads.
     */
    public function status(UpdateTicketStatusRequest $request, string $uuid): JsonResponse
    {
        $ticket = $this->scoped($request)->where('uuid', $uuid)->first();

        if (! $ticket instanceof Ticket) {
            throw new NotFoundHttpException;
        }

        try {
            $ticket = HelpDesk::changeStatus($ticket, $request->status(), $request->actor());
        } catch (InvalidStatusTransitionException $e) {
            // 409, not 422: the request is well formed and the caller is
            // allowed to make it. The ticket is simply not in a state the move
            // is legal from, which is a different thing to tell them — and
            // what lets the client raise the documented exception.
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return TicketResource::make($ticket)->response();
    }

    public function show(Request $request, string $uuid): TicketResource
    {
        $ticket = $this->scoped($request)->where('uuid', $uuid)->first();

        if (! $ticket instanceof Ticket) {
            // 404 rather than 403 for a ticket that exists but belongs to
            // someone else: confirming a reference exists is already more than
            // the caller should learn.
            throw new NotFoundHttpException;
        }

        $ticket->setRelation(
            'comments',
            $ticket->comments()->public()->oldest()->get(),
        );

        $ticket->setRelation(
            'attachments',
            $ticket->attachments()->oldest()->get(),
        );

        return TicketResource::make($ticket);
    }
}
