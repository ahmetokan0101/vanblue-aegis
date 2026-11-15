<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin client for the OpenAI-compatible Gemini endpoint (text + vision).
 */
class GeminiClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    public function model(): string
    {
        return (string) config('services.gemini.model');
    }

    /**
     * @param  array<int, array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     */
    public function chat(array $messages, int $maxTokens = 1600, float $temperature = 0.2): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken(config('services.gemini.api_key'))
                ->timeout(config('services.gemini.timeout'))
                ->acceptJson()
                ->post(rtrim(config('services.gemini.base_url'), '/').'/chat/completions', [
                    'model' => $this->model(),
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? (string) $response->json('choices.0.message.content') : null;
    }

    /**
     * Builds a multimodal user message (text + optional base64 data-URL image).
     *
     * @return array{role: string, content: string|array<int, array<string, mixed>>}
     */
    public function userMessage(string $text, ?string $imageDataUrl = null): array
    {
        if ($imageDataUrl === null) {
            return ['role' => 'user', 'content' => $text];
        }

        return ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => $text],
            ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]],
        ]];
    }

    /**
     * Extracts a JSON object from a model reply (tolerates code fences and leading prose).
     *
     * @return array<string, mixed>|null
     */
    public function decodeJson(?string $content): ?array
    {
        if ($content === null) {
            return null;
        }

        $json = Str::of($content)->replaceMatches('/^```(?:json)?|```$/m', '')->trim()->toString();

        if (! str_starts_with($json, '{') && preg_match('/\{.*\}/s', $json, $match)) {
            $json = $match[0];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }
}
