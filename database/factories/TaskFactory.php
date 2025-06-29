<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(TaskStatus::cases());

        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'status' => $status,
            'priority' => $this->faker->randomElement(TaskPriority::cases()),
            'title' => $this->faker->sentence(rand(3, 8)),
            'description' => $this->faker->optional(0.7)->paragraphs(rand(1, 3), true),
            'completed_at' => function (array $attributes) {
                return $attributes['status'] === TaskStatus::DONE->value ? $this->faker->dateTimeBetween('-1 month', 'now') : null;
            },
        ];
    }

    /**
     * @return $this
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::TODO,
            'completed_at' => null,
        ]);
    }

    /**
     * @return $this
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::DONE,
            'completed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * @param TaskPriority $priority
     * @return $this
     */
    public function priority(TaskPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    /**
     * @return $this
     */
    public function critical(): static
    {
        return $this->priority(TaskPriority::CRITICAL);
    }

    /**
     * @return $this
     */
    public function high(): static
    {
        return $this->priority(TaskPriority::HIGH);
    }

    /**
     * @return $this
     */
    public function medium(): static
    {
        return $this->priority(TaskPriority::MEDIUM);
    }

    /**
     * @return $this
     */
    public function low(): static
    {
        return $this->priority(TaskPriority::LOW);
    }

    /**
     * @param Task|int $parent
     * @return $this
     */
    public function withParent(Task|int $parent): static
    {
        $parentId = $parent instanceof Task ? $parent->id : $parent;

        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }

    /**
     * @return $this
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
        ]);
    }

    /**
     * @param User|int $user
     * @return $this
     */
    public function forUser(User|int $user): static
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * @return $this
     */
    public function workTask(): static
    {
        $workTasks = [
            'Review quarterly reports',
            'Prepare presentation for client meeting',
            'Update project documentation',
            'Code review for new feature',
            'Fix critical bug in production',
            'Schedule team standup meeting',
            'Research new technology stack',
            'Optimize database queries',
            'Write unit tests for API endpoints',
            'Deploy to staging environment',
            'Conduct user acceptance testing',
            'Update security protocols',
            'Backup database',
            'Perform system maintenance',
            'Create API documentation',
        ];

        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement($workTasks),
            'description' => $this->faker->optional(0.8)->paragraphs(rand(1, 2), true),
        ]);
    }

    /**
     * @return $this
     */
    public function personalTask(): static
    {
        $personalTasks = [
            'Buy groceries for the week',
            'Schedule dentist appointment',
            'Call mom and dad',
            'Pay monthly bills',
            'Plan weekend trip',
            'Clean the house',
            'Exercise for 30 minutes',
            'Read chapter 5 of book',
            'Organize closet',
            'Update resume',
            'Learn new recipe',
            'Water plants',
            'Walk the dog',
            'Meal prep for next week',
            'Backup phone photos',
        ];

        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->randomElement($personalTasks),
            'description' => $this->faker->optional(0.6)->sentence(),
        ]);
    }

    /**
     * @return $this
     */
    public function withHierarchy(): static
    {
        return $this->afterCreating(function (Task $task) {
            if ($this->faker->boolean(30) && $task->parent_id === null) {
                Task::factory()
                    ->count(rand(1, 3))
                    ->withParent($task)
                    ->forUser($task->user_id)
                    ->create();
            }
        });
    }
}
