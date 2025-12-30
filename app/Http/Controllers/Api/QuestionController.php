<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Repositories\QuestionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class QuestionController extends Controller
{
    public function __construct(
        private QuestionRepository $questionRepository
    ) {
    }

    /**
     * List questions with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $search = $request->query('search');
        $filterStats = $request->query('filter_stats');

        $questions = $this->questionRepository->getPaginatedWithStats(
            $request->user()->id,
            $search,
            $filterStats,
            $perPage
        );

        // Load translations for the matched phrases
        $allTranslationIds = collect($questions->items())->pluck('translation_ids')->flatten()->unique()->filter()->toArray();
        $translations = \App\Models\Translation::whereIn('id', $allTranslationIds)->get(['id', 'text_it', 'text_en', 'text_fa']);

        foreach ($questions as $question) {
            if ($question->translation_ids) {
                $question->translations = $translations->whereIn('id', $question->translation_ids)->values();
            } else {
                $question->translations = [];
            }
        }

        return response()->json($questions);
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
            'text_fa' => 'nullable|string',
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
            'text_fa' => 'nullable|string',
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

    /**
     * List all images with their related questions
     * Optimized with caching and efficient queries
     */
    public function imagesList(): JsonResponse
    {
        // Cache for 1 hour (3600 seconds)
        $cacheKey = 'questions_images_list';
        $cacheTTL = 3600;

        $images = Cache::remember($cacheKey, $cacheTTL, function () {
            // Use groupBy to fetch all questions in one query, then group by image
            $questions = Question::whereNotNull('image')
                ->select(['id', 'text', 'image', 'answer', 'parent_number', 'question_number'])
                ->get()
                ->groupBy('image');

            return $questions->map(function ($items, $image) {
                return [
                    'image' => $image,
                    'questions' => $items->values()->toArray()
                ];
            })->values();
        });

        return response()->json([
            'total_images' => $images->count(),
            'images' => $images
        ]);
    }
}