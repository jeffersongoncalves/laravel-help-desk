<?php

namespace JeffersonGoncalves\HelpDesk\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use JeffersonGoncalves\HelpDesk\Concerns\ResolvesMorphedIdentity;
use JeffersonGoncalves\HelpDesk\Concerns\UsesHelpDeskConnection;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $rating
 * @property string|null $comment
 * @property string $submitted_by_type
 * @property int $submitted_by_id
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property-read Ticket $ticket
 * @property-read Model|null $submittedBy
 * @property-read string|null $submitter_name
 * @property-read string|null $submitter_email
 */
class TicketFeedback extends Model
{
    use ResolvesMorphedIdentity, UsesHelpDeskConnection;

    public $timestamps = false;

    protected $table = 'help_desk_ticket_feedback';

    protected $fillable = [
        'ticket_id',
        'rating',
        'comment',
        'submitted_by_type',
        'submitted_by_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketFeedback $feedback) {
            if (empty($feedback->created_at)) {
                $feedback->created_at = now();
            }
        });
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function submittedBy(): MorphTo
    {
        return $this->morphTo('submittedBy');
    }

    /**
     * The submitter, or null when their model is not installed here.
     */
    public function resolvedSubmittedBy(): ?Model
    {
        return $this->resolveMorphed('submittedBy', 'submitted_by_type');
    }

    protected function submitterName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshotField('submittedBy', 'submitted_by_type', 'submitter', 'name'));
    }

    protected function submitterEmail(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshotField('submittedBy', 'submitted_by_type', 'submitter', 'email'));
    }
}
