<?php

namespace JeffersonGoncalves\HelpDesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\EmailChannel;

/**
 * @extends Factory<EmailChannel>
 */
class EmailChannelFactory extends Factory
{
    protected $model = EmailChannel::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->company().' Support',
            'driver' => 'mailgun',
            'email_address' => fake()->unique()->safeEmail(),
            'settings' => [],
            'is_active' => true,
            'last_polled_at' => null,
            'last_error' => null,
        ];
    }

    public function driver(string $driver): static
    {
        return $this->state(fn () => ['driver' => $driver]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
