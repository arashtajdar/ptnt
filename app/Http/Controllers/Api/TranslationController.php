<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    /**
     * Display a listing of translations
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Translation::with('progress')->get()
        );
    }

    /**
     * Store a newly created translation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text_en' => 'required|string',
            'text_fa' => 'required|string',
            'text_it' => 'required|string',
            'score' => 'sometimes|integer|min:0'
        ]);

        $translation = Translation::create($validated);

        return response()->json($translation, 201);
    }

    /**
     * Display the specified translation
     */
    public function show(Translation $translation): JsonResponse
    {
        return response()->json(
            $translation->load('progress')
        );
    }

    /**
     * Update the specified translation
     */
    public function update(Request $request, Translation $translation): JsonResponse
    {
        $validated = $request->validate([
            'text_en' => 'required|string',
            'text_fa' => 'required|string',
            'text_it' => 'required|string',
            'score' => 'sometimes|integer|min:0'
        ]);

        $translation->update($validated);

        return response()->json($translation);
    }

    /**
     * Remove the specified translation
     */
    public function destroy(Translation $translation): JsonResponse
    {
        $translation->delete();
        return response()->json(null, 204);
    }
}