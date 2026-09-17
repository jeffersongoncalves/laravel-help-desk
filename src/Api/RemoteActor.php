<?php

namespace JeffersonGoncalves\HelpDesk\Api;

use Illuminate\Database\Eloquent\Model;

/**
 * The person a satellite application is acting on behalf of.
 *
 * The services take a Model for the actor, and over the API there is no model
 * to take — the satellite's user table is somewhere this application cannot
 * reach. This stands in for one: it answers the three questions the services
 * actually ask of an actor, and nothing else.
 *
 * It is never saved, never queried, and has no table. Touching it as an
 * Eloquent model beyond those three answers is a bug.
 *
 * No constructor: Eloquent instantiates a model with no arguments while
 * booting it, so state arrives through the factory below instead.
 */
class RemoteActor extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected string $actorType = '';

    protected int|string $actorId = 0;

    protected ?string $actorName = null;

    protected ?string $actorEmail = null;

    /**
     * @param  array{type: string, id: int|string, name?: string|null, email?: string|null}  $actor
     */
    public static function fromArray(array $actor): self
    {
        $instance = new self;

        $instance->actorType = $actor['type'];
        $instance->actorId = $actor['id'];
        $instance->actorName = $actor['name'] ?? null;
        $instance->actorEmail = $actor['email'] ?? null;

        return $instance;
    }

    /**
     * What lands in user_type, author_type, uploaded_by_type and the rest.
     */
    public function getMorphClass(): string
    {
        return $this->actorType;
    }

    public function getKey(): int|string
    {
        return $this->actorId;
    }

    /**
     * The identity snapshot, which is the whole reason the central application
     * can name someone whose model it does not have.
     *
     * @return array<string, string>
     */
    public function toHelpDeskSnapshot(): array
    {
        return array_filter([
            'name' => $this->actorName,
            'email' => $this->actorEmail,
        ], fn ($value) => filled($value));
    }

    /**
     * Saving one would mean writing a satellite's user into this application's
     * database, which is exactly what the API transport exists to avoid.
     */
    public function save(array $options = []): bool
    {
        return false;
    }
}
