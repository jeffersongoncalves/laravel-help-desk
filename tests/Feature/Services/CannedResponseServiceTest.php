<?php

use JeffersonGoncalves\HelpDesk\Models\CannedResponse;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\CannedResponseService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

afterEach(function () {
    CannedResponseService::flushCustomResolvers();
});

beforeEach(function () {
    $this->service = app(CannedResponseService::class);

    $this->user = TestUser::create([
        'name' => 'Jane Requester',
        'email' => 'jane@example.com',
    ]);

    $this->department = Department::create([
        'name' => 'Billing',
        'slug' => 'billing',
        'is_active' => true,
    ]);

    $this->ticket = Ticket::create([
        'department_id' => $this->department->id,
        'user_type' => $this->user->getMorphClass(),
        'user_id' => $this->user->id,
        'title' => 'Test Ticket',
        'description' => 'Test description',
    ]);
});

function makeCannedResponse(string $body): CannedResponse
{
    return CannedResponse::create([
        'title' => 'Test response',
        'body' => $body,
    ]);
}

it('substitutes every core placeholder', function () {
    $agent = TestUser::create(['name' => 'Agent Smith', 'email' => 'agent@example.com']);

    $response = makeCannedResponse('Hi {user_name}, ticket {ticket_code} in {department}, from {agent_name}.');

    $rendered = $this->service->render($response, $this->ticket, $agent);

    expect($rendered)->toBe(
        "Hi {$this->user->name}, ticket {$this->ticket->reference_number} in {$this->department->name}, from Agent Smith."
    );
});

it('renders an empty string for a missing agent rather than null or an exception', function () {
    $response = makeCannedResponse('Regards, {agent_name}');

    $rendered = $this->service->render($response, $this->ticket);

    expect($rendered)->toBe('Regards, ');
});

it('leaves an unknown placeholder untouched', function () {
    $response = makeCannedResponse('Order {order_number} for ticket {ticket_code}.');

    $rendered = $this->service->render($response, $this->ticket);

    expect($rendered)->toBe("Order {order_number} for ticket {$this->ticket->reference_number}.");
});

it('returns a body with no placeholders unchanged', function () {
    $response = makeCannedResponse('Thanks for reaching out.');

    $rendered = $this->service->render($response, $this->ticket);

    expect($rendered)->toBe('Thanks for reaching out.');
});

it('substitutes a registered custom variable', function () {
    CannedResponseService::resolveVariable('order_number', fn () => 'ORD-42');

    $response = makeCannedResponse('Order {order_number} for ticket {ticket_code}.');

    $rendered = $this->service->render($response, $this->ticket);

    expect($rendered)->toBe("Order ORD-42 for ticket {$this->ticket->reference_number}.");
});

it('passes the ticket and agent to a custom resolver', function () {
    $agent = TestUser::create(['name' => 'Agent Smith', 'email' => 'agent@example.com']);
    $seen = [];

    CannedResponseService::resolveVariable('order_number', function ($ticket, $resolvedAgent) use (&$seen) {
        $seen = [$ticket, $resolvedAgent];

        return 'ORD-42';
    });

    $this->service->render(makeCannedResponse('{order_number}'), $this->ticket, $agent);

    expect($seen[0]->is($this->ticket))->toBeTrue()
        ->and($seen[1]->is($agent))->toBeTrue();
});

it('never lets a custom resolver override a core placeholder', function () {
    CannedResponseService::resolveVariable('ticket_code', fn () => 'HIJACKED');

    $response = makeCannedResponse('Ticket {ticket_code}.');

    $rendered = $this->service->render($response, $this->ticket);

    expect($rendered)->toBe("Ticket {$this->ticket->reference_number}.");
});

it('renders an empty string when a custom resolver has nothing for this ticket', function () {
    CannedResponseService::resolveVariable('order_number', fn () => null);

    $response = makeCannedResponse('Order {order_number}.');

    $rendered = $this->service->render($response, $this->ticket);

    expect($rendered)->toBe('Order .');
});

it('does not leak a custom resolver registered in a previous test', function () {
    $response = makeCannedResponse('Order {order_number}.');

    $rendered = $this->service->render($response, $this->ticket);

    expect($rendered)->toBe('Order {order_number}.');
});
