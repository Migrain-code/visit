@php
    $variants = app(\App\Services\Media\ImageVariants::class);
    $thumb = filled($item->image) ? $variants->url($item->image, 640, 480) : $item->image_url;
@endphp
<a class="gallery-item {{ $class ?? '' }}" href="{{ $item->image_url }}" data-full="{{ $item->image_url }}" data-title="{{ $item->title }}" data-alt="{{ $item->alt }}" data-category="{{ $item->category?->slug ?? 'diger' }}">
    <img src="{{ $thumb }}" alt="{{ $item->alt }}" loading="lazy" decoding="async" width="640" height="480">
    <span class="caption">{{ $item->title }}</span>
</a>
