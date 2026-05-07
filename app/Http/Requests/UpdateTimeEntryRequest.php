<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class UpdateTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $entryId = (int) $this->route('timeEntry')->id;
            $companyId = (int) $this->input('company_id');
            $employeeId = (int) $this->input('employee_id');
            $projectId = (int) $this->input('project_id');
            $taskId = (int) $this->input('task_id');
            $date = (string) $this->input('date');

            $employeeName = Employee::query()->whereKey($employeeId)->value('name') ?? "Employee #{$employeeId}";
            $projectCompanyId = (int) Project::query()->whereKey($projectId)->value('company_id');
            $taskCompanyId = (int) Task::query()->whereKey($taskId)->value('company_id');

            if ($projectCompanyId !== $companyId) {
                $validator->errors()->add('project_id', 'Selected project does not belong to the selected company.');
            }

            if ($taskCompanyId !== $companyId) {
                $validator->errors()->add('task_id', 'Selected task does not belong to the selected company.');
            }

            $belongsToCompany = DB::table('company_employee')
                ->where('employee_id', $employeeId)
                ->where('company_id', $companyId)
                ->exists();

            if (! $belongsToCompany) {
                $validator->errors()->add('employee_id', 'Selected employee does not belong to the selected company.');
            }

            $assignedToProject = DB::table('employee_project')
                ->where('employee_id', $employeeId)
                ->where('project_id', $projectId)
                ->exists();

            if (! $assignedToProject) {
                $validator->errors()->add('project_id', 'Selected employee is not assigned to the selected project.');
            }

            $hasDifferentProjectOnDate = TimeEntry::query()
                ->where('employee_id', $employeeId)
                ->whereDate('date', $date)
                ->where('id', '!=', $entryId)
                ->where('project_id', '!=', $projectId)
                ->exists();

            if ($hasDifferentProjectOnDate) {
                $validator->errors()->add('project_id', "{$employeeName} already has another project on {$date}.");
            }
        });
    }
}
