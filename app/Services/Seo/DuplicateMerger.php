<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\Redirect;
use App\Models\SeoKeyword;
use App\Support\AutomationLog;
use App\Support\PathNormalizer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Çakışan iki yazıyı birleştirir (spec §3.5).
 *
 * Ne yapar:
 *   1. 301 yönlendirme oluşturur (kaybeden → kazanan)
 *   2. Kaybedeni YAYINDAN KALDIRIR (status = 0) — ASLA SİLMEZ (spec §7.7)
 *   3. Hepsi TEK TRANSACTION: yönlendirme yazılamazsa yazı da yayında kalır,
 *      yoksa 404 üretirsin (spec §3.5)
 *
 * İDEMPOTENT: buton iki kez tıklanabilir, ikinci çağrı hata değil no-op'tur.
 */
class DuplicateMerger
{
    /** @return array{merged: bool, reason: ?string, redirect_id: ?int} */
    public function merge(Blog $winner, Blog $loser): array
    {
        if ($winner->is($loser)) {
            throw new RuntimeException('Bir yazı kendisiyle birleştirilemez.');
        }

        // İdempotans: zaten birleştirilmişse no-op.
        if ($loser->merged_into_id === $winner->getKey()) {
            return ['merged' => false, 'reason' => 'already_merged', 'redirect_id' => null];
        }

        // HEDEF YAYINDA DEĞİLSE REDDET — yoksa 301 zinciri 404'e gider (spec §3.5).
        if (! $winner->is_published) {
            throw new RuntimeException(
                'Hedef yazı yayında değil: "'.$winner->title.'". '.
                'Yayında olmayan bir yazıya yönlendirmek 404 üretir. Önce hedefi yayınlayın.'
            );
        }

        $redirect = null;

        DB::transaction(function () use ($winner, $loser, &$redirect) {
            $redirect = Redirect::updateOrCreate(
                ['from_hash' => PathNormalizer::hash($loser->path())],
                [
                    'from_path' => $loser->path(),
                    'to_path' => $winner->path(),
                    'status_code' => 301,
                    'is_active' => true,
                    'source' => Redirect::SOURCE_DUPLICATE_MERGE,
                    'note' => 'Çakışma birleştirme: #'.$loser->getKey().' → #'.$winner->getKey(),
                ],
            );

            // SİLME YOK: yayından kaldır ve izini bırak (spec §7.7, §10.9).
            $loser->forceFill([
                'status' => Blog::STATUS_DRAFT,
                'merged_into_id' => $winner->getKey(),
                'merged_at' => now(),
            ])->save();

            // Kaybedene ait kelime sahipliği kazanana devredilir.
            SeoKeyword::query()
                ->where('owner_blog_id', $loser->getKey())
                ->update(['owner_blog_id' => $winner->getKey()]);
        });

        AutomationLog::summary('blog.merge', [
            'winner' => $winner->getKey(),
            'winner_title' => $winner->title,
            'loser' => $loser->getKey(),
            'loser_title' => $loser->title,
            'redirect_id' => $redirect?->getKey(),
        ], changed: true);

        return ['merged' => true, 'reason' => null, 'redirect_id' => $redirect?->getKey()];
    }

    /**
     * Birleştirmeyi geri alır: yazıyı tekrar yayınlar, yönlendirmeyi pasife alır.
     * Karar yanlışsa geri dönüş MÜMKÜN olmalıdır (spec §7.7, §10.10).
     */
    public function undo(Blog $loser): bool
    {
        if (! $loser->merged_into_id) {
            return false;
        }

        DB::transaction(function () use ($loser) {
            Redirect::query()
                ->where('from_hash', PathNormalizer::hash($loser->path()))
                ->where('source', Redirect::SOURCE_DUPLICATE_MERGE)
                ->update(['is_active' => false]);

            $loser->forceFill([
                'status' => Blog::STATUS_PUBLISHED,
                'merged_into_id' => null,
                'merged_at' => null,
            ])->save();
        });

        AutomationLog::summary('blog.merge_undo', ['blog' => $loser->getKey()], changed: true);

        return true;
    }
}
