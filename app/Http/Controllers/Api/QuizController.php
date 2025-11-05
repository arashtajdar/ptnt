<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(
        private QuizService $quizService
    ) {}

    /**
     * Generate a new quiz
     */
    public function generate(Request $request): JsonResponse
    {
        $count = (int)$request->query('count', 30);
        return response()->json(
            $this->quizService->generate($count)
        );
    }

    /**
     * Submit quiz answers
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => 'required|integer|exists:questions,id',
            'answers' => 'required|array|size:' . count($request->input('question_ids', [])),
            'answers.*' => 'required|in:V,F,v,f'
        ]);

        $result = $this->quizService->submitAnswers(
            $request->user()->id,
            $validated['question_ids'],
            $validated['answers']
        );

        return response()->json($result);
    }
}