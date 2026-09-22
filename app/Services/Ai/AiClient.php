<?php

namespace App\Services\Ai;

use App\Models\AiGeneration;
use App\Support\AutomationLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tek AI istemcisi — OpenAI uyumlu sohbet tamamlama (spec §8.1).
 *
 * Varsayılan sağlayıcı OpenRouter'dır; temel adres ve model .env / panel ayarından
 * gelir, bu yüzden OpenAI, Groq, DeepSeek veya yerel bir sunucu da aynı istemciyle
 * çalışır.
 *
 * API ANAHTARI asla log'a, veritabanına, URL'ye veya hata mesajına yazılmaz (spec §10.2).
 */
class AiClient
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
        protected ?string $model = null,
    ) {
        $this->apiKey = $apiKey ?? config('seo.ai.api_key');
        $this->baseUrl = rtrim($baseUrl ?? config('seo.ai.base_url'), '/');
        $this->model = $model ?? config('seo.ai.model');
    }

    /** Anahtar yoksa AI aksiyonları panelde gizlenir, sistem çökmez (spec §10.5). */
    public function isConfigured(): bool
    {
        return filled($this->apiKey) && filled($this->baseUrl) && filled($this->model);
    }

    public function model(): string
    {
        return (string) $this->model;
    }

    /**
     * JSON çıktı bekleyen bir çağrı yapar. Bozuk JSON gelirse modele hatayı
     * söyleyerek BİR KEZ yeniden dener (spec §3.3).
     *
     * @param  array<string, mixed>  $meta  ai_generations denetim kaydına yazılacak bağlam
     * @return array<string, mixed>
     */
    public function json(string $operation, string $systemPrompt, string $userPrompt, array $meta = []): array
    {
        $generation = $this->startAudit($operation, $systemPrompt, $userPrompt, $meta);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $lastError = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $content = $this->chat($messages, json: true);
                $decoded = $this->decodeJson($content);

                $this->finishAudit($generation, 'success', $content);

                return $decoded;
            } catch (AiException $e) {
                $lastError = $e;

                if ($attempt === 2) {
                    break;
                }

                // Modele ne bozulduğunu söyleyerek tek bir düzeltme şansı ver.
                $messages[] = ['role' => 'assistant', 'content' => $e->getMessage()];
                $messages[] = [
                    'role' => 'user',
                    'content' => 'Önceki yanıt geçerli JSON değildi: '.$e->getMessage().
                        ' Lütfen SADECE istenen şemaya uyan geçerli bir JSON nesnesi döndür. Açıklama, markdown veya kod bloğu ekleme.',
                ];
            } catch (Throwable $e) {
                $this->finishAudit($generation, 'failed', null, $e->getMessage());

                throw new AiException('AI çağrısı başarısız: '.$e->getMessage(), previous: $e);
            }
        }

        $message = $lastError?->getMessage() ?? 'bilinmeyen hata';
        $this->finishAudit($generation, 'failed', null, $message);

        throw new AiException('AI geçerli JSON döndürmedi: '.$message);
    }

    /** Düz metin yanıt bekleyen çağrı. */
    public function text(string $operation, string $systemPrompt, string $userPrompt, array $meta = []): string
    {
        $generation = $this->startAudit($operation, $systemPrompt, $userPrompt, $meta);

        try {
            $content = $this->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ]);

            $this->finishAudit($generation, 'success', $content);

            return $content;
        } catch (Throwable $e) {
            $this->finishAudit($generation, 'failed', null, $e->getMessage());

            throw new AiException('AI çağrısı başarısız: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Sağlayıcıdaki model listesi (OpenRouter /models). Panelde açılır liste doldurur.
     * Ulaşılamazsa boş dizi döner; sayfa serbest metin girişine düşer.
     *
     * @return array<string, string>
     */
    public function availableModels(): array
    {
        return Cache::remember('ai.models.'.md5((string) $this->baseUrl), now()->addHours(6), function () {
            try {
                $response = $this->request()->get($this->baseUrl.'/models');

                if (! $response->successful()) {
                    return [];
                }

                $models = [];

                foreach ($response->json('data') ?? [] as $row) {
                    $id = $row['id'] ?? null;

                    if (! is_string($id) || $id === '') {
                        continue;
                    }

                    $models[$id] = $row['name'] ?? $id;
                }

                ksort($models);

                return $models;
            } catch (Throwable $e) {
                AutomationLog::error('ai.models', $e->getMessage());

                return [];
            }
        });
    }

    /** @param array<int, array{role: string, content: string}> $messages */
    protected function chat(array $messages, bool $json = false): string
    {
        if (! $this->isConfigured()) {
            throw new AiException('AI yapılandırılmamış: API anahtarı veya model eksik.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => (float) config('seo.ai.temperature', 0.7),
            'max_tokens' => (int) config('seo.ai.max_tokens', 8000),
        ];

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->request()->post($this->baseUrl.'/chat/completions', $payload);

        if (! $response->successful()) {
            // Sağlayıcı hata gövdesi anahtar içerebilir; yalnız durum ve kısa mesaj taşınır.
            $detail = (string) ($response->json('error.message') ?? '');

            throw new AiException('HTTP '.$response->status().($detail !== '' ? ': '.Str::limit($detail, 300) : ''));
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new AiException('Model boş yanıt döndürdü.');
        }

        return trim($content);
    }

    protected function request(): PendingRequest
    {
        $headers = ['Authorization' => 'Bearer '.$this->apiKey];

        // OpenRouter'ın önerdiği kimlik başlıkları; diğer sağlayıcılar yok sayar.
        if ($referer = config('seo.ai.referer')) {
            $headers['HTTP-Referer'] = (string) $referer;
        }

        if ($title = config('seo.ai.title')) {
            $headers['X-Title'] = (string) $title;
        }

        return Http::withHeaders($headers)
            ->timeout((int) config('seo.ai.timeout', 180))
            ->connectTimeout(20)
            ->acceptJson();
    }

    /** @return array<string, mixed> */
    protected function decodeJson(string $content): array
    {
        // Model bazen ```json ... ``` bloğuna sarar.
        if (preg_match('/```(?:json)?\s*(.+?)```/s', $content, $m)) {
            $content = $m[1];
        }

        $content = trim($content);

        // Baş/son metni kırp: ilk { ile son } arası.
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $content = substr($content, $start, $end - $start + 1);
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AiException(json_last_error_msg());
        }

        return $decoded;
    }

    protected function startAudit(string $operation, string $system, string $user, array $meta): ?AiGeneration
    {
        try {
            return AiGeneration::create([
                'operation' => $operation,
                'content_type' => $meta['content_type'] ?? null,
                'content_id' => $meta['content_id'] ?? null,
                'batch_key' => $meta['batch_key'] ?? null,
                'model' => (string) $this->model,
                'status' => AiGeneration::STATUS_RUNNING,
                // Prompt'lar denetim için saklanır; API anahtarı burada YER ALMAZ.
                'input' => ['system' => $system, 'user' => $user] + Arr::except($meta, ['system', 'user']),
            ]);
        } catch (Throwable $e) {
            AutomationLog::error('ai.audit', $e->getMessage(), ['operation' => $operation]);

            return null;
        }
    }

    protected function finishAudit(?AiGeneration $generation, string $status, ?string $output, ?string $error = null): void
    {
        if (! $generation) {
            return;
        }

        try {
            $generation->update([
                'status' => $status,
                'output' => $output,
                'error' => $error,
                'duration_ms' => (int) $generation->created_at->diffInMilliseconds(now()),
            ]);
        } catch (Throwable $e) {
            AutomationLog::error('ai.audit', $e->getMessage(), ['generation_id' => $generation->getKey()]);
        }
    }
}
