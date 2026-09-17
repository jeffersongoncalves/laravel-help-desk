<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\HelpDesk\Models\Department;

/**
 * Where departments and their operators live. See TicketRepository for why
 * "repository".
 */
interface DepartmentRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department;

    public function delete(Department $department): bool;

    /**
     * @param  string  $role  'operator', 'manager' or 'admin'
     */
    public function addOperator(Department $department, Model $operator, string $role = 'operator'): void;

    public function removeOperator(Department $department, Model $operator): void;
}
