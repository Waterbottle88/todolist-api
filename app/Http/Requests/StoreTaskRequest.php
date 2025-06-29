<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Rules\TaskHierarchyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'title' => [
                'required',
                'string',
                'max:255',
                'min:1',
            ],
            'description' => [
                'nullable',
                'string',
                'max:65535',
            ],
            'priority' => [
                'sometimes',
                'integer',
                'min:1',
                'max:5',
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::enum(TaskStatus::class),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:tasks,id',
                new TaskHierarchyRule(),
            ],
        ];
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Task title is required.',
            'title.string' => 'Task title must be a string.',
            'title.max' => 'Task title cannot exceed 255 characters.',
            'title.min' => 'Task title cannot be empty.',
            'description.string' => 'Task description must be a string.',
            'description.max' => 'Task description cannot exceed 65535 characters.',
            'priority.integer' => 'Priority must be an integer.',
            'priority.min' => 'Priority must be at least 1 (Critical).',
            'priority.max' => 'Priority cannot exceed 5 (Lowest).',
            'status.string' => 'Status must be a string.',
            'status.enum' => 'Status must be either "todo" or "done".',
            'parent_id.integer' => 'Parent ID must be an integer.',
            'parent_id.min' => 'Parent ID must be a positive integer.',
            'parent_id.exists' => 'Parent task does not exist.',
        ];
    }

    /**
     * @return string[]
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'parent task',
        ];
    }

    /**
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('priority')) {
            $this->merge(['priority' => TaskPriority::MEDIUM->value]);
        }

        if (!$this->has('status')) {
            $this->merge(['status' => TaskStatus::TODO->value]);
        }

        if ($this->has('parent_id') && empty($this->input('parent_id'))) {
            $this->merge(['parent_id' => null]);
        }

        $this->merge([
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * @param $key
     * @param $default
     * @return mixed
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        $validated['user_id'] = auth()->id();

        return $validated;
    }
}
