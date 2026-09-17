<?php

use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\HelpDesk\Http\Controllers\Api\AttachmentController;
use JeffersonGoncalves\HelpDesk\Http\Controllers\Api\CommentController;
use JeffersonGoncalves\HelpDesk\Http\Controllers\Api\DepartmentController;
use JeffersonGoncalves\HelpDesk\Http\Controllers\Api\TicketController;
use JeffersonGoncalves\HelpDesk\Http\Middleware\VerifyHelpDeskSignature;

/*
 * The end-user surface a satellite application needs, and nothing more.
 * Operator and admin actions stay on the database side, in the central
 * application.
 *
 * Registered only when at least one API client is configured, so a single
 * application installation exposes no endpoints at all.
 */
Route::prefix(config('help-desk.api.prefix', 'help-desk/api'))
    ->middleware(array_merge(
        config('help-desk.api.middleware', ['throttle:60,1']),
        [VerifyHelpDeskSignature::class],
    ))
    ->group(function () {
        Route::get('tickets', [TicketController::class, 'index'])->name('help-desk.api.tickets.index');
        Route::post('tickets', [TicketController::class, 'store'])->name('help-desk.api.tickets.store');
        Route::get('tickets/{uuid}', [TicketController::class, 'show'])->name('help-desk.api.tickets.show');

        // Closing and reopening only. Every other status is an operator
        // decision and has no endpoint.
        Route::post('tickets/{uuid}/status', [TicketController::class, 'status'])->name('help-desk.api.tickets.status');

        Route::post('tickets/{uuid}/comments', [CommentController::class, 'store'])->name('help-desk.api.comments.store');

        Route::post('tickets/{uuid}/attachments', [AttachmentController::class, 'store'])->name('help-desk.api.attachments.store');
        Route::get('tickets/{uuid}/attachments/{attachment}', [AttachmentController::class, 'show'])->name('help-desk.api.attachments.show');

        Route::get('departments', [DepartmentController::class, 'index'])->name('help-desk.api.departments.index');
        Route::get('departments/{department}/categories', [DepartmentController::class, 'categories'])->name('help-desk.api.departments.categories');
    });
