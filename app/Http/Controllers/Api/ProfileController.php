<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        protected \App\Services\FlashcardService $flashcardService
    ) {
    }

    public function show(Request $request)
    {
        $user = $request->user();

        // Load relationships if they exist
        $user->load(['questionStats', 'translationProgress']);

        // Calculate Question Stats
        $totalQuestions = \App\Models\Question::count();
        // Count unique questions answered correctly at least once
        $answeredCorrectlyCount = $user->questionStats()
            ->where('correct', '>', 0)
            ->distinct('question_id')
            ->count('question_id');

        $questionsProgressPercent = $totalQuestions > 0
            ? round(($answeredCorrectlyCount / $totalQuestions) * 100, 2)
            : 0;

        // Calculate Flashcard Stats
        $flashcardProgress = $this->flashcardService->computeProgress($user->id);

        return response()->json([
            'user' => $user,
            'stats' => [
                'questions' => $user->questionStats,
                'translations' => $user->translationProgress
            ],
            'overview' => [
                'questions_correct_percent' => $questionsProgressPercent,
                'questions_answered_unique' => $answeredCorrectlyCount,
                'total_questions' => $totalQuestions,
                'flashcards_progress_percent' => $flashcardProgress['percentage'] ?? 0,
                'flashcards_stats' => $flashcardProgress
            ]
        ]);
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'show_farsi' => 'required|boolean'
        ]);

        $request->user()->update($validated);

        return response()->json([
            'user' => $request->user()->fresh()
        ]);
    }
}
