<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/health', HealthController::class)->name('api.health');

Route::prefix('v1/auth')
    ->name('api.v1.auth.')
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->middleware('throttle:3,1')->name('register');
        Route::post('/login', 'login')->middleware('throttle:5,1')->name('login');

        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
            Route::post('/logout', 'logout')->name('logout');
            Route::post('/logout-all', 'logoutAll')->name('logout-all');
            Route::get('/me', 'me')->name('me');
            Route::post('/refresh', 'refresh')->name('refresh');
        });
    });

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'throttle:api'])
    ->group(function () {

        Route::get('/user', [UserController::class, 'show'])->name('user');

        Route::controller(TaskController::class)
            ->prefix('tasks')
            ->name('tasks.')
            ->group(function () {

                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{task}', 'show')->name('show')->where('task', '[0-9]+');
                Route::put('/{task}', 'update')->name('update')->where('task', '[0-9]+');
                Route::delete('/{task}', 'destroy')->name('destroy')->where('task', '[0-9]+');

                Route::patch('/{task}/complete', 'complete')->name('complete')->where('task', '[0-9]+');

                Route::get('/stats', 'stats')->name('stats');
                Route::get('/search', 'search')->name('search');
                Route::get('/{task}/children', 'children')->name('children')->where('task', '[0-9]+');
            });
    });

Route::fallback(function () {
    return response()->json([
        'error' => 'Endpoint not found',
        'message' => 'The requested API endpoint does not exist.',
        'documentation' => config('app.url') . '/docs/api',
    ], 404);
});
