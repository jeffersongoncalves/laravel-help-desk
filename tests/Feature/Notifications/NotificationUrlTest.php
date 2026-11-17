<?php

use Illuminate\Notifications\AnonymousNotifiable;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Notifications\OperatorTicketActivityNotification;
use JeffersonGoncalves\HelpDesk\Notifications\TicketCreatedNotification;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->requester = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com']);
    $this->operator = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);

    $this->ticket = Ticket::create([
        'department_id' => $department->id,
        'user_type' => $this->requester->getMorphClass(),
        'user_id' => $this->requester->id,
        'title' => 'Printer on fire',
        'description' => 'Help',
        'status' => TicketStatus::Open,
    ]);
});

it('links to the app home without a resolver', function () {
    $mail = (new TicketCreatedNotification($this->ticket))->toMail($this->requester);

    expect($mail->actionUrl)->toBe(url('/'));
});

it('links requesters and operators to their own panels', function () {
    Ticket::resolveNotificationUrlUsing(fn (Ticket $ticket, object $notifiable, bool $forOperator) => ($forOperator ? '/admin/tickets/' : '/my/tickets/').$ticket->uuid);

    $uuid = $this->ticket->uuid;

    expect((new TicketCreatedNotification($this->ticket))->toMail($this->requester)->actionUrl)->toBe("/my/tickets/{$uuid}")
        ->and((new TicketCreatedNotification($this->ticket))->toMail(new AnonymousNotifiable)->actionUrl)->toBe("/my/tickets/{$uuid}")
        ->and((new OperatorTicketActivityNotification($this->ticket))->toMail($this->operator)->actionUrl)->toBe("/admin/tickets/{$uuid}");
});
