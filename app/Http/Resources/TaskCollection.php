<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TaskCollection extends ResourceCollection
{
    /**
     * @var string
     */
    public $collects = TaskResource::class;

    /**
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        $result = [
            'data' => $this->collection,
        ];

        // Manually merge pagination data
        $paginationData = $this->with($request);
        return array_merge($result, $paginationData);
    }

    /**
     * @return array
     */
    private function getMeta(): array
    {
        $meta = [
            'count' => $this->collection->count(),
        ];

        if (method_exists($this->resource, 'total')) {
            $meta = array_merge($meta, [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'has_more_pages' => $this->resource->hasMorePages(),
            ]);
        }

        return $meta;
    }

    /**
     * @return array
     */
    private function getSummary(): array
    {
        $tasks = $this->collection;

        $summary = [
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status.value', 'done')->count(),
            'pending_tasks' => $tasks->where('status.value', 'todo')->count(),
            'root_tasks' => $tasks->where('hierarchy.is_root_task', true)->count(),
            'subtasks' => $tasks->where('hierarchy.is_root_task', false)->count(),
        ];

        $summary['completion_rate'] = $summary['total_tasks'] > 0
            ? round(($summary['completed_tasks'] / $summary['total_tasks']) * 100, 2)
            : 0.0;

        $priorityBreakdown = [];
        for ($i = 1; $i <= 5; $i++) {
            $priorityBreakdown[$i] = $tasks->where('priority.value', $i)->count();
        }
        $summary['priority_breakdown'] = $priorityBreakdown;

        return $summary;
    }

    /**
     * @param Request $request
     * @return array[]
     */
    public function with(Request $request): array
    {
        $with = [];

        // Include pagination links and meta data if the resource is paginated
        if ($this->resource instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $with['links'] = [
                'first' => $this->resource->url(1),
                'last' => $this->resource->url($this->resource->lastPage()),
                'prev' => $this->resource->previousPageUrl(),
                'next' => $this->resource->nextPageUrl(),
            ];

            $with['meta'] = [
                'current_page' => $this->resource->currentPage(),
                'from' => $this->resource->firstItem(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'to' => $this->resource->lastItem(),
                'total' => $this->resource->total(),
            ];
        } else {
            $with['links'] = [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ];

            $with['meta'] = [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'per_page' => $this->collection->count(),
                'to' => $this->collection->count(),
                'total' => $this->collection->count(),
            ];
        }

        return $with;
    }
}
