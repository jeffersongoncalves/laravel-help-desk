<?php

use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskSigner;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;

const UPLOADER = ['type' => 'app-a-user', 'id' => 5, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'];

beforeEach(fn () => Storage::fake('local'));

function callAttachmentApi(string $method, string $path, array $payload = [])
{
    $body = $payload === [] ? '' : json_encode($payload);
    $headers = (new HelpDeskSigner)->headersFor($method, $path, $body, 'app-a', 'app-a-secret');

    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.str_replace('-', '_', strtoupper($name))] = $value;
    }

    return test()->call($method, $path, [], [], [], $server, $body);
}

function attachableTicket(): Ticket
{
    return Ticket::create([
        'department_id' => Department::factory()->create()->id,
        'user_type' => UPLOADER['type'],
        'user_id' => UPLOADER['id'],
        'app_key' => 'app-a',
        'title' => 'Printer offline',
        'description' => 'It stopped printing.',
    ]);
}

it('stores an attachment sent inline, with the uploader snapshot', function () {
    $ticket = attachableTicket();

    $response = callAttachmentApi('POST', "/help-desk/api/tickets/{$ticket->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'contents' => base64_encode('the file bytes'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.file_name', 'invoice.pdf')
        ->assertJsonPath('data.uploader_name', 'Ada Lovelace');

    $attachment = TicketAttachment::firstOrFail();

    expect($attachment->ticket_id)->toBe($ticket->id)
        ->and($attachment->metadata['uploader'])->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
        ->and(Storage::disk('local')->get($attachment->file_path))->toBe('the file bytes');
});

it('never publishes where the file lives', function () {
    $ticket = attachableTicket();

    $response = callAttachmentApi('POST', "/help-desk/api/tickets/{$ticket->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'contents' => base64_encode('the file bytes'),
    ]);

    // The disk and path are the central application's business, and a URL
    // would be one that 404s for the satellite's users.
    expect(array_keys($response->json('data')))
        ->toBe(['uuid', 'comment_id', 'file_name', 'mime_type', 'file_size', 'uploader_name', 'created_at']);
});

it('rejects a disallowed extension server-side', function () {
    $ticket = attachableTicket();

    callAttachmentApi('POST', "/help-desk/api/tickets/{$ticket->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'payload.exe',
        'mime_type' => 'application/octet-stream',
        'contents' => base64_encode('MZ'),
    ])->assertStatus(422)->assertJsonValidationErrors('file_name');

    expect(TicketAttachment::count())->toBe(0);
});

it('rejects a file over the inline cap server-side', function () {
    config()->set('help-desk.api.max_inline_attachment', 1);

    $ticket = attachableTicket();

    callAttachmentApi('POST', "/help-desk/api/tickets/{$ticket->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'contents' => base64_encode(str_repeat('x', 4096)),
    ])->assertStatus(422)->assertJsonValidationErrors('contents');

    expect(TicketAttachment::count())->toBe(0);
});

it('rejects contents that are not valid base64', function () {
    $ticket = attachableTicket();

    callAttachmentApi('POST', "/help-desk/api/tickets/{$ticket->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'contents' => 'not base64 !!!',
    ])->assertStatus(422)->assertJsonValidationErrors('contents');
});

it('refuses to attach to another application ticket', function () {
    $ticket = attachableTicket();
    $ticket->update(['app_key' => 'app-b']);

    callAttachmentApi('POST', "/help-desk/api/tickets/{$ticket->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'contents' => base64_encode('bytes'),
    ])->assertNotFound();
});

it('serves the bytes back so a satellite can show the file', function () {
    $ticket = attachableTicket();

    callAttachmentApi('POST', "/help-desk/api/tickets/{$ticket->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'contents' => base64_encode('the file bytes'),
    ])->assertCreated();

    $attachment = TicketAttachment::firstOrFail();

    $response = callAttachmentApi(
        'GET',
        "/help-desk/api/tickets/{$ticket->uuid}/attachments/{$attachment->uuid}?".http_build_query(['actor' => UPLOADER]),
    );

    $response->assertOk();

    expect(base64_decode($response->json('data.contents')))->toBe('the file bytes');
});

it('refuses to serve an attachment from another ticket', function () {
    $mine = attachableTicket();
    $theirs = attachableTicket();

    callAttachmentApi('POST', "/help-desk/api/tickets/{$theirs->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'contents' => base64_encode('secret'),
    ])->assertCreated();

    $attachment = TicketAttachment::firstOrFail();

    callAttachmentApi(
        'GET',
        "/help-desk/api/tickets/{$mine->uuid}/attachments/{$attachment->uuid}?".http_build_query(['actor' => UPLOADER]),
    )->assertNotFound();
});

it('ignores a comment id belonging to another ticket', function () {
    $mine = attachableTicket();
    $theirs = attachableTicket();

    $comment = $theirs->comments()->create(['body' => 'elsewhere', 'type' => 'reply']);

    callAttachmentApi('POST', "/help-desk/api/tickets/{$mine->uuid}/attachments", [
        'actor' => UPLOADER,
        'file_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'contents' => base64_encode('bytes'),
        'comment_id' => $comment->id,
    ])->assertCreated();

    expect(TicketAttachment::firstOrFail()->comment_id)->toBeNull();
});
