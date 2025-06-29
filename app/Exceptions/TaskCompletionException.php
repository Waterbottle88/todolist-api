<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskCompletionException extends TaskException
{
    protected int $statusCode = 422;

    /**
     * @param string $reason
     */
    public function __construct(string $reason)
    {
        parent::__construct("Task cannot be completed: {$reason}");
    }
}
