{{--
    Arka plan görseli, CSS yerine HTML <picture> olarak.

    Neden: CSS arka planı tarayıcı tarafından ancak stil dosyası indirilip işlendikten
    sonra fark edilir. Sayfanın en büyük öğesi (LCP) bu görsel olduğu için tüm sayfa
    onu bekliyordu. HTML'deki <img> ilk anda keşfedilir; "priority" ile en önce iner.

    Mobilde görselin üstünde %80-90 koyu katman var: küçük ve düşük kaliteli bir
    kırpım yeterli. Masaüstü genişliğe göre iki boydan birini seçer.
--}}
@props([
    'path' => null,
    'fallback' => null,
    'mobile' => [720, 960],
    'widths' => [1280, 1920],
    'priority' => false,
])
@php
    $variants = app(\App\Services\Media\ImageVariants::class);
    $original = media_url($path, $fallback);
    $isVector = $original !== null && str_ends_with(strtolower(strtok($original, '?')), '.svg');
    $usable = $original !== null && ! $isVector && filled($path);
@endphp
@if ($original)
    <picture {{ $attributes->class('bg-picture') }}>
        @if ($usable)
            <source media="(max-width: 767.98px)" srcset="{{ $variants->url($path, $mobile[0], $mobile[1], 50) }}">
            <img src="{{ $variants->url($path, $widths[0]) }}"
                 srcset="{{ collect($widths)->map(fn ($w) => $variants->url($path, $w).' '.$w.'w')->implode(', ') }}"
                 sizes="100vw"
                 alt=""
                 @if ($priority) fetchpriority="high" @else loading="lazy" @endif
                 decoding="async">
        @else
            <img src="{{ $original }}" alt="" @if ($priority) fetchpriority="high" @else loading="lazy" @endif decoding="async">
        @endif
    </picture>
@endif
