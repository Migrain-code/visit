<?php

namespace App\Services\Seo;

/**
 * Bir konu adayının filtre sonucu.
 *
 * Reddedilen her aday GEREKÇESİYLE taşınır. Sessiz eleme, "blog üretilmiyor"
 * şikâyetini teşhis edilemez yapar (spec §3.4).
 */
class TopicDecision
{
    private function __construct(
        public bool $accepted,
        public ?string $reasonCode = null,
        public ?string $reason = null,
        public ?string $conflictWith = null,
        public ?float $score = null,
    ) {}

    public static function accept(): self
    {
        return new self(true);
    }

    public static function reject(string $code, string $reason, ?string $conflictWith = null, ?float $score = null): self
    {
        return new self(false, $code, $reason, $conflictWith, $score);
    }

    /** @return array<string, mixed> */
    public function toLog(string $title): array
    {
        return array_filter([
            'title' => $title,
            'reason_code' => $this->reasonCode,
            'reason' => $this->reason,
            'conflict_with' => $this->conflictWith,
            'score' => $this->score,
        ], fn ($v) => $v !== null);
    }
}
