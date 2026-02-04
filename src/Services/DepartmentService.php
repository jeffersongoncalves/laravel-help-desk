<?php

namespace JeffersonGoncalves\HelpDesk\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Models\Department;

class DepartmentService
{
    public function create(array $data): Department
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return Department::create($data);
    }

    public function update(Department $department, array $data): Department
    {
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $department->update($data);

        return $department->fresh();
    }

    public function delete(Department $department): bool
    {
        return $department->delete();
    }

    public function addOperator(Department $department, Model $operator, string $role = 'operator'): void
    {
        $department->getConnection()
            ->table('help_desk_department_operator')
            ->updateOrInsert(
                [
                    'department_id' => $department->id,
                    'operator_type' => $operator->getMorphClass(),
                    'operator_id' => $operator->getKey(),
                ],
                [
                    'role' => $role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
    }

    public function removeOperator(Department $department, Model $operator): void
    {
        $department->getConnection()
            ->table('help_desk_department_operator')
            ->where('department_id', $department->id)
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->delete();
    }

    public function getOperators(Department $department)
    {
        return $department->getConnection()
            ->table('help_desk_department_operator')
            ->where('department_id', $department->id)
            ->get();
    }
}
