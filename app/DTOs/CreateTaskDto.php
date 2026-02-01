<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

readonly class CreateTaskDto
{
    public function __construct(
        public int $userId,
        public string $title,
        public ?string $description = null,
        public TaskPriority $priority = TaskPriority::MEDIUM,
        public TaskStatus $status = TaskStatus::TODO,
        public ?int $parentId = null,
    ) {
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            title: (string) $data['title'],
            description: $data['description'] ?? null,
            priority: isset($data['priority']) ? TaskPriority::fromInt((int) $data['priority']) : TaskPriority::MEDIUM,
            status: isset($data['status']) ? TaskStatus::from((string) $data['status']) : TaskStatus::TODO,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'parent_id' => $this->parentId,
        ];
    }

}
