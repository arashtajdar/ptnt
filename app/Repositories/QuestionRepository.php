<?php

namespace App\Repositories;

use App\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class QuestionRepository
{
    /**
     * Get paginated questions with user stats
     */
    public function getPaginatedWithStats(
        int $userId,
        ?string $search = null,
        ?string $filterStats = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        return $this->baseQueryWithStats($userId, $search, $filterStats)
            ->paginate($perPage);
    }

    /**
     * Get random questions
     */
    public function getRandom(int $count = 30): Collection
    {
        return Question::inRandomOrder()
            ->limit($count)
            ->get();
    }

    /**
     * Get random filtered questions (wrong answers, never answered, etc.)
     */
    public function getRandomFiltered(int $userId, int $count = 30, ?string $type = null): Collection
    {
        $filterStats = null;
        if ($type === 'wrong') {
            $filterStats = 'wrong';
        } elseif ($type === 'never_answered') {
            $filterStats = 'none';
        }

        return $this->baseQueryWithStats($userId, null, $filterStats)
            ->inRandomOrder()
            ->limit($count)
            ->get();
    }

    /**
     * Get a single question with stats
     */
    public function getWithStats(int $questionId, int $userId): ?Question
    {
        return $this->baseQueryWithStats($userId)
            ->where('questions.id', $questionId)
            ->first();
    }

    /**
     * Base query for questions with stats
     */
    private function baseQueryWithStats(
        int $userId,
        ?string $search = null,
        ?string $filterStats = null
    ): Builder {
        $query = Question::query()
            ->select('questions.*')
            ->selectRaw('COALESCE(stats.correct, 0) as correct_count')
            ->selectRaw('COALESCE(stats.wrong, 0) as wrong_count')
            ->selectRaw('stats.updated_at as last_attempted')
            ->leftJoin('user_question_stats as stats', function ($join) use ($userId) {
                $join->on('questions.id', '=', 'stats.question_id')
                    ->where('stats.user_id', '=', $userId);
            });

        if ($search) {
            $query->where('questions.text', 'LIKE', "%{$search}%");
        }

        if ($filterStats) {
            switch ($filterStats) {
                case 'correct':
                    $query->whereRaw('COALESCE(stats.correct, 0) > 0');
                    break;
                case 'wrong':
                    $query->whereRaw('COALESCE(stats.wrong, 0) > 0');
                    break;
                case 'none':
                    $query->whereRaw('COALESCE(stats.correct, 0) = 0')
                        ->whereRaw('COALESCE(stats.wrong, 0) = 0');
                    break;
            }
        }

        return $query;
    }
}