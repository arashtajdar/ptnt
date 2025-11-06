<?php

namespace App\Services;

use App\Models\UserTranslationProgress;
use App\Repositories\TranslationRepository;
use Illuminate\Support\Facades\DB;

class FlashcardService
{
    public function __construct(
        private TranslationRepository $translationRepository
    ) {}

    /**
     * Get a random flashcard
     */
    public function getRandomCard(int $userId, ?int $excludeScoreGreaterThan = null)
    {
        $translation = $this->translationRepository->getRandom($userId, $excludeScoreGreaterThan);
        
        if (!$translation) {
            return null;
        }

        return [
            'translation' => $translation,
            'progress' => $this->computeProgress($userId)
        ];
    }

    /**
     * Submit an answer for a flashcard
     */
    public function submitAnswer(int $userId, int $translationId, string $result)
    {
        $progress = $this->translationRepository->updateProgress($userId, $translationId, $result);

        return [
            'progress' => $progress,
            'overall_progress' => $this->computeProgress($userId)
        ];
    }

    /**
     * Compute overall progress
     */
    public function computeProgress(int $userId): array
    {
        $totalTranslations = $this->translationRepository->countAll();
        $masteredCount = UserTranslationProgress::where('user_id', $userId)
            ->where('score', '>=', 3)
            ->count();
        $twotime = UserTranslationProgress::where('user_id', $userId)
            ->where('score', '=', 2)
            ->count();
        $onetime = UserTranslationProgress::where('user_id', $userId)
            ->where('score', '=', 1)
            ->count();
        return [
            'total_translations' => $totalTranslations,
            'mastered' => $masteredCount,
            'percentage' => $totalTranslations ? 
                round((($masteredCount+$twotime+$onetime) * 100) / ($totalTranslations *3), 2) : 0
        ];
    }
}