<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Repositories\QuestionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function __construct(
        private QuestionRepository $questionRepository
    ) {}

    /**
     * List questions with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int)$request->query('per_page', 10);
        $search = $request->query('search');
        $filterStats = $request->query('filter_stats');

        return response()->json(
            $this->questionRepository->getPaginatedWithStats(
                $request->user()->id,
                $search,
                $filterStats,
                $perPage
            )
        );
    }

    /**
     * Show single question with stats
     */
    public function show(Question $question, Request $request): JsonResponse
    {
        $questionWithStats = $this->questionRepository->getWithStats(
            $question->id,
            $request->user()->id
        );

        if (!$questionWithStats) {
            return response()->json(['message' => 'Question not found'], 404);
        }

        return response()->json($questionWithStats);
    }

    /**
     * Admin: Show question details
     */
    public function adminShow(Question $question): JsonResponse
    {
        return response()->json($question);
    }

    /**
     * Admin: Store a new question
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string',
            'image' => 'nullable|string',
            'answer' => 'required|in:V,F,v,f',
            'parent_number' => 'required|integer',
            'question_number' => 'required|integer'
        ]);

        $question = Question::create($validated);

        return response()->json($question, 201);
    }

    /**
     * Admin: Update a question
     */
    public function update(Question $question, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string',
            'image' => 'nullable|string',
            'answer' => 'required|in:V,F,v,f',
            'parent_number' => 'required|integer',
            'question_number' => 'required|integer'
        ]);

        $question->update($validated);

        return response()->json($question);
    }

    /**
     * Admin: Delete a question
     */
    public function destroy(Question $question): JsonResponse
    {
        $question->delete();
        return response()->json(null, 204);
    }
}