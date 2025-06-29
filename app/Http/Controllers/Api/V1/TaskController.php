<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CreateTaskDto;
use App\DTOs\TaskFilterDto;
use App\DTOs\TaskSortDto;
use App\DTOs\UpdateTaskDto;
use App\Exceptions\TaskException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\TaskIndexRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskCollection;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskStatsResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Tasks",
 *     description="Task management operations"
 * )
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks",
     *     operationId="getTasks",
     *     tags={"Tasks"},
     *     summary="Get paginated list of tasks",
     *     description="Retrieve a paginated list of tasks with optional filtering and sorting",
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by task status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"todo", "done"})
     *     ),
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filter by priority level (1=Critical, 2=High, 3=Medium, 4=Low, 5=Lowest)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={1, 2, 3, 4, 5})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in title and description (minimum 2 characters)",
     *         required=false,
     *         @OA\Schema(type="string", minLength=2, maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="parent_id",
     *         in="query",
     *         description="Filter by parent task ID",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="root_tasks_only",
     *         in="query",
     *         description="Show only root tasks (no parent)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="subtasks_only",
     *         in="query",
     *         description="Show only subtasks (have parent)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="created_after",
     *         in="query",
     *         description="Filter tasks created after this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="created_before",
     *         in="query",
     *         description="Filter tasks created before this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="completed_after",
     *         in="query",
     *         description="Filter tasks completed after this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="completed_before",
     *         in="query",
     *         description="Filter tasks completed before this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort field and direction (e.g., 'title:asc', 'priority:desc', 'created_at:desc')",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/TaskCollection")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function index(TaskIndexRequest $request): JsonResponse
    {
        try {
            $filters = TaskFilterDto::fromArray($request->validated());
            $sorts = TaskSortDto::fromInput($request->input('sort'));
            $perPage = (int) $request->input('per_page', 15);

            $tasks = $this->taskService->getPaginatedTasks($filters, $sorts, $perPage);

            return response()->json(new TaskCollection($tasks));

        } catch (TaskException $e) {
            return response()->json([
                'error' => 'Failed to retrieve tasks',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected error retrieving tasks', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while retrieving tasks.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks",
     *     operationId="createTask",
     *     tags={"Tasks"},
     *     summary="Create a new task",
     *     description="Create a new task with the provided data",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title", type="string", minLength=1, maxLength=255, example="Complete project documentation"),
     *             @OA\Property(property="description", type="string", nullable=true, maxLength=65535, example="Write comprehensive documentation for the API"),
     *             @OA\Property(property="status", type="string", enum={"todo", "done"}, default="todo"),
     *             @OA\Property(property="priority", type="integer", enum={1, 2, 3, 4, 5}, default=3, description="1=Critical, 2=High, 3=Medium, 4=Low, 5=Lowest"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, minimum=1, example=1, description="ID of the parent task for subtasks")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Task created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/Task")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            $dto = CreateTaskDto::fromArray($request->validated());
            $task = $this->taskService->createTask($dto);

            return response()->json(['data' => new TaskResource($task)], Response::HTTP_CREATED);

        } catch (TaskException $e) {
            return response()->json([
                'error' => 'Failed to create task',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected error creating task', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while creating the task.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{id}",
     *     operationId="getTask",
     *     tags={"Tasks"},
     *     summary="Get a specific task",
     *     description="Retrieve a single task by its ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/Task")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function show(int $task): JsonResponse
    {
        try {
            $taskModel = $this->taskService->findTask($task);

            return response()->json(['data' => new TaskResource($taskModel)]);

        } catch (TaskException $e) {
            return response()->json([
                'error' => 'Task not found',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected error retrieving task', [
                'user_id' => auth()->id(),
                'task_id' => $task,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while retrieving the task.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tasks/{id}",
     *     operationId="updateTask",
     *     tags={"Tasks"},
     *     summary="Update a task",
     *     description="Update an existing task with new data",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", minLength=1, maxLength=255, example="Updated task title"),
     *             @OA\Property(property="description", type="string", nullable=true, maxLength=65535, example="Updated task description"),
     *             @OA\Property(property="status", type="string", enum={"todo", "done"}),
     *             @OA\Property(property="priority", type="integer", enum={1, 2, 3, 4, 5}, description="1=Critical, 2=High, 3=Medium, 4=Low, 5=Lowest"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, minimum=1, example=1, description="ID of the parent task")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/Task")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function update(UpdateTaskRequest $request, int $task): JsonResponse
    {
        try {
            $dto = UpdateTaskDto::fromArray($request->validated());
            $task = $this->taskService->updateTask($task, $dto);

            return response()->json(['data' => new TaskResource($task)]);

        } catch (TaskException $e) {
            return response()->json([
                'error' => 'Failed to update task',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected error updating task', [
                'user_id' => auth()->id(),
                'task_id' => $task,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while updating the task.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tasks/{id}",
     *     operationId="deleteTask",
     *     tags={"Tasks"},
     *     summary="Delete a task",
     *     description="Delete a task permanently from the system",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Task deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function destroy(int $task): JsonResponse
    {
        try {
            $this->taskService->deleteTask($task);

            Log::info('Task deleted successfully', [
                'user_id' => auth()->id(),
                'task_id' => $task,
            ]);

            return response()->json([
                'message' => 'Task deleted successfully.',
            ]);

        } catch (TaskException $e) {
            Log::warning('Task deletion failed', [
                'user_id' => auth()->id(),
                'task_id' => $task,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete task',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected error deleting task', [
                'user_id' => auth()->id(),
                'task_id' => $task,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while deleting the task.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tasks/{id}/complete",
     *     operationId="completeTask",
     *     tags={"Tasks"},
     *     summary="Mark task as completed",
     *     description="Mark a specific task as completed",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Task ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task marked as completed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/Task"),
     *             @OA\Property(property="message", type="string", example="Task marked as completed successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Task already completed or cannot be completed",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function complete(CompleteTaskRequest $request, int $task): JsonResponse
    {
        try {
            $task = $this->taskService->completeTask($task);

            return response()->json([
                'data' => new TaskResource($task),
                'message' => 'Task marked as completed successfully.',
            ]);

        } catch (TaskException $e) {
            return response()->json([
                'error' => 'Failed to complete task',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected error completing task', [
                'user_id' => auth()->id(),
                'task_id' => $task,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while completing the task.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/stats",
     *     operationId="getTaskStats",
     *     tags={"Tasks"},
     *     summary="Get task statistics",
     *     description="Retrieve statistics about tasks for the authenticated user",
     *     @OA\Response(
     *         response=200,
     *         description="Task statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/TaskStats")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->taskService->getTaskStats();

            return response()->json([
                'data' => new TaskStatsResource($stats)
            ]);

        } catch (\Exception $e) {
            Log::error('Unexpected error retrieving task statistics', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while retrieving task statistics.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/search",
     *     operationId="searchTasks",
     *     tags={"Tasks"},
     *     summary="Search tasks",
     *     description="Search tasks by text in title and description",
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query string",
     *         required=true,
     *         @OA\Schema(type="string", minLength=2, maxLength=255, example="documentation")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Task"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
        ]);

        try {
            $searchTerm = $request->input('q');
            $tasks = $this->taskService->searchTasks($searchTerm);

            return response()->json(new TaskCollection($tasks));

        } catch (TaskException $e) {
            Log::warning('Task search failed', [
                'user_id' => auth()->id(),
                'search_term' => $request->input('q'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected error during task search', [
                'user_id' => auth()->id(),
                'search_term' => $request->input('q'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred during search.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{id}/children",
     *     operationId="getTaskChildren",
     *     tags={"Tasks"},
     *     summary="Get task children",
     *     description="Get all child tasks (subtasks) of a specific task",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Parent task ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Child tasks retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Task"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Parent task not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function children(int $task): JsonResponse
    {
        try {
            $children = $this->taskService->getChildTasks($task);

            return response()->json(new TaskCollection($children));

        } catch (TaskException $e) {
            Log::warning('Failed to retrieve task children', [
                'user_id' => auth()->id(),
                'parent_task_id' => $task,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to retrieve task children',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            Log::error('Unexpected error retrieving task children', [
                'user_id' => auth()->id(),
                'parent_task_id' => $task,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while retrieving task children.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
