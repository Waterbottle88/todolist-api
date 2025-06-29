<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

enum TaskPriority: int
{
    case CRITICAL = 1;
    case HIGH = 2;
    case MEDIUM = 3;
    case LOW = 4;
    case LOWEST = 5;

    /**
     * @return array
     */
    public static function values(): array
    {
        return array_map(fn(self $priority) => $priority->value, self::cases());
    }

    /**
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::CRITICAL => 'Critical',
            self::HIGH => 'High',
            self::MEDIUM => 'Medium',
            self::LOW => 'Low',
            self::LOWEST => 'Lowest',
        };
    }

    /**
     * @return string
     */
    public function color(): string
    {
        return match($this) {
            self::CRITICAL => '#dc2626',
            self::HIGH => '#ea580c',
            self::MEDIUM => '#d97706',
            self::LOW => '#16a34a',
            self::LOWEST => '#6b7280',
        };
    }

    /**
     * @param int $value
     * @return self
     */
    public static function fromInt(int $value): self
    {
        return match($value) {
            1 => self::CRITICAL,
            2 => self::HIGH,
            3 => self::MEDIUM,
            4 => self::LOW,
            5 => self::LOWEST,
            default => throw new InvalidArgumentException("Invalid priority value: {$value}. Must be between 1 and 5."),
        };
    }
}
