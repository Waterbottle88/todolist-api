<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateTaskDto;
use App\DTOs\PaginatedResult;
use App\DTOs\TaskFilterDto;
use App\DTOs\TaskSortDto;
use App\DTOs\UpdateTaskDto;
use App\Exceptions\TaskCompletionException;
use App\Exceptions\TaskDeletionException;
use App\Exceptions\TaskHierarchyException;
use App\Exceptions\TaskNotFoundException;
use App\Exceptions\TaskValidationException;
use App\Models\Task;
use App\Repositories\TaskRepositoryInterface;
use Psr\Log\LoggerInterface;

readonly class TaskService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param int $userId
     * @param TaskFilterDto $filters
     * @param TaskSortDto $sorts
     * @param int $perPage
     * @return PaginatedResult
     */
    public function getPaginatedTasks(
        int $userId,
        TaskFilterDto $filters,
        TaskSortDto $sorts,
        int $perPage = 15
    ): PaginatedResult {
        return $this->taskRepository->getUserTasksPaginated(
            $userId,
            $filters->toArray(),
            $sorts->toArray(),
            $perPage
        );
    }

    /**
     * @param int $taskId
     * @param int $userId
     * @return Task
     * @throws TaskNotFoundException
     */
    public function findTask(int $taskId, int $userId): Task
    {
        $task = $this->taskRepository->findByIdForUser($taskId, $userId);

        if (!$task) {
            throw new TaskNotFoundException($taskId, $userId);
        }

        return $task;
    }

    /**
     * @param CreateTaskDto $dto
     * @return Task
     * @throws TaskHierarchyException
     */
    public function createTask(CreateTaskDto $dto): Task
    {
        if ($dto->parentId) {
            $this->validateParentTask($dto->parentId, $dto->userId);
        }

        return $this->taskRepository->create($dto->toArray());
    }

    /**
     * @param int $taskId
     * @param int $userId
     * @param UpdateTaskDto $dto
     * @return Task
     * @throws TaskNotFoundException
     * @throws TaskValidationException
     * @throws TaskHierarchyException
     */
    public function updateTask(int $taskId, int $userId, UpdateTaskDto $dto): Task
    {
        $task = $this->findTask($taskId, $userId);

        if (!$dto->hasUpdates()) {
            throw new TaskValidationException(['No updates provided']);
        }

        if ($dto->wasProvided('parent_id')) {
            $this->validateParentUpdate($task, $dto->parentId);
        }

        return $this->taskRepository->update($task, $dto->toArray());
    }

    /**
     * @param int $taskId
     * @param int $userId
     * @return Task
     * @throws TaskNotFoundException
     * @throws TaskCompletionException
     */
    public function completeTask(int $taskId, int $userId): Task
    {
        $task = $this->findTask($taskId, $userId);

        if ($task->isCompleted()) {
            $this->logger->info('Task already completed', ['task_id' => $taskId]);
            return $task;
        }

        $success = $this->taskRepository->markAsCompleted($task);

        if (!$success) {
            throw new TaskCompletionException('All subtasks must be completed first');
        }

        return $task->fresh();
    }

    /**
     * @param int $taskId
     * @param int $userId
     * @return bool
     * @throws TaskNotFoundException
     * @throws TaskDeletionException
     */
    public function deleteTask(int $taskId, int $userId): bool
    {
        $task = $this->findTask($taskId, $userId);

        if ($task->isCompleted()) {
            throw new TaskDeletionException('Completed tasks cannot be deleted');
        }

        return $this->taskRepository->delete($task);
    }

    /**
     * @param int $userId
     * @return array<string, mixed>
     */
    public function getTaskStats(int $userId): array
    {
        return $this->taskRepository->getTaskStats($userId);
    }

    /**
     * @param int $userId
     * @param string $searchTerm
     * @param int $perPage
     * @return PaginatedResult
     */
    public function searchTasks(int $userId, string $searchTerm, int $perPage = 15): PaginatedResult
    {
        return $this->taskRepository->searchTasks($userId, trim($searchTerm), $perPage);
    }

    /**
     * @param int $parentId
     * @param int $userId
     * @return array
     * @throws TaskNotFoundException
     */
    public function getChildTasks(int $parentId, int $userId): array
    {
        $this->findTask($parentId, $userId);

        return $this->taskRepository->getChildTasks($parentId, $userId);
    }

    /**
     * @throws TaskHierarchyException
     */
    private function validateParentTask(int $parentId, int $userId): void
    {
        $parentTask = $this->taskRepository->findByIdForUser($parentId, $userId);

        if (!$parentTask) {
            throw new TaskHierarchyException("Parent task {$parentId} not found");
        }
    }

    /**
     * @throws TaskHierarchyException
     */
    private function validateParentUpdate(Task $task, ?int $newParentId): void
    {
        if ($newParentId === null) {
            return;
        }

        if ($newParentId === $task->id) {
            throw new TaskHierarchyException('Task cannot be its own parent');
        }

        $newParent = $this->taskRepository->findByIdForUser($newParentId, $task->user_id);
        if (!$newParent) {
            throw new TaskHierarchyException("Parent task {$newParentId} not found");
        }

        $current = $newParent;
        while ($current->parent) {
            if ($current->parent_id === $task->id) {
                throw new TaskHierarchyException('Moving task would create a circular reference');
            }
            $current = $current->parent;
        }
    }
}
