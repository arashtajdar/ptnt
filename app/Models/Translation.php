<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'text_en',
        'text_fa',
        'text_it',
        'score'
    ];

    /**
     * Get the user progress for this translation
     */
    public function progress(): HasMany
    {
        return $this->hasMany(UserTranslationProgress::class);
    }
}