<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskValidationException extends TaskException
{
    protected int $statusCode = 422;

    /**
     * @param array $errors
     */
    public function __construct(array $errors)
    {
        $message = "Task validation failed: " . implode(', ', $errors);
        parent::__construct($message);
    }
}
