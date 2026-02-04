<?php

namespace JeffersonGoncalves\HelpDesk\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketHistory;

trait IsOperator
{
    use HasTickets;

    public function helpDeskAssignedTickets(): MorphMany
    {
        return $this->morphMany(Ticket::class, 'assigned_to');
    }

    public function helpDeskDepartments(): MorphToMany
    {
        return $this->morphToMany(
            Department::class,
            'operator',
            'help_desk_department_operator',
            null,
            'department_id'
        )->withPivot('role')->withTimestamps();
    }

    public function helpDeskHistory(): MorphMany
    {
        return $this->morphMany(TicketHistory::class, 'performer');
    }
}
