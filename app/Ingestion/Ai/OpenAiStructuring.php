<?php

namespace App\Ingestion\Ai;

use App\Contracts\AiStructuring;
use App\Models\Template;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenAI-based structuring. Given OCR text and a template's field definitions,
 * asks the model to return strict JSON of field key => value for the fields NOT
 * already resolved from the CFDI XML. Output is validated against the template's
 * fields; anything unexpected is dropped.
 *
 * XML always wins — this only fills gaps. Runs only when the template opts in
 * (ai_enabled) and OCR text is present.
 */
class OpenAiStructuring implements AiStructuring
{
    private ?string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $timeout;
    private int $maxInputChars;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey        = config('ai.openai.api_key');
        $this->baseUrl       = rtrim(config('ai.openai.base_url'), '/');
        $this->model         = config('ai.openai.model', 'gpt-4o-mini');
        $this->timeout       = (int) config('ai.openai.timeout', 60);
        $this->maxInputChars = (int) config('ai.max_input_chars', 12000);
        $this->temperature   = (float) config('ai.temperature', 0.0);
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function structure(string $text, Template $template, array $known = []): array
    {
        if (! $this->isAvailable() || trim($text) === '') {
            return [];
        }

        // Only ask for fields not already resolved from the XML.
        $missingFields = $template->fields->filter(
            fn ($f) => ! array_key_exists($f->key, $known) || $known[$f->key] === null || $known[$f->key] === ''
        )->values();

        if ($missingFields->isEmpty()) {
            return []; // XML covered everything — no AI call, no cost.
        }

        $text = mb_substr($text, 0, $this->maxInputChars);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'           => $this->model,
                    'temperature'     => $this->temperature,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($text, $missingFields)],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI structuring HTTP error', ['status' => $response->status(), 'body' => $response->body()]);
                return [];
            }

            $content = data_get($response->json(), 'choices.0.message.content');
            if (! is_string($content)) {
                return [];
            }

            return $this->validate($this->decode($content), $missingFields);
        } catch (Throwable $e) {
            Log::warning('OpenAI structuring failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'Eres un extractor de datos de documentos en español.',
            'Extrae únicamente los campos solicitados a partir del texto del documento.',
            'Devuelve SOLO un objeto JSON válido, sin explicaciones ni markdown.',
            'Si un campo no aparece en el texto, usa una cadena vacía "".',
            'No inventes valores.',
        ]);
    }

    /** Build a user prompt describing exactly which keys/types to return. */
    private function userPrompt(string $text, $fields): string
    {
        $spec = $fields->map(function ($f) {
            $typeHint = match ($f->type) {
                'currency', 'number' => 'número',
                'date'               => 'fecha (YYYY-MM-DD)',
                'select'             => 'una de: ' . implode(', ', $f->options ?? []),
                default              => 'texto',
            };
            return "- \"{$f->key}\": {$f->label} ({$typeHint})";
        })->implode("\n");

        $keys = $fields->pluck('key')->map(fn ($k) => "\"{$k}\"")->implode(', ');

        return <<<PROMPT
        Campos a extraer (clave: descripción):
        {$spec}

        Devuelve un JSON con exactamente estas claves: {$keys}.

        Texto del documento:
        ---
        {$text}
        ---
        PROMPT;
    }

    /** Decode the model's JSON, tolerating accidental markdown fences. */
    private function decode(string $content): array
    {
        $content = trim($content);
        // Strip ```json ... ``` fences if the model added them despite instructions.
        $content = preg_replace('/^```(?:json)?|```$/m', '', $content);
        $decoded = json_decode(trim($content), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Keep only expected keys with non-empty scalar values, and enforce select
     * options. Everything else is discarded (no hallucinated fields written).
     *
     * @return array<string,string>
     */
    private function validate(array $decoded, $fields): array
    {
        $out = [];
        $byKey = $fields->keyBy('key');

        foreach ($decoded as $key => $value) {
            $field = $byKey->get($key);
            if (! $field) {
                continue; // unexpected key
            }
            if (! is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            // For selects, only accept a listed option.
            if ($field->type === 'select' && ! in_array($value, $field->options ?? [], true)) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
