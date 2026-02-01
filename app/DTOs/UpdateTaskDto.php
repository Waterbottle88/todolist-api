<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

readonly class UpdateTaskDto
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?TaskPriority $priority = null,
        public ?TaskStatus $status = null,
        public ?int $parentId = null,
        private array $providedFields = [],
    ) {
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $providedFields = array_keys($data);

        return new self(
            title: isset($data['title']) ? (string) $data['title'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            priority: isset($data['priority']) ? TaskPriority::fromInt((int) $data['priority']) : null,
            status: isset($data['status']) ? TaskStatus::from((string) $data['status']) : null,
            parentId: array_key_exists('parent_id', $data) ? (isset($data['parent_id']) ? (int) $data['parent_id'] : null) : null,
            providedFields: $providedFields,
        );
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        if (in_array('title', $this->providedFields) && $this->title !== null) {
            $data['title'] = $this->title;
        }

        if (in_array('description', $this->providedFields)) {
            $data['description'] = $this->description;
        }

        if (in_array('priority', $this->providedFields) && $this->priority !== null) {
            $data['priority'] = $this->priority->value;
        }

        if (in_array('status', $this->providedFields) && $this->status !== null) {
            $data['status'] = $this->status->value;
        }

        if (in_array('parent_id', $this->providedFields)) {
            $data['parent_id'] = $this->parentId;
        }

        return $data;
    }

    /**
     * @param string $field
     * @return bool
     */
    public function wasProvided(string $field): bool
    {
        return in_array($field, $this->providedFields);
    }

    /**
     * @return bool
     */
    public function hasUpdates(): bool
    {
        return !empty($this->providedFields);
    }
}
