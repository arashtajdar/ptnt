<?php

namespace App\Services;

use App\Models\UserQuestionStat;
use App\Repositories\QuestionRepository;
use Illuminate\Support\Facades\DB;

class QuizService
{
    public function __construct(
        private QuestionRepository $questionRepository
    ) {}

    /**
     * Generate a new quiz
     */
    public function generate(int $count = 30)
    {
        return $this->questionRepository->getRandom($count);
    }

    /**
     * Submit quiz answers and update stats
     */
    public function submitAnswers(int $userId, array $questionIds, array $answers): array
    {
        $stats = ['correct' => 0, 'wrong' => 0];

        DB::transaction(function () use ($userId, $questionIds, $answers, &$stats) {
            foreach ($questionIds as $index => $questionId) {
                if (!isset($answers[$index])) continue;

                $stat = UserQuestionStat::firstOrNew([
                    'user_id' => $userId,
                    'question_id' => $questionId
                ]);

                if (strtoupper($answers[$index]) === strtoupper($stat->question->answer)) {
                    $stat->correct = ($stat->correct ?? 0) + 1;
                    $stats['correct']++;
                } else {
                    $stat->wrong = ($stat->wrong ?? 0) + 1;
                    $stats['wrong']++;
                }

                $stat->updated_at = now();
                $stat->save();
            }
        });

        return [
            'total' => count($questionIds),
            'answered' => count($answers),
            'correct' => $stats['correct'],
            'wrong' => $stats['wrong'],
            'score' => $stats['correct'] * 100 / count($questionIds)
        ];
    }
}