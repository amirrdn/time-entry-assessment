<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'project_id' => Project::factory(),
            'task_id' => Task::factory(),
            'date' => fake()->dateTimeBetween(now()->startOfMonth(), now())->format('Y-m-d'),
            'hours' => fake()->randomFloat(2, 1, 8),
        ];
    }
}
