<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Task;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

readonly class TaskHierarchyRule implements ValidationRule
{
    public function __construct(
        private ?int $currentTaskId = null
    ) {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $parentId = (int) $value;
        $userId = Auth::id();

        $parentTask = Task::where('id', $parentId)
            ->where('user_id', $userId)
            ->first();

        if (!$parentTask) {
            $fail('The selected parent task does not exist or does not belong to you.');
            return;
        }

        if ($this->currentTaskId !== null) {
            if ($this->wouldCreateCircularReference($parentTask, $this->currentTaskId)) {
                $fail('The selected parent would create a circular reference in the task hierarchy.');
                return;
            }

            if ($parentId === $this->currentTaskId) {
                $fail('A task cannot be its own parent.');
            }
        }
    }

    /**
     * @param Task $parentTask
     * @param int $currentTaskId
     * @return bool
     */
    private function wouldCreateCircularReference(Task $parentTask, int $currentTaskId): bool
    {
        $current = $parentTask;

        while ($current->parent_id !== null) {
            if ($current->parent_id === $currentTaskId) {
                return true;
            }

            $current = $current->parent;

            if (!$current) {
                break;
            }
        }

        return false;
    }
}
