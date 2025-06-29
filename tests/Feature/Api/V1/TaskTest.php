<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();

        $taskData = [
            'title' => 'Complete project documentation',
            'description' => 'Write comprehensive documentation for the API',
            'priority' => TaskPriority::HIGH->value,
            'status' => TaskStatus::TODO->value,
        ];

        $response = $this->postJson('/api/v1/tasks', $taskData, $auth['headers']);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'priority',
                    'user_id',
                    'parent_id',
                    'created_at',
                    'updated_at',
                    'completed_at',
                ]
            ])
            ->assertJson([
                'data' => [
                    'title' => 'Complete project documentation',
                    'description' => 'Write comprehensive documentation for the API',
                    'status' => TaskStatus::TODO->value,
                    'priority' => TaskPriority::HIGH->value,
                    'user_id' => $auth['user']->id,
                    'parent_id' => null,
                    'completed_at' => null,
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Complete project documentation',
            'user_id' => $auth['user']->id,
            'status' => TaskStatus::TODO->value,
        ]);
    }

    public function test_task_creation_fails_with_invalid_data(): void
    {
        $auth = $this->createAuthenticatedApiUser();

        $response = $this->postJson('/api/v1/tasks', [], $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    public function test_task_creation_fails_without_authentication(): void
    {
        $taskData = [
            'title' => 'Test task',
            'priority' => TaskPriority::MEDIUM->value,
        ];

        $response = $this->postJson('/api/v1/tasks', $taskData);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_subtask(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $parentTask = Task::factory()->forUser($auth['user'])->create();

        $taskData = [
            'title' => 'Subtask',
            'description' => 'This is a subtask',
            'priority' => TaskPriority::MEDIUM->value,
            'parent_id' => $parentTask->id,
        ];

        $response = $this->postJson('/api/v1/tasks', $taskData, $auth['headers']);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'Subtask',
                    'parent_id' => $parentTask->id,
                    'user_id' => $auth['user']->id,
                ]
            ]);
    }

    public function test_authenticated_user_can_get_task_list(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        Task::factory()->count(3)->forUser($auth['user'])->create();

        $response = $this->getJson('/api/v1/tasks', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'status',
                        'priority',
                        'user_id',
                        'parent_id',
                        'created_at',
                        'updated_at',
                        'completed_at',
                    ]
                ],
                'links',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ]
            ]);

        $this->assertCount(3, $response['data']);
    }

    public function test_user_only_sees_their_own_tasks(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $otherUser = User::factory()->create();

        Task::factory()->count(2)->forUser($auth['user'])->create();
        Task::factory()->count(3)->forUser($otherUser)->create();

        $response = $this->getJson('/api/v1/tasks', $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(2, $response['data']);
    }

    public function test_task_list_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/tasks');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_specific_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $task = Task::factory()->forUser($auth['user'])->create();

        $response = $this->getJson("/api/v1/tasks/{$task->id}", $auth['headers']);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'user_id' => $auth['user']->id,
                ]
            ]);
    }

    public function test_user_cannot_access_other_users_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $otherUser = User::factory()->create();
        $task = Task::factory()->forUser($otherUser)->create();

        $response = $this->getJson("/api/v1/tasks/{$task->id}", $auth['headers']);

        $response->assertStatus(404);
    }

    public function test_get_nonexistent_task_returns_404(): void
    {
        $auth = $this->createAuthenticatedApiUser();

        $response = $this->getJson('/api/v1/tasks/999999', $auth['headers']);

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_update_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $task = Task::factory()->forUser($auth['user'])->create();

        $updateData = [
            'title' => 'Updated task title',
            'description' => 'Updated description',
            'priority' => TaskPriority::CRITICAL->value,
            'status' => TaskStatus::DONE->value,
        ];

        $response = $this->putJson("/api/v1/tasks/{$task->id}", $updateData, $auth['headers']);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $task->id,
                    'title' => 'Updated task title',
                    'description' => 'Updated description',
                    'priority' => TaskPriority::CRITICAL->value,
                    'status' => TaskStatus::DONE->value,
                    'user_id' => $auth['user']->id,
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated task title',
            'status' => TaskStatus::DONE->value,
        ]);
    }

    public function test_user_cannot_update_other_users_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $otherUser = User::factory()->create();
        $task = Task::factory()->forUser($otherUser)->create();

        $updateData = [
            'title' => 'Updated title',
        ];

        $response = $this->putJson("/api/v1/tasks/{$task->id}", $updateData, $auth['headers']);

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_delete_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $task = Task::factory()->forUser($auth['user'])->pending()->create();

        $response = $this->deleteJson("/api/v1/tasks/{$task->id}", [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Task deleted successfully.',
            ]);

        $this->assertSoftDeleted('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_user_cannot_delete_other_users_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $otherUser = User::factory()->create();
        $task = Task::factory()->forUser($otherUser)->create();

        $response = $this->deleteJson("/api/v1/tasks/{$task->id}", [], $auth['headers']);

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_complete_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $task = Task::factory()->forUser($auth['user'])->pending()->create();

        $response = $this->patchJson("/api/v1/tasks/{$task->id}/complete", [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'completed_at',
                ],
                'message'
            ])
            ->assertJson([
                'data' => [
                    'id' => $task->id,
                    'status' => TaskStatus::DONE->value,
                ],
                'message' => 'Task marked as completed successfully.',
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::DONE->value,
        ]);

        $task->refresh();
        $this->assertNotNull($task->completed_at);
    }

    public function test_user_cannot_complete_other_users_task(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $otherUser = User::factory()->create();
        $task = Task::factory()->forUser($otherUser)->pending()->create();

        $response = $this->patchJson("/api/v1/tasks/{$task->id}/complete", [], $auth['headers']);

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_search_tasks(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        Task::factory()->forUser($auth['user'])->create(['title' => 'Complete documentation']);
        Task::factory()->forUser($auth['user'])->create(['title' => 'Fix bug in API']);
        Task::factory()->forUser($auth['user'])->create(['description' => 'Update documentation']);

        $response = $this->getJson('/api/v1/tasks/search?q=documentation', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                    ]
                ]
            ]);

        $this->assertCount(2, $response['data']);
    }

    public function test_search_fails_with_invalid_query(): void
    {
        $auth = $this->createAuthenticatedApiUser();

        $response = $this->getJson('/api/v1/tasks/search?q=a', $auth['headers']);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_filter_tasks_by_status(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        Task::factory()->forUser($auth['user'])->pending()->count(2)->create();
        Task::factory()->forUser($auth['user'])->completed()->count(3)->create();

        $response = $this->getJson('/api/v1/tasks?status=done', $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(3, $response['data']);

        foreach ($response['data'] as $task) {
            $this->assertEquals(TaskStatus::DONE->value, $task['status']);
        }
    }

    public function test_authenticated_user_can_filter_tasks_by_priority(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        Task::factory()->forUser($auth['user'])->critical()->count(2)->create();
        Task::factory()->forUser($auth['user'])->medium()->count(3)->create();

        $response = $this->getJson('/api/v1/tasks?priority=1', $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(2, $response['data']);

        foreach ($response['data'] as $task) {
            $this->assertEquals(TaskPriority::CRITICAL->value, $task['priority']);
        }
    }

    public function test_authenticated_user_can_get_root_tasks_only(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $parentTask = Task::factory()->forUser($auth['user'])->create();
        Task::factory()->forUser($auth['user'])->count(2)->create();
        Task::factory()->forUser($auth['user'])->withParent($parentTask)->count(3)->create();

        $response = $this->getJson('/api/v1/tasks?root_tasks_only=true', $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(3, $response['data']);

        foreach ($response['data'] as $task) {
            $this->assertNull($task['parent_id']);
        }
    }

    public function test_authenticated_user_can_get_subtasks_only(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $parentTask = Task::factory()->forUser($auth['user'])->create();
        Task::factory()->forUser($auth['user'])->count(2)->create();
        Task::factory()->forUser($auth['user'])->withParent($parentTask)->count(3)->create();

        $response = $this->getJson('/api/v1/tasks?subtasks_only=true', $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(3, $response['data']);

        foreach ($response['data'] as $task) {
            $this->assertNotNull($task['parent_id']);
        }
    }

    public function test_authenticated_user_can_get_task_children(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        $parentTask = Task::factory()->forUser($auth['user'])->create();
        Task::factory()->forUser($auth['user'])->withParent($parentTask)->count(3)->create();

        $response = $this->getJson("/api/v1/tasks/{$parentTask->id}/children", $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'parent_id',
                    ]
                ]
            ]);

        $this->assertCount(3, $response['data']);

        foreach ($response['data'] as $task) {
            $this->assertEquals($parentTask->id, $task['parent_id']);
        }
    }

    public function test_authenticated_user_can_get_task_stats(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        Task::factory()->forUser($auth['user'])->pending()->count(5)->create();
        Task::factory()->forUser($auth['user'])->completed()->count(3)->create();
        Task::factory()->forUser($auth['user'])->critical()->pending()->count(2)->create();

        $response = $this->getJson('/api/v1/tasks/stats', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_tasks',
                    'completed_tasks',
                    'pending_tasks',
                    'completion_rate',
                    'priority_breakdown',
                    'recent_completions',
                ]
            ]);

        $this->assertEquals(10, $response['data']['total_tasks']);
        $this->assertEquals(3, $response['data']['completed_tasks']);
        $this->assertEquals(7, $response['data']['pending_tasks']);
    }

    public function test_task_operations_with_pagination(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        Task::factory()->forUser($auth['user'])->count(25)->create();

        $response = $this->getJson('/api/v1/tasks?per_page=10&page=1', $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ]
            ]);

        $this->assertEquals(10, count($response['data']));
        $this->assertEquals(1, $response['meta']['current_page']);
        $this->assertEquals(25, $response['meta']['total']);
        $this->assertEquals(3, $response['meta']['last_page']);
    }

    public function test_task_sorting(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        Task::factory()->forUser($auth['user'])->create(['title' => 'B Task']);
        Task::factory()->forUser($auth['user'])->create(['title' => 'A Task']);
        Task::factory()->forUser($auth['user'])->create(['title' => 'C Task']);

        $response = $this->getJson('/api/v1/tasks?sort=title:asc', $auth['headers']);

        $response->assertStatus(200);
        $this->assertEquals('A Task', $response['data'][0]['title']);
        $this->assertEquals('B Task', $response['data'][1]['title']);
        $this->assertEquals('C Task', $response['data'][2]['title']);
    }

    public function test_task_date_filtering(): void
    {
        $auth = $this->createAuthenticatedApiUser();
        
        $oldTask = Task::factory()->forUser($auth['user'])->create();
        $oldTask->created_at = now()->subWeek();
        $oldTask->save();
        
        Task::factory()->forUser($auth['user'])->create();

        $response = $this->getJson('/api/v1/tasks?created_after=' . now()->subDays(3)->format('Y-m-d'), $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(1, $response['data']);
    }
}