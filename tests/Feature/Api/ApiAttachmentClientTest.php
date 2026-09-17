<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicket;
use JeffersonGoncalves\HelpDesk\Api\Models\ApiTicketAttachment;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Facades\HelpDesk;
use JeffersonGoncalves\HelpDesk\Tests\TestUser;

beforeEach(function () {
    config()->set('help-desk.driver', 'api');
    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.api.url', 'https://support.example.com');
    config()->set('help-desk.api.secret', 'app-a-secret');

    Storage::fake('local');
});

function apiUploader(): TestUser
{
    return TestUser::create(['name' => 'Ada Lovelace', 'email' => Str::random(8).'@example.com']);
}

function attachmentResponse(): array
{
    return ['data' => [
        'uuid' => 'aaaaaaaa-e29b-41d4-a716-446655440000',
        'comment_id' => null,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 14,
        'uploader_name' => 'Ada Lovelace',
        'created_at' => '2026-09-17T00:00:00+00:00',
    ]];
}

function hydratedTicket(): ApiTicket
{
    $ticket = new ApiTicket;
    $ticket->forceFill(['uuid' => '550e8400-e29b-41d4-a716-446655440000']);
    $ticket->exists = true;

    return $ticket;
}

it('sends a file inline, base64 encoded', function () {
    Http::fake(['*' => Http::response(attachmentResponse(), 201)]);

    $attachment = HelpDesk::attachments()->store(
        hydratedTicket(),
        UploadedFile::fake()->createWithContent('invoice.pdf', 'the file bytes'),
        apiUploader(),
    );

    expect($attachment)->toBeInstanceOf(ApiTicketAttachment::class)
        ->and($attachment->file_name)->toBe('invoice.pdf')
        ->and($attachment->uploader_name)->toBe('Ada Lovelace');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://support.example.com/help-desk/api/tickets/550e8400-e29b-41d4-a716-446655440000/attachments'
            && base64_decode($body['contents']) === 'the file bytes'
            && $body['actor']['name'] === 'Ada Lovelace';
    });
});

it('refuses an oversize file before sending it', function () {
    config()->set('help-desk.api.max_inline_attachment', 1);

    Http::fake();

    expect(fn () => HelpDesk::attachments()->store(
        hydratedTicket(),
        UploadedFile::fake()->createWithContent('invoice.pdf', str_repeat('x', 4096)),
        apiUploader(),
    ))->toThrow(HelpDeskApiException::class, 'capped at 1 KB');

    // Sending several megabytes for the server to refuse would be rude.
    Http::assertNothingSent();
});

it('fetches the bytes back for a satellite with no disk', function () {
    Http::fake(['*' => Http::response(['data' => attachmentResponse()['data'] + [
        'contents' => base64_encode('the file bytes'),
    ]], 200)]);

    test()->actingAs(apiUploader());

    $attachment = new ApiTicketAttachment;
    $attachment->forceFill(['uuid' => 'aaaaaaaa-e29b-41d4-a716-446655440000']);

    expect(HelpDesk::attachments()->contents($attachment, '550e8400-e29b-41d4-a716-446655440000'))
        ->toBe('the file bytes');
});

it('refuses to hand out a url it cannot honour', function () {
    $attachment = new ApiTicketAttachment;

    // A URL here would 404 for the satellite's users, which is worse than
    // admitting there is none.
    expect(fn () => $attachment->getUrl())
        ->toThrow(HelpDeskApiException::class, 'HelpDesk::attachments()->contents($attachment)');

    expect(fn () => $attachment->getTemporaryUrl())
        ->toThrow(HelpDeskApiException::class, 'no credentials for');
});

it('still refuses to delete an attachment', function () {
    Http::fake();

    expect(fn () => HelpDesk::attachments()->delete(new ApiTicketAttachment))
        ->toThrow(HelpDeskApiException::class, 'operator action');

    Http::assertNothingSent();
});

it('keeps the size and extension helpers usable on the satellite', function () {
    expect(HelpDesk::attachments()->isAllowedExtension('pdf'))->toBeTrue()
        ->and(HelpDesk::attachments()->isAllowedExtension('exe'))->toBeFalse()
        ->and(HelpDesk::attachments()->isWithinSizeLimit(999999))->toBeFalse();
});
