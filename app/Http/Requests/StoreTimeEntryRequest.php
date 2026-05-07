<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.company_id' => ['required', 'integer', 'exists:companies,id'],
            'entries.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'entries.*.project_id' => ['required', 'integer', 'exists:projects,id'],
            'entries.*.task_id' => ['required', 'integer', 'exists:tasks,id'],
            'entries.*.date' => ['required', 'date'],
            'entries.*.hours' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $entries = collect($this->input('entries', []));

            if ($entries->isEmpty()) {
                return;
            }

            $employeeNames = Employee::query()
                ->whereIn('id', $entries->pluck('employee_id')->filter()->unique()->values())
                ->pluck('name', 'id');

            $companyIds = $entries->pluck('company_id')->filter()->unique()->values();
            $employeeIds = $entries->pluck('employee_id')->filter()->unique()->values();
            $projectIds = $entries->pluck('project_id')->filter()->unique()->values();
            $taskIds = $entries->pluck('task_id')->filter()->unique()->values();

            $projectCompanyMap = Project::query()
                ->whereIn('id', $projectIds)
                ->pluck('company_id', 'id');

            $taskCompanyMap = Task::query()
                ->whereIn('id', $taskIds)
                ->pluck('company_id', 'id');

            $employeeCompanyPairs = DB::table('company_employee')
                ->select('employee_id', 'company_id')
                ->whereIn('employee_id', $employeeIds)
                ->whereIn('company_id', $companyIds)
                ->get()
                ->map(fn (object $pair): string => "{$pair->employee_id}|{$pair->company_id}")
                ->flip();

            $employeeProjectPairs = DB::table('employee_project')
                ->select('employee_id', 'project_id')
                ->whereIn('employee_id', $employeeIds)
                ->whereIn('project_id', $projectIds)
                ->get()
                ->map(fn (object $pair): string => "{$pair->employee_id}|{$pair->project_id}")
                ->flip();

            $getEmployeeLabel = function (int $employeeId) use ($employeeNames): string {
                return $employeeNames->get($employeeId, "Employee #{$employeeId}");
            };

            foreach ($entries as $rowIndex => $entry) {
                $companyId = (int) $entry['company_id'];
                $employeeId = (int) $entry['employee_id'];
                $projectId = (int) $entry['project_id'];
                $taskId = (int) $entry['task_id'];

                if ((int) $projectCompanyMap->get($projectId) !== $companyId) {
                    $validator->errors()->add(
                        "entries.{$rowIndex}.project_id",
                        'Selected project does not belong to the selected company.'
                    );
                }

                if ((int) $taskCompanyMap->get($taskId) !== $companyId) {
                    $validator->errors()->add(
                        "entries.{$rowIndex}.task_id",
                        'Selected task does not belong to the selected company.'
                    );
                }

                if (! $employeeCompanyPairs->has("{$employeeId}|{$companyId}")) {
                    $validator->errors()->add(
                        "entries.{$rowIndex}.employee_id",
                        'Selected employee does not belong to the selected company.'
                    );
                }

                if (! $employeeProjectPairs->has("{$employeeId}|{$projectId}")) {
                    $validator->errors()->add(
                        "entries.{$rowIndex}.project_id",
                        'Selected employee is not assigned to the selected project.'
                    );
                }
            }

            $grouped = $entries->groupBy(fn (array $entry): string => "{$entry['employee_id']}|{$entry['date']}");

            foreach ($grouped as $groupKey => $groupEntries) {
                $projectCount = $groupEntries->pluck('project_id')->unique()->count();

                if ($projectCount <= 1) {
                    continue;
                }

                [$employeeId, $date] = explode('|', (string) $groupKey);
                $employeeLabel = $getEmployeeLabel((int) $employeeId);
                foreach ($groupEntries as $rowIndex => $rowEntry) {
                    $validator->errors()->add(
                        "entries.{$rowIndex}.project_id",
                        "{$employeeLabel} has multiple projects on {$date} in this request."
                    );
                }
            }

            $validPairs = $entries
                ->filter(
                    fn (array $entry): bool => isset($entry['employee_id'], $entry['date'], $entry['project_id'])
                )
                ->map(
                    fn (array $entry): array => [
                        'employee_id' => (int) $entry['employee_id'],
                        'date' => (string) $entry['date'],
                        'project_id' => (int) $entry['project_id'],
                    ]
                )
                ->unique(fn (array $entry): string => "{$entry['employee_id']}|{$entry['date']}|{$entry['project_id']}")
                ->values();

            if ($validPairs->isEmpty()) {
                return;
            }

            $employeeDatePairs = $validPairs
                ->map(fn (array $entry): string => "{$entry['employee_id']}|{$entry['date']}")
                ->unique()
                ->values();

            $existingRows = TimeEntry::query()
                ->select('employee_id', 'date', 'project_id')
                ->where(function ($query) use ($employeeDatePairs): void {
                    foreach ($employeeDatePairs as $pair) {
                        [$employeeId, $date] = explode('|', (string) $pair);
                        $query->orWhere(function ($subQuery) use ($employeeId, $date): void {
                            $subQuery
                                ->where('employee_id', (int) $employeeId)
                                ->whereDate('date', (string) $date);
                        });
                    }
                })
                ->get()
                ->groupBy(fn (TimeEntry $entry): string => "{$entry->employee_id}|{$entry->date->format('Y-m-d')}");

            foreach ($validPairs as $entry) {
                $pairKey = "{$entry['employee_id']}|{$entry['date']}";
                $existingProjects = $existingRows->get($pairKey);

                if (! $existingProjects || $existingProjects->isEmpty()) {
                    continue;
                }

                $hasDifferentProject = $existingProjects
                    ->pluck('project_id')
                    ->contains(fn (int $projectId): bool => $projectId !== $entry['project_id']);

                if ($hasDifferentProject) {
                    $offendingRowIndexes = $entries
                        ->filter(
                            fn (array $row): bool => (int) $row['employee_id'] === $entry['employee_id']
                                && (string) $row['date'] === $entry['date']
                        )
                        ->keys();

                    foreach ($offendingRowIndexes as $rowIndex) {
                        $employeeLabel = $getEmployeeLabel($entry['employee_id']);
                        $validator->errors()->add(
                            "entries.{$rowIndex}.project_id",
                            "{$employeeLabel} already has another project on {$entry['date']}."
                        );
                    }
                }
            }
        });
    }
}
