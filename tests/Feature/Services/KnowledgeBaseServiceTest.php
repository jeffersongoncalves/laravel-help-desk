<?php

use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\KbArticle;
use JeffersonGoncalves\HelpDesk\Services\KnowledgeBaseService;

beforeEach(function () {
    $this->service = app(KnowledgeBaseService::class);
    $this->department = Department::create(['name' => 'Support', 'slug' => 'support', 'is_active' => true]);
});

function makeKbArticle(array $overrides = []): KbArticle
{
    return KbArticle::create(array_merge([
        'title' => 'How to reset your password',
        'body' => 'Go to settings and click reset.',
        'is_published' => true,
    ], $overrides));
}

it('returns only published articles', function () {
    $published = makeKbArticle();
    makeKbArticle(['title' => 'Draft article', 'is_published' => false]);

    $results = $this->service->search('password');

    expect($results)->toHaveCount(1)
        ->and($results->first()->is($published))->toBeTrue();
});

it('matches the term against the title', function () {
    makeKbArticle(['title' => 'Billing FAQ', 'body' => 'Nothing relevant here.']);

    $results = $this->service->search('billing');

    expect($results)->toHaveCount(1);
});

it('matches the term against the body', function () {
    makeKbArticle(['title' => 'Unrelated title', 'body' => 'This covers invoice refunds.']);

    $results = $this->service->search('refund');

    expect($results)->toHaveCount(1);
});

it('filters by department when given', function () {
    $otherDepartment = Department::create(['name' => 'Sales', 'slug' => 'sales', 'is_active' => true]);
    makeKbArticle(['title' => 'Password reset', 'department_id' => $this->department->id]);
    makeKbArticle(['title' => 'Password policy', 'department_id' => $otherDepartment->id]);

    $results = $this->service->search('password', $this->department->id);

    expect($results)->toHaveCount(1);
});

it('does not filter by department when none is given', function () {
    $otherDepartment = Department::create(['name' => 'Sales', 'slug' => 'sales', 'is_active' => true]);
    makeKbArticle(['title' => 'Password reset', 'department_id' => $this->department->id]);
    makeKbArticle(['title' => 'Password policy', 'department_id' => $otherDepartment->id]);

    $results = $this->service->search('password');

    expect($results)->toHaveCount(2);
});

it('treats a wildcard in the search term as a literal character', function () {
    makeKbArticle(['title' => '100% uptime guarantee']);
    makeKbArticle(['title' => '100X uptime guarantee']);

    $results = $this->service->search('100%');

    expect($results)->toHaveCount(1);
});

it('is scoped by app_key the same way tickets are', function () {
    config()->set('help-desk.app.key', 'app-a');
    $ownArticle = makeKbArticle(['title' => 'Password reset for app-a']);

    config()->set('help-desk.app.key', 'app-b');
    $foreignArticle = makeKbArticle(['title' => 'Password reset for app-b']);

    expect($foreignArticle->app_key)->toBe('app-b');

    config()->set('help-desk.app.key', 'app-a');
    config()->set('help-desk.scope_to_app', true);

    $results = $this->service->search('password');

    expect($results)->toHaveCount(1)
        ->and($results->first()->is($ownArticle))->toBeTrue();
});

it('stamps app_key from config on creation, matching the Ticket pattern', function () {
    config()->set('help-desk.app.key', 'app-a');

    $article = makeKbArticle();

    expect($article->app_key)->toBe('app-a');
});

it('increments views_count by exactly 1 per call', function () {
    $article = makeKbArticle();

    $this->service->recordView($article);
    $this->service->recordView($article->fresh());

    expect($article->fresh()->views_count)->toBe(2);
});
