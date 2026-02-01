<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

readonly class TaskFilterDto
{
    public function __construct(
        public ?TaskStatus $status = null,
        public ?TaskPriority $priority = null,
        public ?string $search = null,
        public ?int $parentId = null,
        public bool $rootTasksOnly = false,
        public bool $subtasksOnly = false,
        public ?string $createdAfter = null,
        public ?string $createdBefore = null,
        public ?string $completedAfter = null,
        public ?string $completedBefore = null,
    ) {
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: isset($data['status']) ? TaskStatus::from((string) $data['status']) : null,
            priority: isset($data['priority']) ? TaskPriority::fromInt((int) $data['priority']) : null,
            search: isset($data['search']) && !empty(trim($data['search'])) ? trim($data['search']) : null,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            rootTasksOnly: (bool) ($data['root_tasks_only'] ?? false),
            subtasksOnly: (bool) ($data['subtasks_only'] ?? false),
            createdAfter: $data['created_after'] ?? null,
            createdBefore: $data['created_before'] ?? null,
            completedAfter: $data['completed_after'] ?? null,
            completedBefore: $data['completed_before'] ?? null,
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $filters = [];

        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }

        if ($this->priority !== null) {
            $filters['priority'] = $this->priority;
        }

        if ($this->search !== null) {
            $filters['search'] = $this->search;
        }

        if ($this->parentId !== null) {
            $filters['parent_id'] = $this->parentId;
        }

        if ($this->rootTasksOnly) {
            $filters['root_tasks_only'] = true;
        }

        if ($this->subtasksOnly) {
            $filters['subtasks_only'] = true;
        }

        if ($this->createdAfter !== null) {
            $filters['created_after'] = $this->createdAfter;
        }

        if ($this->createdBefore !== null) {
            $filters['created_before'] = $this->createdBefore;
        }

        if ($this->completedAfter !== null) {
            $filters['completed_after'] = $this->completedAfter;
        }

        if ($this->completedBefore !== null) {
            $filters['completed_before'] = $this->completedBefore;
        }

        return $filters;
    }

}
