<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Exceptions\TicketNotFoundException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

/**
 * The four methods a satellite panel needs, on the database driver.
 *
 * They already existed on the API side; what is tested here is that the same
 * call means the same thing on both, because that is the only reason to put
 * them on the contract.
 */
function contractUser(string $name = 'Ada Lovelace'): TestUser
{
    return TestUser::create(['name' => $name, 'email' => str()->random(8).'@example.com']);
}

function contractTicket(TestUser $user, ?string $title = null): Ticket
{
    return Ticket::factory()->create([
        'user_type' => $user->getMorphClass(),
        'user_id' => $user->getKey(),
        'title' => $title ?? 'Printer offline',
    ]);
}

it('lists only the tickets the actor opened', function () {
    $ada = contractUser();
    $grace = contractUser('Grace Hopper');

    $mine = contractTicket($ada);
    contractTicket($grace);

    $tickets = HelpDesk::tickets()->forActor($ada);

    expect($tickets->total())->toBe(1)
        ->and($tickets->items()[0]->id)->toBe($mine->id);
});

it('does not match another morph type holding the same key', function () {
    $ada = contractUser();

    // Same key, different type — the case a single `where` on user_id would
    // leak. The API is scoped server-side; here the query is the only guard.
    Ticket::factory()->create([
        'user_type' => 'some-other-app-user',
        'user_id' => $ada->getKey(),
    ]);

    expect(HelpDesk::tickets()->forActor($ada)->total())->toBe(0);
});

it('paginates rather than returning everything', function () {
    $ada = contractUser();

    foreach (range(1, 7) as $n) {
        contractTicket($ada, "Ticket {$n}");
    }

    $first = HelpDesk::tickets()->forActor($ada, perPage: 3);
    $third = HelpDesk::tickets()->forActor($ada, perPage: 3, page: 3);

    expect($first)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($first->total())->toBe(7)
        ->and($first->count())->toBe(3)
        ->and($first->lastPage())->toBe(3)
        ->and($third->count())->toBe(1);
});

it('narrows the list by status and priority, without leaving the actor scope', function () {
    $ada = contractUser();
    $grace = contractUser('Grace Hopper');

    $mine = contractTicket($ada);
    $mine->update(['status' => TicketStatus::InProgress, 'priority' => TicketPriority::Urgent]);

    contractTicket($ada)->update(['status' => TicketStatus::Closed]);

    // Same status and priority, another actor. The filter must not reach it.
    $theirs = contractTicket($grace);
    $theirs->update(['status' => TicketStatus::InProgress, 'priority' => TicketPriority::Urgent]);

    $tickets = HelpDesk::tickets()->forActor($ada, status: 'in_progress', priority: TicketPriority::Urgent);

    expect($tickets->total())->toBe(1)
        ->and($tickets->items()[0]->id)->toBe($mine->id);
});

it('searches the title and the reference number', function () {
    $ada = contractUser();

    $scanner = contractTicket($ada, 'Scanner jams on page two');
    contractTicket($ada, 'Printer offline');

    expect(HelpDesk::tickets()->forActor($ada, search: 'SCANNER')->total())->toBe(1)
        ->and(HelpDesk::tickets()->forActor($ada, search: $scanner->reference_number)->items()[0]->id)->toBe($scanner->id)
        // A wildcard is the character the user typed, not "match everything".
        ->and(HelpDesk::tickets()->forActor($ada, search: '%')->total())->toBe(0);
});

it('sorts by priority in severity order rather than by the stored string', function () {
    $ada = contractUser();

    contractTicket($ada, 'Low')->update(['priority' => TicketPriority::Low]);
    contractTicket($ada, 'Urgent')->update(['priority' => TicketPriority::Urgent]);
    contractTicket($ada, 'Medium')->update(['priority' => TicketPriority::Medium]);

    $tickets = HelpDesk::tickets()->forActor($ada, sort: 'priority', direction: 'desc');

    expect($tickets->pluck('title')->all())->toBe(['Urgent', 'Medium', 'Low']);
});

it('throws rather than ignoring a sort column, direction or status it does not know', function () {
    $ada = contractUser();

    // Silently falling back to the default order would hand back a list that
    // looks sorted, which is the failure this allow-list exists to avoid.
    expect(fn () => HelpDesk::tickets()->forActor($ada, sort: 'user_id'))
        ->toThrow(InvalidArgumentException::class, 'Tickets cannot be sorted by [user_id].')
        ->and(fn () => HelpDesk::tickets()->forActor($ada, sort: 'created_at', direction: 'sideways'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => HelpDesk::tickets()->forActor($ada, status: 'nonsense'))
        ->toThrow(InvalidArgumentException::class, 'Unknown ticket status [nonsense].');
});

it('offers only active departments, in sort order', function () {
    Department::factory()->create(['name' => 'Second', 'sort_order' => 2, 'is_active' => true]);
    Department::factory()->create(['name' => 'First', 'sort_order' => 1, 'is_active' => true]);
    Department::factory()->create(['name' => 'Retired', 'sort_order' => 0, 'is_active' => false]);

    $departments = HelpDesk::departments()->all();

    expect($departments)->toHaveCount(2)
        ->and($departments->pluck('name')->all())->toBe(['First', 'Second']);
});

it('offers only the active categories of the department asked for', function () {
    $billing = Department::factory()->create();
    $other = Department::factory()->create();

    Category::create(['department_id' => $billing->id, 'name' => 'Invoices', 'sort_order' => 1, 'is_active' => true]);
    Category::create(['department_id' => $billing->id, 'name' => 'Hidden', 'sort_order' => 2, 'is_active' => false]);
    Category::create(['department_id' => $other->id, 'name' => 'Elsewhere', 'sort_order' => 1, 'is_active' => true]);

    $categories = HelpDesk::departments()->categoriesFor($billing->id);

    expect($categories)->toHaveCount(1)
        ->and($categories->first())->toBeInstanceOf(Category::class)
        ->and($categories->first()->name)->toBe('Invoices');
});

it('hands back the bytes of an attachment', function () {
    Storage::fake('local');

    $ticket = Ticket::factory()->create();
    $attachment = HelpDesk::attachments()->store(
        $ticket,
        UploadedFile::fake()->createWithContent('invoice.pdf', 'the file bytes'),
        contractUser(),
    );

    expect(HelpDesk::attachments()->contents($attachment, $ticket->uuid))->toBe('the file bytes');
});

it('refuses to serve an attachment through another ticket uuid', function () {
    Storage::fake('local');

    $mine = Ticket::factory()->create();
    $theirs = Ticket::factory()->create();

    $attachment = HelpDesk::attachments()->store(
        $theirs,
        UploadedFile::fake()->createWithContent('secret.pdf', 'not yours'),
        contractUser(),
    );

    // Passing an attachment with someone else's ticket uuid must fail here as
    // it 404s over the API, rather than quietly serving the file.
    HelpDesk::attachments()->contents($attachment, $mine->uuid);
})->throws(TicketNotFoundException::class);
