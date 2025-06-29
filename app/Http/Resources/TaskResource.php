<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }

    /**
     * @param Request $request
     * @return array[]
     */
    public function with(Request $request): array
    {
        return [];
    }

}
