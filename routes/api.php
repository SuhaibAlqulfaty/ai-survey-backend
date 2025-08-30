<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SurveyController;

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

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile routes
    Route::prefix('auth')->group(function () {
        Route::get('profile', [AuthController::class, 'profile']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
    
    // Survey management routes
    Route::prefix('surveys')->group(function () {
        Route::get('/', [SurveyController::class, 'index']);
        Route::post('/', [SurveyController::class, 'store']);
        Route::get('/{id}', [SurveyController::class, 'show']);
        Route::put('/{id}', [SurveyController::class, 'update']);
        Route::delete('/{id}', [SurveyController::class, 'destroy']);
        
        // Survey actions
        Route::post('/{id}/publish', [SurveyController::class, 'publish']);
        Route::post('/{id}/close', [SurveyController::class, 'close']);
        Route::post('/{id}/duplicate', [SurveyController::class, 'duplicate']);
    });
    
    // Legacy user route for compatibility
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => ['user' => $request->user()]
        ]);
    });
});

// Health check route
Route::get('health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Survey Platform API is running',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});
