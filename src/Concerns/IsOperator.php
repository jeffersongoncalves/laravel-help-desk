<?php

namespace JeffersonGoncalves\HelpDesk\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use JeffersonGoncalves\HelpDesk\Models\Department;
use JeffersonGoncalves\HelpDesk\Models\Ticket;
use JeffersonGoncalves\HelpDesk\Models\TicketHistory;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $helpDeskAssignedTickets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Department> $helpDeskDepartments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketHistory> $helpDeskHistory
 */
trait IsOperator
{
    use HasTickets;

    /** @return MorphMany<Ticket, $this> */
    public function helpDeskAssignedTickets(): MorphMany
    {
        return $this->morphMany(Ticket::class, 'assigned_to');
    }

    /** @return MorphToMany<Department, $this> */
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

    /** @return MorphMany<TicketHistory, $this> */
    public function helpDeskHistory(): MorphMany
    {
        return $this->morphMany(TicketHistory::class, 'performer');
    }
}
