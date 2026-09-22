<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const CACHE_KEY = 'site_settings';

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }

    /**
     * İstek boyunca ayarların tutulduğu kapsayıcı anahtarı.
     *
     * Önbellek sürücüsü "database" olduğunda her Cache::get bir SQL sorgusudur.
     * setting() bir sayfada yüzden fazla kez çağrılıyordu: ana sayfa yalnız ayar
     * okumak için 104 sorgu atıyordu. Ayarlar artık istek başına BİR kez okunur.
     * "scoped" kayıt: her kuyruk işinin başında da tazelenir.
     */
    public const MEMO = 'settings.memo';

    /**
     * @return array<string, string|null>
     */
    public static function allCached(): array
    {
        $container = app();

        return $container->bound(static::MEMO)
            ? $container->make(static::MEMO)
            : static::loadFromStore();
    }

    /** @return array<string, string|null> */
    public static function loadFromStore(): array
    {
        try {
            return Cache::rememberForever(static::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            return [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::allCached()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? config('site.'.$key);
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value]);
    }

    public static function flush(): void
    {
        Cache::forget(static::CACHE_KEY);
        app()->forgetInstance(static::MEMO);
    }
}
