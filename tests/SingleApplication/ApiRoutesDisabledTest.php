<?php

use Illuminate\Support\Facades\Route;

it('registers no API routes when no client is configured', function () {
    expect(Route::has('help-desk.api.tickets.index'))->toBeFalse()
        ->and(Route::has('help-desk.api.tickets.store'))->toBeFalse()
        ->and(Route::has('help-desk.api.comments.store'))->toBeFalse()
        ->and(Route::has('help-desk.api.departments.index'))->toBeFalse();
});

it('serves a 404, not a 401, for an endpoint that was never registered', function () {
    // The difference matters: a 401 would tell a prober the surface exists.
    test()->postJson('/help-desk/api/tickets', [])->assertNotFound();
});
