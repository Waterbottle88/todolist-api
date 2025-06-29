<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case DONE = 'done';

    /**
     * @return array
     */
    public static function values(): array
    {
        return array_map(fn(self $status) => $status->value, self::cases());
    }

    /**
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::TODO => 'To Do',
            self::DONE => 'Completed',
        };
    }
    
    /**
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this === self::DONE;
    }
}
