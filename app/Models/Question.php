<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'text_fa',
        'image',
        'answer',
        'parent_number',
        'question_number'
    ];

    /**
     * Get the stats for this question
     */
    public function stats(): HasMany
    {
        return $this->hasMany(UserQuestionStat::class);
    }
}