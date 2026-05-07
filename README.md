# Time Entry Interface

Laravel 11 + Vue 3 (Composition API) app for entering and viewing employee time entries.

Authentication is intentionally not required for the main flow.

## Tech Stack

- Laravel 11
- Vue 3 with Composition API
- Inertia.js
- Tailwind CSS
- MySQL

## Setup

1. Install dependencies:
   - `composer install`
   - `npm install`
2. Copy environment:
   - `cp .env.example .env`
3. Configure database in `.env`:
   - `DB_CONNECTION=mysql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_DATABASE=time_track`
   - `DB_USERNAME=...`
   - `DB_PASSWORD=...`
4. Generate app key:
   - `php artisan key:generate`
5. Run migrations and seeders:
   - `php artisan migrate --seed`
6. Run app:
   - `php artisan serve`
   - `npm run dev`

Open `http://127.0.0.1:8000`.

## Main Features

- Global company filter (`All` or specific company).
- `New Entries` tab:
  - Dynamic row input.
  - Row-level dependent dropdowns (employee/project/task by company).
  - Metadata request caching per `company_id`.
  - Keyboard friendly data entry (`Tab` on last hours field adds a new row).
  - Row-level validation feedback from API.
- `History` tab:
  - Read-only table with company/date/employee/project/task/hours.
  - Global company filter reactive refetch.
  - Frontend search (employee/project).
  - Vue pagination.

## Data Model

Tables:
- `companies`
- `employees`
- `projects`
- `tasks`
- `time_entries`
- `company_employee` (pivot)
- `employee_project` (pivot)

Relationships:
- Company ↔ Employee: many-to-many
- Employee ↔ Project: many-to-many
- Company → Project/Task: one-to-many
- TimeEntry belongs to company, employee, project, task

## API Endpoints

- `GET /api/companies`
- `GET /api/time-entries/meta-data?company_id={id|null}`
- `GET /api/time-entries?company_id={id|null}`
- `POST /api/time-entries`

### `POST /api/time-entries` payload

```json
{
  "entries": [
    {
      "company_id": 1,
      "employee_id": 2,
      "project_id": 3,
      "task_id": 4,
      "date": "2026-05-06",
      "hours": 4
    }
  ]
}
```

## Validation Rules Implemented

- `entries` must be an array.
- Each row requires: `company_id`, `employee_id`, `project_id`, `task_id`, `date`, `hours`.
- Integrity checks:
  - Project must belong to selected company.
  - Task must belong to selected company.
  - Employee must belong to selected company.
  - Employee must be assigned to selected project.
- Business rule:
  - One employee cannot log different projects on the same date.

## Notes

- Legacy Breeze auth pages/routes remain in project, but dashboard flow is public to satisfy the test requirement.

## AI Conversation Export

This repository includes a JSON export of the AI-assisted development conversation, as requested in the exercise brief.

- File: `ai-conversation.json`
- Purpose: documents how requirements were translated into implementation decisions, iterations, and fixes.
- Privacy: sensitive values (such as credentials or secrets) should be redacted before sharing.
