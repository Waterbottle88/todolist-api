<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class TaskSortDto
{
    /**
     * @param array $sorts
     */
    public function __construct(
        public array $sorts = []
    ) {
    }

    /**
     * @param array|string|null $data
     * @return self
     */
    public static function fromInput(array|string|null $data): self
    {
        if (empty($data)) {
            return new self(['created_at' => 'desc']);
        }

        if (is_string($data)) {
            return self::fromString($data);
        }

        if (is_array($data)) {
            return self::fromArray($data);
        }

        return new self(['created_at' => 'desc']);
    }

    /**
     * @param string $sortString
     * @return self
     */
    public static function fromString(string $sortString): self
    {
        $sorts = [];
        $sortPairs = explode(',', $sortString);

        foreach ($sortPairs as $sortPair) {
            $sortPair = trim($sortPair);

            if (str_contains($sortPair, ':')) {
                $parts = explode(':', $sortPair);
            } else {
                $parts = preg_split('/\s+/', $sortPair);
            }

            if (count($parts) === 2) {
                $field = trim($parts[0]);
                $direction = strtolower(trim($parts[1]));

                if (self::isValidField($field) && self::isValidDirection($direction)) {
                    $sorts[$field] = $direction;
                }
            } elseif (count($parts) === 1 && self::isValidField(trim($parts[0]))) {
                $sorts[trim($parts[0])] = 'asc';
            }
        }

        return new self($sorts ?: ['created_at' => 'desc']);
    }

    /**
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $sorts = [];

        foreach ($data as $field => $direction) {
            $field = trim((string) $field);
            $direction = strtolower(trim((string) $direction));

            if (self::isValidField($field) && self::isValidDirection($direction)) {
                $sorts[$field] = $direction;
            }
        }

        return new self($sorts ?: ['created_at' => 'desc']);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->sorts;
    }

    /**
     * @return array
     */
    public static function getAllowedFields(): array
    {
        return [
            'id',
            'title',
            'status',
            'priority',
            'created_at',
            'updated_at',
            'completed_at',
        ];
    }

    /**
     * @return array
     */
    public static function getAllowedDirections(): array
    {
        return ['asc', 'desc'];
    }

    /**
     * @param string $field
     * @return bool
     */
    public static function isValidField(string $field): bool
    {
        return in_array($field, self::getAllowedFields(), true);
    }

    /**
     * @param string $direction
     * @return bool
     */
    public static function isValidDirection(string $direction): bool
    {
        return in_array($direction, self::getAllowedDirections(), true);
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        $errors = [];

        foreach ($this->sorts as $field => $direction) {
            if (!self::isValidField($field)) {
                $errors[] = "Invalid sort field: {$field}. Allowed fields: " . implode(', ', self::getAllowedFields());
            }

            if (!self::isValidDirection($direction)) {
                $errors[] = "Invalid sort direction: {$direction}. Allowed directions: " . implode(', ', self::getAllowedDirections());
            }
        }

        return $errors;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * @return string
     */
    public function getPrimaryField(): string
    {
        if (empty($this->sorts)) {
            return 'created_at';
        }

        return array_key_first($this->sorts);
    }

    /**
     * @return string
     */
    public function getPrimaryDirection(): string
    {
        if (empty($this->sorts)) {
            return 'desc';
        }

        return reset($this->sorts);
    }

    /**
     * @param string $field
     * @return bool
     */
    public function isSortingBy(string $field): bool
    {
        return array_key_exists($field, $this->sorts);
    }

    /**
     * @param string $field
     * @return string|null
     */
    public function getDirectionFor(string $field): ?string
    {
        return $this->sorts[$field] ?? null;
    }
}
