<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Todo List API",
 *     version="1.0.0",
 *     description="A comprehensive task management API with support for hierarchical tasks, filtering, and statistics",
 *     @OA\Contact(
 *         email="api@todolist.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Laravel Sanctum token authentication"
 * )
 * 
 * @OA\Schema(
 *     schema="Task",
 *     type="object",
 *     title="Task",
 *     description="Task model with nested status and priority objects",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Complete project documentation"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Write comprehensive documentation for the API"),
 *     @OA\Property(
 *         property="status",
 *         type="object",
 *         @OA\Property(property="value", type="string", enum={"todo", "done"}, example="todo"),
 *         @OA\Property(property="label", type="string", example="To Do"),
 *         @OA\Property(property="is_completed", type="boolean", example=false)
 *     ),
 *     @OA\Property(
 *         property="priority",
 *         type="object",
 *         @OA\Property(property="value", type="integer", enum={1, 2, 3, 4, 5}, example=3),
 *         @OA\Property(property="label", type="string", example="Medium"),
 *         @OA\Property(property="color", type="string", example="#d97706")
 *     ),
 *     @OA\Property(
 *         property="hierarchy",
 *         type="object",
 *         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
 *         @OA\Property(property="has_children", type="boolean", example=false),
 *         @OA\Property(property="is_root_task", type="boolean", example=true),
 *         @OA\Property(property="depth", type="integer", example=0)
 *     ),
 *     @OA\Property(
 *         property="timestamps",
 *         type="object",
 *         @OA\Property(property="created_at", type="string", format="datetime", example="2024-01-01T09:00:00Z"),
 *         @OA\Property(property="updated_at", type="string", format="datetime", example="2024-01-01T09:00:00Z"),
 *         @OA\Property(property="completed_at", type="string", format="datetime", nullable=true, example=null)
 *     ),
 *     @OA\Property(
 *         property="relationships",
 *         type="object",
 *         @OA\Property(property="parent", nullable=true, example=null),
 *         @OA\Property(property="children", type="array", @OA\Items(type="object"))
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="can_be_completed", type="boolean", example=true),
 *         @OA\Property(property="has_children", type="boolean", example=false),
 *         @OA\Property(property="children_count", type="integer", example=0)
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="TaskStats",
 *     type="object",
 *     title="Task Statistics",
 *     description="Comprehensive task statistics and analytics",
 *     @OA\Property(
 *         property="overview",
 *         type="object",
 *         @OA\Property(property="total_tasks", type="integer", example=50),
 *         @OA\Property(property="pending_tasks", type="integer", example=30),
 *         @OA\Property(property="completed_tasks", type="integer", example=20),
 *         @OA\Property(property="completion_rate", type="number", format="float", example=40.0),
 *         @OA\Property(property="average_priority", type="number", format="float", example=3.2)
 *     ),
 *     @OA\Property(
 *         property="hierarchy",
 *         type="object",
 *         @OA\Property(property="root_tasks", type="integer", example=25),
 *         @OA\Property(property="subtasks", type="integer", example=25),
 *         @OA\Property(property="hierarchy_depth", type="integer", example=2)
 *     ),
 *     @OA\Property(
 *         property="priority_distribution",
 *         type="object",
 *         @OA\Property(property="critical", type="integer", example=5),
 *         @OA\Property(property="high", type="integer", example=10),
 *         @OA\Property(property="medium", type="integer", example=20),
 *         @OA\Property(property="low", type="integer", example=10),
 *         @OA\Property(property="lowest", type="integer", example=5)
 *     ),
 *     @OA\Property(
 *         property="trends",
 *         type="object",
 *         @OA\Property(
 *             property="most_common_priority",
 *             type="object",
 *             @OA\Property(property="value", type="integer", example=3),
 *             @OA\Property(property="label", type="string", example="Medium"),
 *             @OA\Property(property="count", type="integer", example=20)
 *         ),
 *         @OA\Property(
 *             property="productivity_score",
 *             type="object",
 *             @OA\Property(property="score", type="number", format="float", example=65.0),
 *             @OA\Property(property="level", type="string", example="Good"),
 *             @OA\Property(property="description", type="string", example="Good progress on your tasks...")
 *         )
 *     ),
 *     @OA\Property(property="generated_at", type="string", format="datetime", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(
 *         property="links",
 *         type="object",
 *         @OA\Property(property="tasks", type="string", example="/api/v1/tasks")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Error Response",
 *     description="Standard error response",
 *     @OA\Property(property="error", type="string", example="Task not found"),
 *     @OA\Property(property="message", type="string", example="The specified task could not be found")
 * )
 * 
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     title="Validation Error Response",
 *     description="Validation error response",
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(property="errors", type="object", example={"title": {"The title field is required."}})
 * )
 * 
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     title="Pagination Meta",
 *     description="Pagination metadata",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="from", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=5),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="to", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=75)
 * )
 * 
 * @OA\Schema(
 *     schema="PaginationLinks",
 *     type="object",
 *     title="Pagination Links",
 *     description="Pagination links",
 *     @OA\Property(property="first", type="string", example="http://localhost/api/v1/tasks?page=1"),
 *     @OA\Property(property="last", type="string", example="http://localhost/api/v1/tasks?page=5"),
 *     @OA\Property(property="prev", type="string", nullable=true, example=null),
 *     @OA\Property(property="next", type="string", example="http://localhost/api/v1/tasks?page=2")
 * )
 * 
 * @OA\Schema(
 *     schema="TaskCollection",
 *     type="object",
 *     title="Task Collection",
 *     description="Paginated collection of tasks with summary",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Task")),
 *     @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"),
 *     @OA\Property(
 *         property="summary",
 *         type="object",
 *         @OA\Property(property="total_tasks", type="integer", example=50),
 *         @OA\Property(property="completed_tasks", type="integer", example=20),
 *         @OA\Property(property="pending_tasks", type="integer", example=30),
 *         @OA\Property(property="root_tasks", type="integer", example=25),
 *         @OA\Property(property="subtasks", type="integer", example=25),
 *         @OA\Property(property="completion_rate", type="number", format="float", example=40.0),
 *         @OA\Property(property="priority_breakdown", type="object", example={"1": 5, "2": 10, "3": 20, "4": 10, "5": 5})
 *     ),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="datetime", nullable=true, example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="datetime", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="AuthLoginRequest",
 *     type="object",
 *     title="Login Request",
 *     description="User login request payload",
 *     required={"email", "password"},
 *     @OA\Property(property="email", type="string", format="email", maxLength=255, example="john@example.com"),
 *     @OA\Property(property="password", type="string", minLength=8, maxLength=255, example="password123")
 * )
 * 
 * @OA\Schema(
 *     schema="AuthRegisterRequest",
 *     type="object",
 *     title="Register Request",
 *     description="User registration request payload",
 *     required={"name", "email", "password", "password_confirmation"},
 *     @OA\Property(property="name", type="string", maxLength=255, example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255, example="john@example.com"),
 *     @OA\Property(property="password", type="string", minLength=8, maxLength=255, example="password123"),
 *     @OA\Property(property="password_confirmation", type="string", minLength=8, maxLength=255, example="password123")
 * )
 * 
 * @OA\Schema(
 *     schema="AuthTokenResponse",
 *     type="object",
 *     title="Authentication Token Response",
 *     description="Response containing authentication token and user data",
 *     @OA\Property(property="access_token", type="string", example="1|abc123token456"),
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 * 
 * @OA\Schema(
 *     schema="MessageResponse",
 *     type="object",
 *     title="Message Response",
 *     description="Simple message response",
 *     @OA\Property(property="message", type="string", example="Operation completed successfully")
 * )
 * 
 * @OA\Schema(
 *     schema="HealthResponse",
 *     type="object",
 *     title="Health Check Response",
 *     description="API health status response",
 *     @OA\Property(property="status", type="string", example="healthy"),
 *     @OA\Property(property="timestamp", type="string", format="datetime", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="version", type="string", example="1.0.0"),
 *     @OA\Property(property="environment", type="string", example="production")
 * )
 */
abstract class Controller
{
    //
}
