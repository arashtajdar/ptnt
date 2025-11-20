<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Load relationships if they exist
        $user->load(['questionStats', 'translationProgress']);
        
        return response()->json([
            'user' => $user,
            'stats' => [
                'questions' => $user->questionStats,
                'translations' => $user->translationProgress
            ]
        ]);
    }
}
