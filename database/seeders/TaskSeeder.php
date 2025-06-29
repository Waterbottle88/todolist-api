<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    /**
     * @return void
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->createTasksForExistingUsers();
            $this->createTestUserWithTasks();
            $this->createHierarchicalTasks();
        });
    }

    /**
     * @return void
     */
    private function createTasksForExistingUsers(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            $this->createTasksForUser($user);
        }
    }

    /**
     * @return void
     */
    private function createTestUserWithTasks(): void
    {
        $testUser = User::where('email', 'test@example.com')->first();

        $this->createTasksForUser($testUser, true);
    }

    /**
     * @param User $user
     * @param bool $comprehensive
     * @return void
     */
    private function createTasksForUser(User $user, bool $comprehensive = false): void
    {
        $taskCount = $comprehensive ? 25 : rand(5, 15);

        Task::factory()
            ->count(intval($taskCount * 0.6))
            ->forUser($user)
            ->workTask()
            ->withHierarchy()
            ->create();

        Task::factory()
            ->count(intval($taskCount * 0.4))
            ->forUser($user)
            ->personalTask()
            ->create();

        if ($comprehensive) {
            $this->createSpecificTaskScenarios($user);
        }
    }

    /**
     * @param User $user
     * @return void
     */
    private function createSpecificTaskScenarios(User $user): void
    {
        Task::factory()
            ->count(2)
            ->forUser($user)
            ->critical()
            ->pending()
            ->state([
                'title' => 'Critical production issue needs immediate attention',
                'description' => 'Server is down, customer reports coming in. Need to investigate and fix ASAP.',
            ])
            ->create();

        Task::factory()
            ->count(5)
            ->forUser($user)
            ->completed()
            ->create();

        Task::factory()
            ->count(3)
            ->forUser($user)
            ->withoutDescription()
            ->create();

        Task::factory()
            ->forUser($user)
            ->state([
                'title' => 'Comprehensive project planning',
                'description' => 'This is a complex project that requires detailed planning and coordination across multiple teams. The scope includes: 1) Market research and competitor analysis, 2) Technical architecture design and review, 3) Resource allocation and timeline planning, 4) Risk assessment and mitigation strategies, 5) Stakeholder communication and approval process, 6) Implementation roadmap with milestones, 7) Quality assurance and testing protocols, 8) Deployment and monitoring procedures, 9) Post-launch support and maintenance planning, 10) Documentation and knowledge transfer.',
                'priority' => TaskPriority::HIGH,
            ])
            ->create();
    }

    /**
     * @return void
     */
    private function createHierarchicalTasks(): void
    {
        $users = User::limit(2)->get();

        foreach ($users as $user) {
            $project = Task::factory()
                ->forUser($user)
                ->high()
                ->pending()
                ->state([
                    'title' => 'Website Redesign Project',
                    'description' => 'Complete overhaul of company website with modern design and improved user experience.',
                ])
                ->create();

            $phases = [
                [
                    'title' => 'Research and Planning Phase',
                    'description' => 'User research, competitor analysis, and project planning.',
                ],
                [
                    'title' => 'Design Phase',
                    'description' => 'UI/UX design, wireframes, and prototypes.',
                ],
                [
                    'title' => 'Development Phase',
                    'description' => 'Frontend and backend development implementation.',
                ],
                [
                    'title' => 'Testing and Launch Phase',
                    'description' => 'Quality assurance, testing, and deployment.',
                ],
            ];

            foreach ($phases as $index => $phaseData) {
                $phase = Task::factory()
                    ->forUser($user)
                    ->withParent($project)
                    ->medium()
                    ->state($phaseData)
                    ->create();

                if ($index === 0) {
                    $subtasks = [
                        'Conduct user interviews',
                        'Analyze competitor websites',
                        'Create user personas',
                        'Define project requirements',
                    ];
                } elseif ($index === 1) {
                    $subtasks = [
                        'Create wireframes',
                        'Design UI mockups',
                        'Build interactive prototype',
                        'Get stakeholder approval',
                    ];
                } elseif ($index === 2) {
                    $subtasks = [
                        'Set up development environment',
                        'Implement responsive design',
                        'Develop content management system',
                        'Integrate third-party services',
                    ];
                } else {
                    $subtasks = [
                        'Perform browser compatibility testing',
                        'Conduct user acceptance testing',
                        'Set up monitoring and analytics',
                        'Deploy to production',
                    ];
                }

                foreach ($subtasks as $subtaskTitle) {
                    Task::factory()
                        ->forUser($user)
                        ->withParent($phase)
                        ->low()
                        ->state(['title' => $subtaskTitle])
                        ->create();
                }
            }

            $allTasks = Task::where('user_id', $user->id)
                ->where('parent_id', '!=', null)
                ->inRandomOrder()
                ->limit(rand(3, 8))
                ->get();

            foreach ($allTasks as $task) {
                $task->update([
                    'status' => \App\Enums\TaskStatus::DONE,
                    'completed_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
                ]);
            }
        }
    }
}
