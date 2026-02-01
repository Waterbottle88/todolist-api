<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskIndexRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                Rule::enum(TaskStatus::class),
            ],
            'priority' => [
                'sometimes',
                'integer',
                'min:1',
                'max:5',
            ],
            'search' => [
                'sometimes',
                'string',
                'min:2',
                'max:255',
            ],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
            ],
            'root_tasks_only' => [
                'sometimes',
                'boolean',
            ],
            'subtasks_only' => [
                'sometimes',
                'boolean',
            ],
            'created_after' => [
                'sometimes',
                'date',
            ],
            'created_before' => [
                'sometimes',
                'date',
                'after_or_equal:created_after',
            ],
            'completed_after' => [
                'sometimes',
                'date',
            ],
            'completed_before' => [
                'sometimes',
                'date',
                'after_or_equal:completed_after',
            ],

            'sort' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'Status must be either "todo" or "done".',
            'priority.integer' => 'Priority must be an integer.',
            'priority.min' => 'Priority must be at least 1 (Critical).',
            'priority.max' => 'Priority cannot exceed 5 (Lowest).',
            'search.min' => 'Search term must be at least 2 characters long.',
            'search.max' => 'Search term cannot exceed 255 characters.',
            'parent_id.integer' => 'Parent ID must be an integer.',
            'parent_id.min' => 'Parent ID must be a positive integer.',
            'root_tasks_only.boolean' => 'Root tasks only must be true or false.',
            'subtasks_only.boolean' => 'Subtasks only must be true or false.',
            'created_after.date' => 'Created after must be a valid date.',
            'created_before.date' => 'Created before must be a valid date.',
            'created_before.after_or_equal' => 'Created before must be after or equal to created after.',
            'completed_after.date' => 'Completed after must be a valid date.',
            'completed_before.date' => 'Completed before must be a valid date.',
            'completed_before.after_or_equal' => 'Completed before must be after or equal to completed after.',
            'sort.string' => 'Sort parameter must be a string.',
            'sort.max' => 'Sort parameter cannot exceed 255 characters.',
            'per_page.integer' => 'Per page must be an integer.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page cannot exceed 100.',
            'page.integer' => 'Page must be an integer.',
            'page.min' => 'Page must be at least 1.',
        ];
    }

    /**
     * @return void
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($this->boolean('root_tasks_only') && $this->boolean('subtasks_only')) {
                $validator->errors()->add('root_tasks_only', 'Cannot filter for both root tasks only and subtasks only simultaneously.');
            }

            if ($this->boolean('root_tasks_only') && $this->filled('parent_id')) {
                $validator->errors()->add('root_tasks_only', 'Cannot specify parent ID when filtering for root tasks only.');
            }
        });
    }

    /**
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('per_page')) {
            $this->merge(['per_page' => 15]);
        }

        if (!$this->has('page')) {
            $this->merge(['page' => 1]);
        }

        if ($this->has('root_tasks_only')) {
            $this->merge(['root_tasks_only' => filter_var($this->input('root_tasks_only'), FILTER_VALIDATE_BOOLEAN)]);
        }

        if ($this->has('subtasks_only')) {
            $this->merge(['subtasks_only' => filter_var($this->input('subtasks_only'), FILTER_VALIDATE_BOOLEAN)]);
        }

        if ($this->has('parent_id') && empty($this->input('parent_id'))) {
            $this->merge(['parent_id' => null]);
        }
    }
}
