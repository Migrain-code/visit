<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * "Varsa sayacı artır, yoksa oluştur" — gözlem tabloları için ortak yardımcı.
 *
 * Yarış durumunda (iki istek aynı anda) unique çakışması yakalanır ve artırmaya
 * düşülür; kayıt ASLA kaybolmaz ve istek bozulmaz.
 */
trait RecordsHits
{
    protected static function incrementOrCreate(string $hashColumn, string $hash, array $attributes, array $touch = []): ?Model
    {
        $update = $touch + ['hits' => DB::raw('hits + 1'), 'updated_at' => now()];

        $affected = static::query()->where($hashColumn, $hash)->update($update);

        if ($affected > 0) {
            return static::query()->where($hashColumn, $hash)->first();
        }

        try {
            return static::query()->create($attributes + [$hashColumn => $hash]);
        } catch (QueryException) {
            // Araya başka bir istek girdi: artırmaya düş.
            static::query()->where($hashColumn, $hash)->update($update);

            return static::query()->where($hashColumn, $hash)->first();
        }
    }
}
