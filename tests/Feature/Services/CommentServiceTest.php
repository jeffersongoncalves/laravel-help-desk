<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\HelpDesk\Enums\CommentType;
use JeffersonGoncalves\HelpDesk\Events\CommentAdded;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;
use JeffersonGoncalves\HelpDesk\Services\CommentService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    $this->service = app(CommentService::class);
    $this->ticket = Ticket::factory()->create();
    $this->author = TestUser::create(['name' => 'Author', 'email' => 'author@example.com']);
});

it('adds a public reply and updates last replied at', function () {
    Event::fake([CommentAdded::class]);

    $comment = $this->service->addReply($this->ticket, $this->author, 'My reply');

    expect($comment->type)->toBe(CommentType::Reply)
        ->and($comment->is_internal)->toBeFalse()
        ->and($comment->body)->toBe('My reply')
        ->and($this->ticket->fresh()->last_replied_at)->not->toBeNull();

    Event::assertDispatched(CommentAdded::class);
});

it('adds an internal note', function () {
    $note = $this->service->addNote($this->ticket, $this->author, 'Internal note');

    expect($note->type)->toBe(CommentType::Note)
        ->and($note->is_internal)->toBeTrue();
});

it('adds a system comment without an author', function () {
    $comment = $this->service->addSystemComment($this->ticket, 'System message');

    expect($comment->type)->toBe(CommentType::System)
        ->and($comment->author_type)->toBeNull()
        ->and($comment->author_id)->toBeNull()
        ->and($comment->isSystem())->toBeTrue();
});

it('adds a comment with attachments', function () {
    Storage::fake('local');

    $comment = $this->service->addReply($this->ticket, $this->author, 'With file', [
        'attachments' => [UploadedFile::fake()->create('document.pdf', 100)],
    ]);

    expect($comment->attachments)->toHaveCount(1)
        ->and($comment->attachments->first()->file_name)->toBe('document.pdf');
});

it('deletes a comment', function () {
    $comment = $this->service->addReply($this->ticket, $this->author, 'To delete');

    expect($this->service->delete($comment))->toBeTrue()
        ->and($comment->trashed())->toBeTrue()
        ->and(TicketComment::find($comment->id))->toBeNull();
});
