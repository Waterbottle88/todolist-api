<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface
{
    /**
     * @param int $userId
     * @param array<string, mixed> $filters
     * @param array<string, string> $sorts
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserTasksPaginated(
        int $userId,
        array $filters = [],
        array $sorts = [],
        int $perPage = 15
    ): LengthAwarePaginator;

    /**
     * @param int $userId
     * @return Collection<int, Task>
     */
    public function getUserTasks(int $userId): Collection;

    /**
     * @param int $taskId
     * @param int $userId
     * @return Task|null
     */
    public function findByIdForUser(int $taskId, int $userId): ?Task;

    /**
     * @param array<string, mixed> $data
     * @return Task
     */
    public function create(array $data): Task;

    /**
     * @param Task $task
     * @param array<string, mixed> $data
     * @return Task
     */
    public function update(Task $task, array $data): Task;

    /**
     * @param Task $task
     * @return bool
     */
    public function delete(Task $task): bool;

    /**
     * @param Task $task
     * @return bool
     */
    public function markAsCompleted(Task $task): bool;

    /**
     * @param int $userId
     * @param string $searchTerm
     * @return Collection<int, Task>
     */
    public function searchTasks(int $userId, string $searchTerm): Collection;

    /**
     * @param int $userId
     * @return Collection<int, Task>
     */
    public function getRootTasks(int $userId): Collection;

    /**
     * @param int $parentId
     * @param int $userId
     * @return Collection<int, Task>
     */
    public function getChildTasks(int $parentId, int $userId): Collection;

    /**
     * @param Task $task
     * @return bool
     */
    public function hasIncompleteChildren(Task $task): bool;

    /**
     * @param int $userId
     * @return array<string, int>
     */
    public function getTaskStats(int $userId): array;
}
