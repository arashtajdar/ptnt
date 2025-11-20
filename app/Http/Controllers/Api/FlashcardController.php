<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlashcardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class FlashcardController extends Controller
{
    protected $flashcardService;
    protected $translationRepository;

    public function __construct(FlashcardService $flashcardService, \App\Repositories\TranslationRepository $translationRepository)
    {
        $this->flashcardService = $flashcardService;
        $this->translationRepository = $translationRepository;
    }

    /**
     * Get a random flashcard
     */
    public function random(Request $request): JsonResponse
    {
        $excludeScoreGreaterThan = $request->query('exclude_score_gt');
        
        $card = $this->flashcardService->getRandomCard(
            $request->user()->id,
            $excludeScoreGreaterThan ? (int)$excludeScoreGreaterThan : null
        );

        if (!$card) {
            return response()->json(['message' => 'No cards available'], 404);
        }

        return response()->json($card);
    }

    /**
     * Submit an answer for a flashcard
     */
    public function answer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'translationId' => 'required|integer|exists:translations,id',
            'result' => 'required|in:correct,wrong'
        ]);

        $result = $this->flashcardService->submitAnswer(
            $request->user()->id,
            $validated['translationId'],
            $validated['result']
        );

        return response()->json($result);
    }

    /**
     * Get flashcards that the user has responded to
     */
    public function responded(Request $request): JsonResponse
    {
        $cards = $this->flashcardService->getRespondedCards($request->user()->id);
        return response()->json($cards);
    }

    /**
     * List all flashcards
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);

        $translationsQuery = $this->translationRepository->getAllWithProgress($request->user()->id);
        $total = $translationsQuery->count();
        $translations = $translationsQuery->forPage($page, $perPage)->values();

        return response()->json([
            'translations' => $translations,
            'progress' => $this->flashcardService->computeProgress($request->user()->id),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage)
            ]
        ]);
    }
}