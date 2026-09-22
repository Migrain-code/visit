<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\District;
use App\Models\InternalLinkRule;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Services\InternalLink\LinkApplier;
use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Onaylı iç link kurallarını yayındaki içeriklerin gövdesine KALICI olarak işler.
 *
 * Not: render anında uygulama her zaman çalışır (LinkApplier). Bu komut, linkin
 * veritabanındaki metne de yazılmasını sağlar — SEO skoru gövdedeki bağlamsal
 * linkleri ölçtüğü için bu gerekir.
 */
class ApplyInternalLinks extends Command
{
    protected $signature = 'links:apply
                            {--force : Otomatik uygulama kapalı olsa da uygula}
                            {--type=* : blog|tour|category|district|province (boş bırakılırsa hepsi)}';

    protected $description = 'İç link kurallarını yayındaki içeriklerin gövdesine işler';

    public function handle(LinkApplier $applier): int
    {
        if (! $applier->enabled()) {
            AutomationLog::summary('links.apply', ['enabled' => false], reasons: ['disabled']);
            $this->warn('İç link motoru kapalı (SEO & AI Ayarları → İç link motoru).');

            return self::SUCCESS;
        }

        $auto = SeoConfig::bool('internal_links_auto_apply', (bool) config('seo.internal_links.auto_apply', false));

        if (! $auto && ! $this->option('force')) {
            AutomationLog::summary('links.apply', ['auto_apply' => false], reasons: ['disabled']);
            $this->warn('Gövdeye kalıcı işleme kapalı. Render anında uygulama çalışmaya devam ediyor.');

            return self::SUCCESS;
        }

        if (InternalLinkRule::query()->active()->doesntExist()) {
            AutomationLog::summary('links.apply', ['rules' => 0], reasons: ['no_candidates']);
            $this->warn('Aktif iç link kuralı yok. Panel → SEO & AI → İç Link Önerileri ekranından onaylayın.');

            return self::SUCCESS;
        }

        $batch = SeoConfig::int('internal_links_batch_size', (int) config('seo.internal_links.batch_size', 25), 1, 500);
        $types = (array) $this->option('type') ?: ['blog', 'tour', 'category', 'district', 'province'];

        $scanned = 0;
        $changed = 0;

        foreach ($this->targets($types, $batch) as $scope => $rows) {
            foreach ($rows as [$model, $field, $selfUrl]) {
                $scanned++;

                $before = (string) $model->{$field};

                if (trim(strip_tags($before)) === '') {
                    continue;
                }

                $after = $applier->apply($before, $scope, $selfUrl);

                if ($after !== $before) {
                    $model->forceFill([$field => $after])->saveQuietly();
                    $changed++;
                    $this->line('  '.$scope.': '.Str::limit($selfUrl, 50));
                }
            }
        }

        AutomationLog::summary('links.apply', [
            'scanned' => $scanned,
            'changed' => $changed,
            'types' => $types,
        ], reasons: $changed === 0 ? ['unchanged'] : [], changed: $changed > 0);

        $this->info("{$scanned} içerik tarandı, {$changed} tanesi güncellendi.");

        return self::SUCCESS;
    }

    /**
     * Yayındaki içerikler: [kapsam => [[model, gövde alanı, kendi adresi], ...]]
     *
     * Kendi adresi verilir ki sayfa KENDİNE link vermesin.
     *
     * @return array<string, array<int, array{0: Model, 1: string, 2: string}>>
     */
    private function targets(array $types, int $batch): array
    {
        $out = [];

        if (in_array('blog', $types, true)) {
            $out['blog'] = Blog::query()->published()->orderBy('updated_at')->limit($batch)->get()
                ->map(fn (Blog $b) => [$b, 'body_html', $b->path()])->all();
        }

        if (in_array('tour', $types, true)) {
            $out['tour'] = Tour::query()->where('is_active', true)->limit($batch)->get()
                ->map(fn (Tour $t) => [$t, 'description', '/'.$t->slug])->all();
        }

        if (in_array('category', $types, true)) {
            $out['category'] = TourCategory::query()->where('is_active', true)->limit($batch)->get()
                ->map(fn (TourCategory $c) => [$c, 'content', $c->path()])->all();
        }

        if (in_array('district', $types, true)) {
            $out['district'] = District::query()->where('is_active', true)->with('province')->limit($batch)->get()
                ->filter(fn (District $d) => (bool) $d->province?->is_active)
                ->map(fn (District $d) => [$d, 'content', '/'.$d->province->slug.'/'.$d->slug])->values()->all();
        }

        if (in_array('province', $types, true)) {
            $out['province'] = Province::query()->where('is_active', true)->limit($batch)->get()
                ->map(fn (Province $p) => [$p, 'content', '/'.$p->slug])->all();
        }

        return $out;
    }
}
