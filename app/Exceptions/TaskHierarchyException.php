<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskHierarchyException extends TaskException
{
    protected int $statusCode = 422;

    /**
     * @param string $reason
     */
    public function __construct(string $reason)
    {
        parent::__construct("Task hierarchy violation: {$reason}");
    }
}
