<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $baseUrl;
    protected string $model;

    public function __construct()
    {
        $this->baseUrl = env('OLLAMA_URL', 'http://127.0.0.1:11434');
        // Default to llama3 or user can configure. Using a safe default if not set.
        $this->model = env('OLLAMA_MODEL', 'qwen3-vl:235b-cloud');
    }

    /**
     * Translate text to the target language.
     *
     * @param string $text
     * @param string $targetLang
     * @return string|null
     */
    public function translate(string $text, string $targetLang = 'Persian (Farsi)'): ?string
    {
        try {
            // Construct a prompt that encourages direct translation without extra chatter.
            $prompt = "Translate the following text to {$targetLang}. Only provide the translated text, no unrelated explanations or notes:\n\n{$text}";

            $response = Http::timeout(120)->post("{$this->baseUrl}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return trim($data['response'] ?? '');
            }

            Log::error('Ollama API error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Ollama connection failed: ' . $e->getMessage());
            return null;
        }
    }
}
