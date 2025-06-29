<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskNotFoundException extends TaskException
{
    protected int $statusCode = 404;

    /**
     * @param int $taskId
     * @param int|null $userId
     */
    public function __construct(int $taskId, ?int $userId = null)
    {
        $message = $userId
            ? "Task with ID {$taskId} not found for user {$userId}."
            : "Task with ID {$taskId} not found.";

        parent::__construct($message);
    }
}
