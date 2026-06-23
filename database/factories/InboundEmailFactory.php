<?php

namespace JeffersonGoncalves\HelpDesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Models\InboundEmail;

/**
 * @extends Factory<InboundEmail>
 */
class InboundEmailFactory extends Factory
{
    protected $model = InboundEmail::class;

    public function definition(): array
    {
        return [
            'email_channel_id' => null,
            'message_id' => '<'.Str::uuid().'@example.com>',
            'in_reply_to' => null,
            'references' => null,
            'from_address' => fake()->unique()->safeEmail(),
            'from_name' => fake()->name(),
            'to_addresses' => [fake()->safeEmail()],
            'cc_addresses' => null,
            'subject' => fake()->sentence(),
            'text_body' => fake()->paragraph(),
            'html_body' => null,
            'raw_payload' => null,
            'ticket_id' => null,
            'comment_id' => null,
            'status' => 'pending',
            'error_message' => null,
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }

    public function ignored(): static
    {
        return $this->state(fn () => [
            'status' => 'ignored',
            'processed_at' => now(),
        ]);
    }
}
