<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CreateTaskDto;
use App\DTOs\PaginatedResult;
use App\DTOs\TaskFilterDto;
use App\DTOs\TaskSortDto;
use App\DTOs\UpdateTaskDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteTaskRequest;
use App\Http\Requests\SearchTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\TaskIndexRequest;
use App\Http\Requests\UpdateTaskRequest;
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
        $filters = TaskFilterDto::fromArray($request->validated());
        $sorts = TaskSortDto::fromInput($request->input('sort'));
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->taskService->getPaginatedTasks(auth()->id(), $filters, $sorts, $perPage);

        return $this->paginatedResponse($result);
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
        $dto = CreateTaskDto::fromArray($request->validated());
        $task = $this->taskService->createTask($dto);

        return response()->json(['data' => new TaskResource($task)], Response::HTTP_CREATED);
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
        $taskModel = $this->taskService->findTask($task, auth()->id());

        return response()->json(['data' => new TaskResource($taskModel)]);
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
        $dto = UpdateTaskDto::fromArray($request->validated());
        $updatedTask = $this->taskService->updateTask($task, auth()->id(), $dto);

        return response()->json(['data' => new TaskResource($updatedTask)]);
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
        $this->taskService->deleteTask($task, auth()->id());

        Log::info('Task deleted successfully', [
            'user_id' => auth()->id(),
            'task_id' => $task,
        ]);

        return response()->json([
            'message' => 'Task deleted successfully.',
        ]);
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
        $completedTask = $this->taskService->completeTask($task, auth()->id());

        return response()->json([
            'data' => new TaskResource($completedTask),
            'message' => 'Task marked as completed successfully.',
        ]);
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
        $stats = $this->taskService->getTaskStats(auth()->id());

        return response()->json([
            'data' => new TaskStatsResource($stats)
        ]);
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
    public function search(SearchTaskRequest $request): JsonResponse
    {
        $searchTerm = $request->input('q');
        $perPage = (int) $request->input('per_page', 15);
        $result = $this->taskService->searchTasks(auth()->id(), $searchTerm, $perPage);

        return $this->paginatedResponse($result);
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
        $children = $this->taskService->getChildTasks($task, auth()->id());

        return response()->json([
            'data' => TaskResource::collection($children),
        ]);
    }

    private function paginatedResponse(PaginatedResult $result): JsonResponse
    {
        return response()->json([
            'data' => TaskResource::collection($result->items),
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
            'meta' => [
                'current_page' => $result->currentPage,
                'from' => $result->from,
                'last_page' => $result->lastPage,
                'per_page' => $result->perPage,
                'to' => $result->to,
                'total' => $result->total,
            ],
        ]);
    }
}
