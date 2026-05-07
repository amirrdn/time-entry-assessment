<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function getMetaData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $companyId = $validated['company_id'] ?? null;

        $companiesQuery = Company::query()->select('id', 'name');
        $employeesQuery = Employee::query()->select('employees.id', 'employees.name');
        $projectsQuery = Project::query()->select('id', 'company_id', 'name');
        $tasksQuery = Task::query()->select('id', 'company_id', 'name');

        if ($companyId) {
            $employeesQuery->whereHas('companies', function ($query) use ($companyId): void {
                $query->where('companies.id', $companyId);
            });

            $projectsQuery->where('company_id', $companyId);
            $tasksQuery->where('company_id', $companyId);
        }

        return response()->json([
            'data' => [
                'companies' => $companiesQuery->orderBy('name')->get(),
                'employees' => $employeesQuery->orderBy('name')->get(),
                'projects' => $projectsQuery->orderBy('name')->get(),
                'tasks' => $tasksQuery->orderBy('name')->get(),
            ],
            'filters' => [
                'company_id' => $companyId,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $companyId = $validated['company_id'] ?? null;

        $timeEntriesQuery = TimeEntry::query()
            ->with(['company', 'employee', 'project', 'task'])
            ->latest('date')
            ->latest('id');

        if ($companyId) {
            $timeEntriesQuery->where('company_id', $companyId);
        }

        $timeEntries = $timeEntriesQuery->get();

        return TimeEntryResource::collection($timeEntries);
    }

    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $entries = collect($request->validated('entries'))
            ->map(function (array $entry): array {
                return [
                    'company_id' => $entry['company_id'],
                    'employee_id' => $entry['employee_id'],
                    'project_id' => $entry['project_id'],
                    'task_id' => $entry['task_id'],
                    'date' => $entry['date'],
                    'hours' => $entry['hours'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values();

        TimeEntry::query()->insert($entries->all());

        return response()->json([
            'message' => 'Time entries created successfully.',
            'count' => $entries->count(),
        ], 201);
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $timeEntry->update($request->validated());
        $timeEntry->load(['company', 'employee', 'project', 'task']);

        return response()->json([
            'message' => 'Time entry updated successfully.',
            'data' => TimeEntryResource::make($timeEntry),
        ]);
    }
}
