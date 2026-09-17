<?php

namespace JeffersonGoncalves\HelpDesk\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;

/**
 * Where departments and their operators live. See TicketRepository for why
 * "repository".
 */
interface DepartmentRepository
{
    /**
     * The departments a create form may offer: active only, in sort order.
     *
     * Not paginated, unlike tickets — this is a select's options, and a list
     * long enough to need pages is a different feature.
     *
     * @return Collection<int, Department>
     */
    public function all(): Collection;

    /**
     * The categories of one department, on the same terms.
     *
     * @return Collection<int, Category>
     */
    public function categoriesFor(int $departmentId): Collection;

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
