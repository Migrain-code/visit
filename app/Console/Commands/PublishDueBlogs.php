<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Illuminate\Console\Command;

class PublishDueBlogs extends Command
{
    protected $signature = 'blog:publish-due';

    protected $description = 'Yayın zamanı gelmiş taslak blog yazılarını yayınlar';

    public function handle(): int
    {
        if (! SeoConfig::bool('blog_auto_publish', (bool) config('seo.blog.auto_publish', true))) {
            AutomationLog::summary('blog.publish', ['enabled' => false], reasons: ['disabled']);

            return self::SUCCESS;
        }

        $due = Blog::query()->due()->get();

        if ($due->isEmpty()) {
            // Değişiklik olmasa bile SATIR YAZILIR: "hiç koşmadı" ile "koştu, iş yoktu"
            // ayrımı teşhisin yarısıdır (spec §6).
            AutomationLog::summary('blog.publish', ['published' => 0], reasons: ['nothing_due']);

            return self::SUCCESS;
        }

        foreach ($due as $blog) {
            $blog->update(['status' => Blog::STATUS_PUBLISHED]);
            $this->line('Yayınlandı: '.$blog->title);
        }

        AutomationLog::summary('blog.publish', [
            'published' => $due->count(),
            'titles' => $due->pluck('title')->all(),
        ], changed: true);

        $this->info($due->count().' yazı yayınlandı.');

        return self::SUCCESS;
    }
}
