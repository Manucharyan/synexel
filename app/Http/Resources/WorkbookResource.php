<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkbookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'metadata' => $this->metadata,
            'sheets_count' => $this->whenCounted('sheets'),
            'sheets' => SheetResource::collection($this->whenLoaded('sheets')),
            'access_permission' => $this->when(isset($this->access_permission), $this->access_permission),
            'is_owner' => $this->when(isset($this->is_owner), (bool) $this->is_owner),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
