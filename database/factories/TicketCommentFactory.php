<?php

namespace JeffersonGoncalves\HelpDesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Enums\CommentType;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketComment;

/**
 * @extends Factory<TicketComment>
 */
class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

    public function definition(): array
    {
        $author = $this->makeOperator();

        return [
            'ticket_id' => Ticket::factory(),
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
            'body' => fake()->paragraph(),
            'type' => CommentType::Reply,
            'is_internal' => false,
            'email_message_id' => null,
            'metadata' => null,
        ];
    }

    public function by(Model $author): static
    {
        return $this->state(fn () => [
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
        ]);
    }

    public function note(): static
    {
        return $this->state(fn () => [
            'type' => CommentType::Note,
            'is_internal' => true,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'type' => CommentType::System,
            'author_type' => null,
            'author_id' => null,
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn () => ['is_internal' => true]);
    }

    protected function makeOperator(): Model
    {
        $class = config('help-desk.models.operator', User::class);

        /** @var Model $model */
        $model = $class::query()->create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ]);

        return $model;
    }
}
