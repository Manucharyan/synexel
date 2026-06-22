<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'summary' => $this->summary,
            'workbook_id' => $this->workbook_id,
            'workbook_name' => $this->workbook_name,
            'sheet_id' => $this->sheet_id,
            'sheet_name' => $this->sheet_name,
            'target' => $this->target,
            'operation_id' => $this->operation_id,
            'details' => $this->details,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
