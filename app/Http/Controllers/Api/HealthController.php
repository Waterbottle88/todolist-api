<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Health",
 *     description="API health check operations"
 * )
 */
class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     operationId="healthCheck",
     *     tags={"Health"},
     *     summary="API health check",
     *     description="Check the health status of the API",
     *     @OA\Response(
     *         response=200,
     *         description="API is healthy",
     *         @OA\JsonContent(ref="#/components/schemas/HealthResponse")
     *     )
     * )
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'environment' => app()->environment(),
        ]);
    }
}