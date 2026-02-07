<?php

use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;

it('has all priorities', function () {
    expect(TicketPriority::cases())->toHaveCount(4);
});

it('has correct values', function () {
    expect(TicketPriority::Low->value)->toBe('low')
        ->and(TicketPriority::Medium->value)->toBe('medium')
        ->and(TicketPriority::High->value)->toBe('high')
        ->and(TicketPriority::Urgent->value)->toBe('urgent');
});

it('has ascending numeric values', function () {
    expect(TicketPriority::Low->numericValue())->toBe(1)
        ->and(TicketPriority::Medium->numericValue())->toBe(2)
        ->and(TicketPriority::High->numericValue())->toBe(3)
        ->and(TicketPriority::Urgent->numericValue())->toBe(4);
});
