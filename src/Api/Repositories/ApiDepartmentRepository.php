<?php

namespace JeffersonGoncalves\HelpDesk\Api\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskClient;
use JeffersonGoncalves\HelpDesk\Contracts\DepartmentRepository;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Models\Department;

/**
 * Departments are read-only from a satellite: the central application decides
 * what exists and which are open for business.
 */
class ApiDepartmentRepository implements DepartmentRepository
{
    public function __construct(protected HelpDeskClient $client) {}

    /**
     * The options a create form offers. Not on the contract for the same
     * reason listing tickets is not: the database driver uses Eloquent.
     *
     * @return Collection<int, Department>
     */
    public function all(): Collection
    {
        return collect($this->client->get('departments')['data'] ?? [])->map(
            fn (array $department) => tap(new Department, function (Department $model) use ($department) {
                $model->forceFill($department);
                $model->exists = true;
                $model->syncOriginal();
            }),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function categoriesFor(int $departmentId): Collection
    {
        return collect($this->client->get("departments/{$departmentId}/categories")['data'] ?? []);
    }

    public function create(array $data): Department
    {
        throw HelpDeskApiException::operatorOnly('createDepartment()');
    }

    public function update(Department $department, array $data): Department
    {
        throw HelpDeskApiException::operatorOnly('updateDepartment()');
    }

    public function delete(Department $department): bool
    {
        throw HelpDeskApiException::operatorOnly('deleting a department');
    }

    public function addOperator(Department $department, Model $operator, string $role = 'operator'): void
    {
        throw HelpDeskApiException::operatorOnly('addOperator()');
    }

    public function removeOperator(Department $department, Model $operator): void
    {
        throw HelpDeskApiException::operatorOnly('removeOperator()');
    }
}
