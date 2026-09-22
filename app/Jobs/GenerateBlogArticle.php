<?php

namespace App\Jobs;

use App\Models\BlogCategory;
use App\Services\Ai\ArticleGenerator;
use App\Support\AutomationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Yazı üretimi KUYRUKTA koşar: tek bir AI çağrısı dakikalar sürebilir ve
 * cron turunu bloklamamalıdır (spec §0, §8.1).
 */
class GenerateBlogArticle implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    /** @param array<string, mixed> $topic */
    public function __construct(
        public int $categoryId,
        public array $topic,
        public ?string $publishAt = null,
    ) {}

    public function handle(ArticleGenerator $generator): void
    {
        $category = BlogCategory::find($this->categoryId);

        if (! $category) {
            AutomationLog::summary('blog.article', ['category_id' => $this->categoryId], reasons: ['no_candidates']);

            return;
        }

        $generator->generate(
            $category,
            $this->topic,
            $this->publishAt ? Carbon::parse($this->publishAt) : null,
        );
    }

    public function failed(Throwable $e): void
    {
        AutomationLog::error('blog.article', $e->getMessage(), [
            'category_id' => $this->categoryId,
            'title' => $this->topic['title'] ?? null,
        ]);
    }
}
