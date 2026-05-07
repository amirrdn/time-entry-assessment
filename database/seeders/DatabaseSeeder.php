<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $companyNames = [
            'Tech Corp',
            'Creative Agency',
            'Factory Inc',
        ];

        $taskNames = [
            'Planning',
            'Development',
            'Review',
            'Testing',
            'Documentation',
        ];

        $projectNames = [
            'Website Revamp',
            'Mobile App',
            'Internal Tooling',
        ];

        $companies = Company::factory()
            ->count(3)
            ->sequence(
                ...collect($companyNames)
                    ->map(fn (string $name): array => ['name' => $name])
                    ->all()
            )
            ->create();

        foreach ($companies as $company) {
            Task::factory()
                ->count(5)
                ->state(new Sequence(
                    ...collect($taskNames)
                        ->map(fn (string $name): array => ['name' => "{$company->name} - {$name}"])
                        ->all()
                ))
                ->create([
                    'company_id' => $company->id,
                ]);

            Project::factory()
                ->count(3)
                ->state(new Sequence(
                    ...collect($projectNames)
                        ->map(fn (string $name): array => ['name' => "{$company->name} - {$name}"])
                        ->all()
                ))
                ->create([
                    'company_id' => $company->id,
                ]);
        }

        $employees = Employee::factory()->count(10)->create();

        foreach ($employees as $index => $employee) {
            $primaryCompany = $companies[$index % $companies->count()];
            $attachedCompanyIds = [$primaryCompany->id];

            if ($index < 4) {
                $secondaryCompany = $companies[($index + 1) % $companies->count()];
                $attachedCompanyIds[] = $secondaryCompany->id;
            }

            $employee->companies()->sync(array_values(array_unique($attachedCompanyIds)));

            $projectIds = Project::query()
                ->whereIn('company_id', $attachedCompanyIds)
                ->pluck('id');

            $employee->projects()->sync(
                $projectIds
                    ->shuffle()
                    ->take(random_int(1, min(3, $projectIds->count())))
                    ->values()
                    ->all()
            );
        }

        $startOfMonth = Carbon::now()->startOfMonth();

        foreach ($employees as $employee) {
            $employeeProjects = $employee->projects()->with('company')->get();

            if ($employeeProjects->isEmpty()) {
                continue;
            }

            $availableDayOffsets = collect(range(0, max(0, now()->day - 1)))->shuffle()->take(3)->values();

            foreach ($availableDayOffsets as $dayOffset) {
                $project = $employeeProjects->random();
                $company = $project->company;
                $tasks = Task::query()
                    ->where('company_id', $company->id)
                    ->inRandomOrder()
                    ->limit(2)
                    ->get();

                if ($tasks->isEmpty()) {
                    continue;
                }

                $entryDate = $startOfMonth->copy()->addDays($dayOffset)->format('Y-m-d');

                foreach ($tasks as $task) {
                    TimeEntry::factory()->create([
                        'company_id' => $company->id,
                        'employee_id' => $employee->id,
                        'project_id' => $project->id,
                        'task_id' => $task->id,
                        'date' => $entryDate,
                        'hours' => random_int(2, 5),
                    ]);
                }
            }
        }

        // Guardrail for seed integrity: each employee can only have one project per date.
        $violations = DB::table('time_entries')
            ->select('employee_id', 'date')
            ->groupBy('employee_id', 'date')
            ->havingRaw('COUNT(DISTINCT project_id) > 1')
            ->count();

        if ($violations > 0) {
            throw new RuntimeException('Seed data violates one-project-per-date rule.');
        }
    }
}
