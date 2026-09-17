<?php

namespace JeffersonGoncalves\HelpDesk\Api\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JeffersonGoncalves\HelpDesk\Api\HelpDeskClient;
use JeffersonGoncalves\HelpDesk\Contracts\DepartmentRepository;
use JeffersonGoncalves\HelpDesk\Exceptions\HelpDeskApiException;
use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;

/**
 * Departments are read-only from a satellite: the central application decides
 * what exists and which are open for business.
 */
class ApiDepartmentRepository implements DepartmentRepository
{
    public function __construct(protected HelpDeskClient $client) {}

    /**
     * @return Collection<int, Department>
     */
    public function all(): Collection
    {
        return collect($this->client->get('departments')['data'] ?? [])->map(
            fn (array $department) => $this->asModel(new Department, $department),
        );
    }

    /**
     * Models rather than the raw arrays this used to return. A contract that
     * hands back a Category on one transport and an array on the other is the
     * branch it exists to remove.
     *
     * @return Collection<int, Category>
     */
    public function categoriesFor(int $departmentId): Collection
    {
        return collect($this->client->get("departments/{$departmentId}/categories")['data'] ?? [])->map(
            fn (array $category) => $this->asModel(new Category, $category),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $payload
     * @return TModel
     */
    protected function asModel(Model $model, array $payload): Model
    {
        $model->forceFill($payload);
        $model->exists = true;
        $model->syncOriginal();

        return $model;
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
