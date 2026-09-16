<?php

namespace JeffersonGoncalves\HelpDesk\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Resolves the people attached to a ticket when their model may not exist here.
 *
 * When several applications share one help desk database, a ticket stores a
 * morph type that only the application it came from has installed. Touching
 * such a relation the normal way is fatal — Eloquent instantiates the stored
 * class name and PHP raises "Class not found" — so every read goes through
 * this trait, which falls back to the identity snapshot kept in `metadata`.
 */
trait ResolvesMorphedIdentity
{
    /**
     * Whether the stored morph type maps to a class this application has.
     */
    public static function morphIsResolvable(?string $type): bool
    {
        if ($type === null || $type === '') {
            return false;
        }

        return class_exists(Relation::getMorphedModel($type) ?? $type);
    }

    /**
     * Load a morphTo relation, or null when its class is not installed here.
     */
    protected function resolveMorphed(string $relation, string $typeAttribute): ?Model
    {
        if (! static::morphIsResolvable($this->getAttribute($typeAttribute))) {
            return null;
        }

        /** @var Model|null $model */
        $model = $this->getRelationValue($relation);

        return $model;
    }

    /**
     * Read a display field from the live model, falling back to the snapshot.
     */
    protected function snapshotField(string $relation, string $typeAttribute, string $metadataKey, string $field): ?string
    {
        $value = $this->resolveMorphed($relation, $typeAttribute)?->getAttribute($field)
            ?? data_get($this->metadata, "{$metadataKey}.{$field}");

        return filled($value) ? (string) $value : null;
    }

    /**
     * The fields copied onto the record so another application can still show
     * who this is. A model may replace them with `toHelpDeskSnapshot()`.
     *
     * @return array<string, string>
     */
    public static function snapshotOf(Model $model): array
    {
        if (method_exists($model, 'toHelpDeskSnapshot')) {
            return $model->toHelpDeskSnapshot();
        }

        return array_filter([
            'name' => $model->getAttribute('name'),
            'email' => $model->getAttribute('email'),
        ], fn ($value) => filled($value));
    }
}
