<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;
use JeffersonGoncalves\HelpDesk\Services\AttachmentService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

function uploader(): TestUser
{
    return TestUser::create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);
}

function ticketForAttachment(): Ticket
{
    return Ticket::create([
        'department_id' => Department::factory()->create()->id,
        'user_type' => 'app-user',
        'user_id' => 1,
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ]);
}

/**
 * Rewrites the stored morph type to one this application cannot resolve, the
 * way an attachment uploaded by another application looks from the central one.
 */
function makeUploaderForeign(TicketAttachment $attachment): TicketAttachment
{
    TicketAttachment::query()
        ->whereKey($attachment->getKey())
        ->update(['uploaded_by_type' => 'app-b-user']);

    return $attachment->fresh();
}

beforeEach(fn () => Storage::fake('local'));

afterEach(fn () => Relation::morphMap([], false));

it('snapshots the uploader on an uploaded file', function () {
    $attachment = app(AttachmentService::class)->store(
        ticketForAttachment(),
        UploadedFile::fake()->create('invoice.pdf', 10),
        uploader(),
    );

    expect($attachment->metadata['uploader'])->toBe([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);
});

it('snapshots the uploader on a file stored from a path', function () {
    $source = sys_get_temp_dir().'/help-desk-uploader-snapshot.txt';
    file_put_contents($source, 'hello');

    $attachment = app(AttachmentService::class)->storeFromPath(
        ticketForAttachment(),
        $source,
        'notes.txt',
        'text/plain',
        5,
        uploader(),
    );

    expect($attachment->metadata['uploader'])->toBe([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    unlink($source);
});

it('reads the uploader from the live model when it resolves', function () {
    $attachment = app(AttachmentService::class)->store(
        ticketForAttachment(),
        UploadedFile::fake()->create('invoice.pdf', 10),
        uploader(),
    );

    $attachment->resolvedUploadedBy()->update(['name' => 'Grace B. Hopper']);

    expect($attachment->fresh()->uploader_name)->toBe('Grace B. Hopper')
        ->and($attachment->fresh()->uploader_email)->toBe('grace@example.com');
});

it('falls back to the snapshot when the uploader model is not installed here', function () {
    $attachment = makeUploaderForeign(app(AttachmentService::class)->store(
        ticketForAttachment(),
        UploadedFile::fake()->create('invoice.pdf', 10),
        uploader(),
    ));

    expect($attachment->resolvedUploadedBy())->toBeNull()
        ->and($attachment->uploader_name)->toBe('Grace Hopper')
        ->and($attachment->uploader_email)->toBe('grace@example.com');
});

it('resolves an uploader reachable through a registered morph alias', function () {
    $attachment = makeUploaderForeign(app(AttachmentService::class)->store(
        ticketForAttachment(),
        UploadedFile::fake()->create('invoice.pdf', 10),
        uploader(),
    ));

    Relation::morphMap(['app-b-user' => TestUser::class]);

    expect($attachment->resolvedUploadedBy())->not->toBeNull()
        ->and($attachment->uploader_name)->toBe('Grace Hopper');
});

it('returns null for a row written before the snapshot existed', function () {
    $attachment = makeUploaderForeign(app(AttachmentService::class)->store(
        ticketForAttachment(),
        UploadedFile::fake()->create('invoice.pdf', 10),
        uploader(),
    ));

    $attachment->update(['metadata' => null]);

    expect($attachment->uploader_name)->toBeNull()
        ->and($attachment->uploader_email)->toBeNull();
});
