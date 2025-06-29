<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and authorization operations"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     operationId="registerUser",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     description="Create a new user account and return authentication token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AuthRegisterRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="User registered successfully"),
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="token", type="string", example="1|abc123token456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at->toISOString(),
                ],
                'token' => $token,
            ], Response::HTTP_CREATED);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The provided data is invalid.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Registration failed',
                'message' => 'An error occurred during registration. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     operationId="loginUser",
     *     tags={"Authentication"},
     *     summary="User login",
     *     description="Authenticate user credentials and return authentication token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AuthLoginRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(ref="#/components/schemas/AuthTokenResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'error' => 'Invalid credentials',
                    'message' => 'The provided email or password is incorrect.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $user = Auth::user();
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'created_at' => $user->created_at->toISOString(),
                ],
                'token' => $token,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The provided data is invalid.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('User login failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Login failed',
                'message' => 'An error occurred during login. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     operationId="logoutUser",
     *     tags={"Authentication"},
     *     summary="User logout",
     *     description="Logout user and revoke current authentication token",
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
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
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $user->currentAccessToken()->delete();
            
            return response()->json([
                'message' => 'Logout successful',
            ]);

        } catch (\Exception $e) {
            Log::error('User logout failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Logout failed',
                'message' => 'An error occurred during logout.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout-all",
     *     operationId="logoutAllDevices",
     *     tags={"Authentication"},
     *     summary="Logout from all devices",
     *     description="Logout user from all devices by revoking all authentication tokens",
     *     @OA\Response(
     *         response=200,
     *         description="Logged out from all devices successfully",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
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
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $user->tokens()->delete();

            return response()->json([
                'message' => 'Logged out from all devices successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('User logout all failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Logout failed',
                'message' => 'An error occurred during logout.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     operationId="getCurrentUser",
     *     tags={"Authentication"},
     *     summary="Get current user",
     *     description="Get current authenticated user information",
     *     @OA\Response(
     *         response=200,
     *         description="Current user information",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user", ref="#/components/schemas/User")
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
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     operationId="refreshToken",
     *     tags={"Authentication"},
     *     summary="Refresh authentication token",
     *     description="Refresh the current authentication token by revoking the old one and issuing a new one",
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(property="token", type="string", example="2|def456token789")
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
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $user->currentAccessToken()->delete();

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Token refreshed successfully',
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Token refresh failed',
                'message' => 'An error occurred while refreshing the token.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
