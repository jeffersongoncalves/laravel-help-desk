<?php

namespace JeffersonGoncalves\HelpDesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use JeffersonGoncalves\HelpDesk\Models\Category;
use JeffersonGoncalves\HelpDesk\Models\Department;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(2, true));

        return [
            'department_id' => Department::factory(),
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn () => [
            'department_id' => $parent->department_id,
            'parent_id' => $parent->id,
        ]);
    }
}
