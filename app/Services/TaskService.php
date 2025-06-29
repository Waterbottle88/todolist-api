<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateTaskDto;
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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

readonly class TaskService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * @param TaskFilterDto $filters
     * @param TaskSortDto $sorts
     * @param int $perPage
     * @return LengthAwarePaginator
     * @throws TaskValidationException
     */
    public function getPaginatedTasks(
        TaskFilterDto $filters,
        TaskSortDto $sorts,
        int $perPage = 15
    ): LengthAwarePaginator {
        $this->validateFilters($filters);
        $this->validateSorts($sorts);

        $userId = Auth::id();

        return $this->taskRepository->getUserTasksPaginated(
            $userId,
            $filters->toArray(),
            $sorts->toArray(),
            $perPage
        );
    }

    /**
     * @param int $taskId
     * @return Task
     * @throws TaskNotFoundException
     */
    public function findTask(int $taskId): Task
    {
        $userId = Auth::id();
        $task = $this->taskRepository->findByIdForUser($taskId, $userId);

        if (!$task) {
            throw new TaskNotFoundException($taskId, $userId);
        }

        return $task;
    }

    /**
     * @param CreateTaskDto $dto
     * @return Task
     * @throws TaskValidationException
     * @throws TaskHierarchyException
     */
    public function createTask(CreateTaskDto $dto): Task
    {
        $this->validateCreateDto($dto);

        if ($dto->parentId) {
            $this->validateParentTask($dto->parentId, $dto->userId);
        }

        return $this->taskRepository->create($dto->toArray());
    }

    /**
     * @param int $taskId
     * @param UpdateTaskDto $dto
     * @return Task
     * @throws TaskNotFoundException
     * @throws TaskValidationException
     * @throws TaskHierarchyException
     */
    public function updateTask(int $taskId, UpdateTaskDto $dto): Task
    {
        $task = $this->findTask($taskId);
        $this->validateUpdateDto($dto);

        if (array_key_exists('parentId', get_object_vars($dto))) {
            $this->validateParentUpdate($task, $dto->parentId);
        }

        return $this->taskRepository->update($task, $dto->toArray());
    }

    /**
     * @param int $taskId
     * @return Task
     * @throws TaskNotFoundException
     * @throws TaskCompletionException
     */
    public function completeTask(int $taskId): Task
    {
        $task = $this->findTask($taskId);

        if ($task->isCompleted()) {
            Log::info('Task already completed', ['task_id' => $taskId]);
            return $task;
        }

        if ($this->taskRepository->hasIncompleteChildren($task)) {
            throw new TaskCompletionException('All subtasks must be completed first');
        }

        $success = $this->taskRepository->markAsCompleted($task);

        if (!$success) {
            throw new TaskCompletionException('Failed to mark task as completed');
        }

        return $task->fresh();
    }

    /**
     * @param int $taskId
     * @return bool
     * @throws TaskNotFoundException
     * @throws TaskDeletionException
     */
    public function deleteTask(int $taskId): bool
    {
        $task = $this->findTask($taskId);

        if ($task->isCompleted()) {
            throw new TaskDeletionException('Completed tasks cannot be deleted');
        }

        return $this->taskRepository->delete($task);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTaskStats(): array
    {
        $userId = Auth::id();

        return $this->taskRepository->getTaskStats($userId);
    }

    /**
     * @param string $searchTerm
     * @return Collection<int, Task>
     * @throws TaskValidationException
     */
    public function searchTasks(string $searchTerm): Collection
    {
        if (strlen(trim($searchTerm)) < 2) {
            throw new TaskValidationException(['Search term must be at least 2 characters long']);
        }

        $userId = Auth::id();

        return $this->taskRepository->searchTasks($userId, trim($searchTerm));
    }

    /**
     * @param int $parentId
     * @return Collection<int, Task>
     * @throws TaskNotFoundException
     */
    public function getChildTasks(int $parentId): Collection
    {
        $userId = Auth::id();

        $this->findTask($parentId);

        return $this->taskRepository->getChildTasks($parentId, $userId);
    }

    /**
     * @throws TaskValidationException
     */
    private function validateFilters(TaskFilterDto $filters): void
    {
        if (!$filters->isValid()) {
            throw new TaskValidationException($filters->validate());
        }
    }

    /**
     * @throws TaskValidationException
     */
    private function validateSorts(TaskSortDto $sorts): void
    {
        if (!$sorts->isValid()) {
            throw new TaskValidationException($sorts->validate());
        }
    }

    /**
     * @throws TaskValidationException
     */
    private function validateCreateDto(CreateTaskDto $dto): void
    {
        if (!$dto->isValid()) {
            throw new TaskValidationException($dto->validate());
        }
    }

    /**
     * @throws TaskValidationException
     */
    private function validateUpdateDto(UpdateTaskDto $dto): void
    {
        if (!$dto->isValid()) {
            throw new TaskValidationException($dto->validate());
        }

        if (!$dto->hasUpdates()) {
            throw new TaskValidationException(['No updates provided']);
        }
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
