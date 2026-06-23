<?php

namespace JeffersonGoncalves\HelpDesk\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketAttachment;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

    public function definition(): array
    {
        $uploadedBy = $this->makeOperator();
        $fileName = fake()->word().'.pdf';

        return [
            'uuid' => (string) Str::uuid(),
            'ticket_id' => Ticket::factory(),
            'comment_id' => null,
            'uploaded_by_type' => $uploadedBy->getMorphClass(),
            'uploaded_by_id' => $uploadedBy->getKey(),
            'file_name' => $fileName,
            'file_path' => 'help-desk/attachments/'.Str::uuid().'/'.$fileName,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 1048576),
            'metadata' => null,
        ];
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
