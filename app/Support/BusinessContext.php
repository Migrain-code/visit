<?php

namespace App\Support;

use App\Models\Province;

/**
 * AI istemlerine verilen iş tanımı ve bölge bağlamı.
 *
 * Koda gömülü şehir adı YOKTUR: bölgeler panelde aktif olan il/ilçelerden, iş tanımı
 * site ayarlarından gelir. Firma başka bir şehre taşınsa ya da yeni kalkış noktası
 * açsa istemler kendiliğinden güncellenir.
 */
class BusinessContext
{
    /** "Rize ve Trabzon çıkışlı günübirlik turlar düzenleyen bir seyahat acentesi" */
    public static function describe(): string
    {
        $custom = trim((string) setting('ai_business_context'));

        if ($custom !== '') {
            return $custom;
        }

        $provinces = static::provinceNames();

        return ($provinces !== [] ? static::join($provinces).' çıkışlı ' : '')
            .'günübirlik yayla, göl, kültür ve Batum turları düzenleyen yerel bir seyahat acentesi';
    }

    /** "Rize (Rize Merkez, Ardeşen, ...), Trabzon (Ortahisar, ...)" */
    public static function regions(int $districtsPerProvince = 6): string
    {
        return Province::query()->active()->ordered()->with('activeDistricts:id,province_id,name')->get()
            ->map(function (Province $province) use ($districtsPerProvince) {
                $districts = $province->activeDistricts->take($districtsPerProvince)->pluck('name')->implode(', ');

                return $province->name.($districts !== '' ? ' ('.$districts.')' : '');
            })
            ->implode(', ');
    }

    /** @return array<int, string> */
    public static function provinceNames(): array
    {
        return Province::query()->active()->ordered()->pluck('name')->all();
    }

    /** @param array<int, string> $items "A, B ve C" */
    public static function join(array $items): string
    {
        $items = array_values(array_filter($items));

        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }

        $last = array_pop($items);

        return implode(', ', $items).' ve '.$last;
    }
}
