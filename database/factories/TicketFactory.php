<?php

namespace JeffersonGoncalves\HelpDesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Enums\TicketPriority;
use JeffersonGoncalves\HelpDesk\Enums\TicketStatus;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $user = $this->makeUser('user');

        return [
            'department_id' => Department::factory(),
            'category_id' => null,
            'user_type' => $user->getMorphClass(),
            'user_id' => $user->getKey(),
            'assigned_to_type' => null,
            'assigned_to_id' => null,
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Medium,
            'source' => 'web',
        ];
    }

    public function forUser(Model $user): static
    {
        return $this->state(fn () => [
            'user_type' => $user->getMorphClass(),
            'user_id' => $user->getKey(),
        ]);
    }

    public function assignedTo(Model $operator): static
    {
        return $this->state(fn () => [
            'assigned_to_type' => $operator->getMorphClass(),
            'assigned_to_id' => $operator->getKey(),
        ]);
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    /**
     * Create an instance of the configured user/operator model.
     */
    protected function makeUser(string $type): Model
    {
        $class = config("help-desk.models.{$type}", User::class);

        /** @var Model $model */
        $model = $class::query()->create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ]);

        return $model;
    }
}
