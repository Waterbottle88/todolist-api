<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentTaskRepository implements TaskRepositoryInterface
{

    /**
     * @param int $userId
     * @param array $filters
     * @param array $sorts
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserTasksPaginated(
        int $userId,
        array $filters = [],
        array $sorts = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Task::forUser($userId)->with(['parent', 'children']);

        $this->applyFilters($query, $filters);

        $this->applySorting($query, $sorts);

        return $query->paginate($perPage);
    }

    /**
     * @param int $userId
     * @return Collection
     */
    public function getUserTasks(int $userId): Collection
    {
        return Task::forUser($userId)
            ->with(['parent', 'children'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @param int $taskId
     * @param int $userId
     * @return Task|null
     */
    public function findByIdForUser(int $taskId, int $userId): ?Task
    {
        return Task::forUser($userId)
            ->with(['parent', 'children'])
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
            $task->load(['parent', 'children']);
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
            $task->load(['parent', 'children']);
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
            // First delete all children recursively
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
        if (!$task->allChildrenCompleted()) {
            return false;
        }

        return DB::transaction(function () use ($task) {
            return $task->markAsCompleted();
        });
    }

    /**
     * @param int $userId
     * @param string $searchTerm
     * @return Collection
     */
    public function searchTasks(int $userId, string $searchTerm): Collection
    {
        return Task::forUser($userId)
            ->search($searchTerm)
            ->with(['parent', 'children'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @param int $userId
     * @return Collection
     */
    public function getRootTasks(int $userId): Collection
    {
        return Task::forUser($userId)
            ->rootTasks()
            ->with(['children', 'creator', 'updater'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @param int $parentId
     * @param int $userId
     * @return Collection
     */
    public function getChildTasks(int $parentId, int $userId): Collection
    {
        return Task::forUser($userId)
            ->where('parent_id', $parentId)
            ->with(['children'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function hasIncompleteChildren(Task $task): bool
    {
        return $task->children()
            ->where('status', TaskStatus::TODO)
            ->exists();
    }

    /**
     * @param int $userId
     * @return array|int[]
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
                // Convert TaskPriority enum to its integer value for the key
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
        $children = $task->children()->get();

        foreach ($children as $child) {
            $this->deleteTaskAndChildren($child);
        }

        $task->delete();
    }
}
