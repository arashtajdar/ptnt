<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FlashcardController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\TranslationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Progress routes
    Route::get('/me/progress', [ProgressController::class, 'show']);
    Route::get('/me/flashcards-responded', [FlashcardController::class, 'responded']);
    Route::get('/me/profile', [\App\Http\Controllers\Api\ProfileController::class, 'show']);
    Route::post('/me/preferences', [\App\Http\Controllers\Api\ProfileController::class, 'updatePreferences']);

    // Flashcard routes
    Route::prefix('flashcards')->group(function () {
        Route::get('/random', [FlashcardController::class, 'random']);
        Route::post('/answer', [FlashcardController::class, 'answer']);
        Route::get('/', [FlashcardController::class, 'index']);
    });

    // Quiz routes
    Route::prefix('quiz')->group(function () {
        Route::get('/', [QuizController::class, 'generate']);
        Route::post('/submit', [QuizController::class, 'submit']);
    });

    // Question routes
    Route::prefix('questions')->group(function () {
        Route::get('/', [QuestionController::class, 'index']);
        Route::get('/images/list', [QuestionController::class, 'imagesList']);
        Route::get('/{question}', [QuestionController::class, 'show']);
    });

    // Admin routes
    Route::middleware('admin')->group(function () {
        // Translation management
        Route::apiResource('translations', TranslationController::class);

        // Question management
        Route::prefix('questions')->group(function () {
            Route::get('/{question}/admin', [QuestionController::class, 'adminShow']);
            Route::post('/', [QuestionController::class, 'store']);
            Route::put('/{question}', [QuestionController::class, 'update']);
            Route::delete('/{question}', [QuestionController::class, 'destroy']);
        });

        // Batch Translation
    });

    // Payment routes
    Route::prefix('payment')->group(function () {
        Route::post('/create-checkout-session', [\App\Http\Controllers\PaymentController::class, 'createCheckoutSession']);
    });

});
Route::get('v1/translate', [\App\Http\Controllers\Api\QuestionTranslationController::class, 'translateAll']);

