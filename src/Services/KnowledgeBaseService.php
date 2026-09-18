<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use JeffersonGoncalves\HelpDesk\Models\KbArticle;

class KnowledgeBaseService
{
    /**
     * Published articles whose title or body match the term, same
     * case-insensitive/escaped-LIKE approach as Ticket::scopeSearch().
     *
     * @return Collection<int, KbArticle>
     */
    public function search(string $term, ?int $departmentId = null): Collection
    {
        // See Ticket::scopeSearch() for why '!' is the escape character and
        // LOWER() wraps both sides: LIKE is case-sensitive on Postgres, and a
        // backslash escape literal is spelled differently on MySQL.
        $escaped = '%'.mb_strtolower(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term)).'%';

        return KbArticle::query()
            ->where('is_published', true)
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->where(fn (Builder $query) => $query
                ->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$escaped])
                ->orWhereRaw("LOWER(body) LIKE ? ESCAPE '!'", [$escaped]))
            ->get();
    }

    public function recordView(KbArticle $article): void
    {
        $article->increment('views_count');
    }
}
