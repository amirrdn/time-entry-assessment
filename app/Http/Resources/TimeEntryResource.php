<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'hours' => (float) $this->hours,
            'company' => [
                'id' => $this->company?->id,
                'name' => $this->company?->name,
            ],
            'employee' => [
                'id' => $this->employee?->id,
                'name' => $this->employee?->name,
            ],
            'project' => [
                'id' => $this->project?->id,
                'name' => $this->project?->name,
            ],
            'task' => [
                'id' => $this->task?->id,
                'name' => $this->task?->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
