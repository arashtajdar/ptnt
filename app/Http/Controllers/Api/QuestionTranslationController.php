<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Services\OllamaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuestionTranslationController extends Controller
{
    public function __construct(
        protected OllamaService $ollamaService
    ) {
    }

    /**
     * Translate all questions that have empty translation columns.
     */
    public function translateAll(Request $request): JsonResponse
    {
        // Increase time limit for this long running process
        set_time_limit(600);

        $counter = 0;
        $errors = 0;

        // Fetch questions where text_fa is null or empty
        // You can remove ->whereNull statements if you want to re-translate everything
        $questions = Question::whereNull('text_fa')
            ->orWhere('text_fa', '')
            ->limit(100)
            ->get();

        foreach ($questions as $question) {
            Log::debug('Translating question id started: ' . $question->id);
            if (empty($question->text)) {
                continue;
            }

            $translatedText = $this->ollamaService->translate($question->text, 'Persian');

            if ($translatedText) {
                $question->text_fa = $translatedText;
                $question->save();
                $counter++;
            } else {
                $errors++;
            }
            Log::debug('Translating question id finished: ' . $question->id);
        }

        return response()->json([
            'message' => 'Translation process completed',
            'processed_count' => $counter,
            'error_count' => $errors,
            'total_attempted' => $questions->count()
        ]);
    }
}
