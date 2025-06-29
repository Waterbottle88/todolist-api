<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TaskException extends Exception
{
    protected int $statusCode = 400;

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
