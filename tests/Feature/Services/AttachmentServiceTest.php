<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\HelpDesk\Events\AttachmentAdded;
use JeffersonGoncalves\HelpDesk\Events\AttachmentRemoved;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Services\AttachmentService;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    Storage::fake('local');

    $this->service = app(AttachmentService::class);
    $this->ticket = Ticket::factory()->create();
    $this->uploader = TestUser::create(['name' => 'Uploader', 'email' => 'uploader@example.com']);
});

it('stores an uploaded file and dispatches an event', function () {
    Event::fake([AttachmentAdded::class]);

    $file = UploadedFile::fake()->create('screenshot.png', 50);

    $attachment = $this->service->store($this->ticket, $file, $this->uploader);

    expect($attachment->file_name)->toBe('screenshot.png')
        ->and($attachment->ticket_id)->toBe($this->ticket->id);

    Storage::disk('local')->assertExists($attachment->file_path);
    Event::assertDispatched(AttachmentAdded::class);
});

it('deletes an attachment and removes the file', function () {
    Event::fake([AttachmentAdded::class, AttachmentRemoved::class]);

    $file = UploadedFile::fake()->create('doc.pdf', 50);
    $attachment = $this->service->store($this->ticket, $file, $this->uploader);
    $path = $attachment->file_path;

    expect($this->service->delete($attachment))->toBeTrue();

    Storage::disk('local')->assertMissing($path);
    Event::assertDispatched(AttachmentRemoved::class);
});

it('validates allowed extensions', function () {
    expect($this->service->isAllowedExtension('pdf'))->toBeTrue()
        ->and($this->service->isAllowedExtension('PDF'))->toBeTrue()
        ->and($this->service->isAllowedExtension('exe'))->toBeFalse();
});

it('validates the file size limit', function () {
    expect($this->service->isWithinSizeLimit(1024))->toBeTrue()
        ->and($this->service->isWithinSizeLimit(999999))->toBeFalse();
});
