<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\PaginatedResult;
use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    private const array DEFAULT_RELATIONS = ['parent', 'children'];

    /**
     * @param int $userId
     * @param array $filters
     * @param array $sorts
     * @param int $perPage
     * @return PaginatedResult
     */
    public function getUserTasksPaginated(
        int $userId,
        array $filters = [],
        array $sorts = [],
        int $perPage = 15
    ): PaginatedResult {
        $query = Task::forUser($userId)->with(self::DEFAULT_RELATIONS);

        $this->applyFilters($query, $filters);

        $this->applySorting($query, $sorts);

        $paginator = $query->paginate($perPage);

        return new PaginatedResult(
            items: $paginator->items(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            from: $paginator->firstItem(),
            to: $paginator->lastItem(),
        );
    }

    /**
     * @param int $userId
     * @return array<int, Task>
     */
    public function getUserTasks(int $userId): array
    {
        return Task::forUser($userId)
            ->with(self::DEFAULT_RELATIONS)
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    /**
     * @param int $taskId
     * @param int $userId
     * @return Task|null
     */
    public function findByIdForUser(int $taskId, int $userId): ?Task
    {
        return Task::forUser($userId)
            ->with(self::DEFAULT_RELATIONS)
            ->find($taskId);
    }

    /**
     * @param array $data
     * @return Task
     */
    public function create(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $task = Task::create($data);
            $task->load(self::DEFAULT_RELATIONS);
            return $task;
        });
    }

    /**
     * @param Task $task
     * @param array $data
     * @return Task
     */
    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task->update($data);
            $task->load(self::DEFAULT_RELATIONS);
            return $task;
        });
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function delete(Task $task): bool
    {
        return DB::transaction(function () use ($task) {
            $this->deleteTaskAndChildren($task);
            return true;
        });
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function markAsCompleted(Task $task): bool
    {
        return DB::transaction(function () use ($task) {
            $task = Task::lockForUpdate()->find($task->id);

            $descendantIds = $this->collectDescendantIds($task->id);

            if (!empty($descendantIds)) {
                $hasIncomplete = Task::whereIn('id', $descendantIds)
                    ->where('status', TaskStatus::TODO)
                    ->lockForUpdate()
                    ->exists();

                if ($hasIncomplete) {
                    return false;
                }
            }

            $task->status = TaskStatus::DONE;
            return $task->save();
        });
    }

    /**
     * @param int $userId
     * @param string $searchTerm
     * @param int $perPage
     * @return PaginatedResult
     */
    public function searchTasks(int $userId, string $searchTerm, int $perPage = 15): PaginatedResult
    {
        $paginator = Task::forUser($userId)
            ->search($searchTerm)
            ->with(self::DEFAULT_RELATIONS)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return new PaginatedResult(
            items: $paginator->items(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            from: $paginator->firstItem(),
            to: $paginator->lastItem(),
        );
    }

    /**
     * @param int $userId
     * @return array<int, Task>
     */
    public function getRootTasks(int $userId): array
    {
        return Task::forUser($userId)
            ->rootTasks()
            ->with(self::DEFAULT_RELATIONS)
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    /**
     * @param int $parentId
     * @param int $userId
     * @return array<int, Task>
     */
    public function getChildTasks(int $parentId, int $userId): array
    {
        return Task::forUser($userId)
            ->where('parent_id', $parentId)
            ->with(self::DEFAULT_RELATIONS)
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function hasIncompleteChildren(Task $task): bool
    {
        $descendantIds = $this->collectDescendantIds($task->id);

        if (empty($descendantIds)) {
            return false;
        }

        return Task::whereIn('id', $descendantIds)
            ->where('status', TaskStatus::TODO)
            ->exists();
    }

    /**
     * @param int $userId
     * @return array<string, mixed>
     */
    public function getTaskStats(int $userId): array
    {
        $stats = Task::forUser($userId)
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "todo" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "done" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN parent_id IS NULL THEN 1 ELSE 0 END) as root_tasks'),
                DB::raw('SUM(CASE WHEN parent_id IS NOT NULL THEN 1 ELSE 0 END) as subtasks'),
                DB::raw('AVG(priority) as avg_priority'),
            ])
            ->first();

        $priorityStats = Task::forUser($userId)
            ->select([
                'priority',
                DB::raw('COUNT(*) as count')
            ])
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(function ($item) {
                $priorityValue = $item->priority instanceof \BackedEnum ? $item->priority->value : $item->priority;
                return [$priorityValue => $item->count];
            })
            ->toArray();

        return [
            'total' => (int) $stats->total,
            'pending' => (int) $stats->pending,
            'completed' => (int) $stats->completed,
            'root_tasks' => (int) $stats->root_tasks,
            'subtasks' => (int) $stats->subtasks,
            'avg_priority' => round((float) $stats->avg_priority, 2),
            'priority_breakdown' => $priorityStats,
            'completion_rate' => $stats->total > 0 ? round(($stats->completed / $stats->total) * 100, 2) : 0,
        ];
    }

    /**
     * @param Builder $query
     * @param array $filters
     * @return void
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['status'])) {
            if ($filters['status'] instanceof TaskStatus) {
                $query->withStatus($filters['status']);
            } elseif (is_string($filters['status'])) {
                $status = TaskStatus::from($filters['status']);
                $query->withStatus($status);
            }
        }

        if (isset($filters['priority'])) {
            if ($filters['priority'] instanceof TaskPriority) {
                $query->withPriority($filters['priority']);
            } elseif (is_int($filters['priority'])) {
                $priority = TaskPriority::fromInt($filters['priority']);
                $query->withPriority($priority);
            }
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === null || $filters['parent_id'] === 'null') {
                $query->rootTasks();
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        if (isset($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (isset($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        if (isset($filters['completed_after'])) {
            $query->where('completed_at', '>=', $filters['completed_after']);
        }

        if (isset($filters['completed_before'])) {
            $query->where('completed_at', '<=', $filters['completed_before']);
        }

        if (isset($filters['root_tasks_only']) && $filters['root_tasks_only']) {
            $query->rootTasks();
        }

        if (isset($filters['subtasks_only']) && $filters['subtasks_only']) {
            $query->subtasks();
        }
    }

    /**
     * @param Builder $query
     * @param array $sorts
     * @return void
     */
    private function applySorting(Builder $query, array $sorts): void
    {
        if (empty($sorts)) {
            $query->orderBy('created_at', 'desc');
            return;
        }

        $allowedSortFields = [
            'id', 'title', 'status', 'priority', 'created_at', 'updated_at', 'completed_at'
        ];

        foreach ($sorts as $field => $direction) {
            if (in_array($field, $allowedSortFields, true)) {
                $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                $query->orderBy($field, $direction);
            }
        }
    }

    /**
     * @param Task $task
     * @return void
     */
    private function deleteTaskAndChildren(Task $task): void
    {
        $descendantIds = $this->collectDescendantIds($task->id);
        if (!empty($descendantIds)) {
            Task::whereIn('id', $descendantIds)->delete();
        }
        $task->delete();
    }

    private function collectDescendantIds(int $parentId): array
    {
        $allIds = [];
        $currentParentIds = [$parentId];

        while (!empty($currentParentIds)) {
            $childIds = Task::whereIn('parent_id', $currentParentIds)
                ->pluck('id')
                ->all();

            if (empty($childIds)) {
                break;
            }

            $allIds = array_merge($allIds, $childIds);
            $currentParentIds = $childIds;
        }

        return $allIds;
    }
}
