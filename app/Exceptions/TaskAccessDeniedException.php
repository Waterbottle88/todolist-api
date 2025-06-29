<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskAccessDeniedException extends TaskException
{
    protected int $statusCode = 403;

    /**
     * @param int $taskId
     * @param int $userId
     */
    public function __construct(int $taskId, int $userId)
    {
        parent::__construct("Access denied to task {$taskId} for user {$userId}.");
    }
}
