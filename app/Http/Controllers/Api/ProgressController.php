<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlashcardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(
        private FlashcardService $flashcardService
    ) {}

    /**
     * Get overall progress
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->flashcardService->computeProgress($request->user()->id)
        );
    }
}