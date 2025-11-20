<?php

namespace App\Repositories;

use App\Models\Translation;
use App\Models\UserTranslationProgress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TranslationRepository{
    /**
     * Get total number of translations
     */
    public function countAll(): int
    {
        return Translation::count();
    }
    
    /**
     * Get a random translation
     */
    public function getRandom(int $userId, ?int $excludeScoreGreaterThan = null): ?Translation
    {
        $query = Translation::query()
            ->leftJoin('user_translation_progress as progress', function ($join) use ($userId) {
                $join->on('translations.id', '=', 'progress.translation_id')
                    ->where('progress.user_id', '=', $userId);
            });

        if ($excludeScoreGreaterThan !== null) {
            $query->where(function ($q) use ($excludeScoreGreaterThan) {
                $q->where('progress.score', '<=', $excludeScoreGreaterThan)
                    ->orWhereNull('progress.score');
            });
        }

        return $query->select('translations.*')
            ->inRandomOrder()
            ->first();
    }

    /**
     * Get all translations with user progress
     */
    public function getAllWithProgress(int $userId): Collection
    {
        return Translation::query()
            ->leftJoin('user_translation_progress as progress', function ($join) use ($userId) {
                $join->on('translations.id', '=', 'progress.translation_id')
                    ->where('progress.user_id', '=', $userId);
            })
            ->select('translations.*', 'progress.score', 'progress.attempts', 'progress.last_attempt_at')
            ->get();
    }

    /**
     * Get translations that the user has responded to
     */
    public function getResponded(int $userId): Collection
    {
        return Translation::query()
            ->join('user_translation_progress as progress', function ($join) use ($userId) {
                $join->on('translations.id', '=', 'progress.translation_id')
                    ->where('progress.user_id', '=', $userId);
            })
            ->where('progress.attempts', '>', 0)
            ->select('translations.*', 'progress.score', 'progress.attempts', 'progress.last_attempt_at')
            ->orderByDesc('progress.last_attempt_at')
            ->get();
    }

    /**
     * Update translation progress
     */
    public function updateProgress(int $userId, int $translationId, string $result): UserTranslationProgress
    {
        return DB::transaction(function () use ($userId, $translationId, $result) {
            $progress = UserTranslationProgress::firstOrNew([
                'user_id' => $userId,
                'translation_id' => $translationId
            ]);

            $progress->attempts = ($progress->attempts ?? 0) + 1;
            $progress->score = ($progress->score ?? 0) + ($result === 'correct' ? 1 : -1);
            $progress->score = max(0, $progress->score); // Don't go below 0
            $progress->last_attempt_at = now();
            $progress->save();

            return $progress;
        });
    }
}