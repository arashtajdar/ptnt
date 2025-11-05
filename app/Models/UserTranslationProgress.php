<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTranslationProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'translation_id',
        'score',
        'attempts',
        'last_attempt_at'
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime'
    ];

    /**
     * Get the translation this progress belongs to
     */
    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }

    /**
     * Get the user this progress belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}