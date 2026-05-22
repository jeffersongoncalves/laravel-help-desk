<?php

namespace JeffersonGoncalves\HelpDesk\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model): void {
            if (empty($model->slug)) {
                $model->slug = $model->generateSlug();
            }
        });
    }

    /**
     * Attribute used as the source for the slug.
     */
    public function getSlugSource(): string
    {
        return 'name';
    }

    /**
     * Generate a unique slug from the source attribute.
     */
    protected function generateSlug(): string
    {
        $base = Str::slug((string) $this->getAttribute($this->getSlugSource()));

        if ($base === '') {
            $base = Str::random(8);
        }

        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            /** @phpstan-ignore-next-line method.notFound */
            $query->withTrashed();
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->exists();
    }
}
