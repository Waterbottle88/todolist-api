<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="User",
 *     description="User profile operations"
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/user",
     *     operationId="getCurrentUserProfile",
     *     tags={"User"},
     *     summary="Get current user profile",
     *     description="Get the current authenticated user's profile information",
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            (new UserResource($request->user()))->resolve()
        );
    }
}