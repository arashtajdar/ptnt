<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuestionStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'question_id',
        'correct',
        'wrong',
        'updated_at'
    ];

    /**
     * Get the question this stat belongs to
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Get the user this stat belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}