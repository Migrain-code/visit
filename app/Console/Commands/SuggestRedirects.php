<?php

namespace App\Console\Commands;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\Seo\RedirectSuggester;
use App\Support\AutomationLog;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use Illuminate\Console\Command;

class SuggestRedirects extends Command
{
    protected $signature = 'seo:suggest-redirects {--apply : Yüksek güvenli önerileri otomatik uygula}';

    protected $description = 'Çözülmemiş 404 kayıtlarına yönlendirme önerir';

    public function handle(RedirectSuggester $suggester): int
    {
        $result = $suggester->fillSuggestions();
        $applied = 0;

        $autoApply = $this->option('apply') || SeoConfig::bool('redirects_auto_apply', (bool) config('seo.redirects.auto_apply', false));

        if ($autoApply) {
            // Yalnız çok yüksek güvenli öneriler otomatik uygulanır.
            $candidates = NotFoundLog::query()
                ->unresolved()
                ->whereNotNull('suggested_path')
                ->where('suggestion_score', '>=', 0.9)
                ->get();

            foreach ($candidates as $log) {
                Redirect::updateOrCreate(
                    ['from_hash' => PathNormalizer::hash($log->path)],
                    [
                        'from_path' => $log->path,
                        'to_path' => $log->suggested_path,
                        'status_code' => 301,
                        'is_active' => true,
                        'source' => Redirect::SOURCE_SUGGESTION,
                        'confidence' => $log->suggestion_score,
                    ],
                );

                $log->forceFill(['resolved' => true])->saveQuietly();
                $applied++;
            }
        }

        AutomationLog::summary('redirects.suggest', $result + ['applied' => $applied],
            reasons: $result['suggested'] === 0 ? ['no_candidates'] : [],
            changed: $applied > 0);

        $this->info("{$result['scanned']} kayıt tarandı, {$result['suggested']} öneri bulundu, {$applied} tanesi uygulandı.");

        return self::SUCCESS;
    }
}
