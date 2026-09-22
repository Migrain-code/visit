@props(['subtitle' => null, 'title', 'text' => null, 'center' => false, 'light' => false])
<div {{ $attributes->class(['section-title', 'text-center' => $center, 'light' => $light]) }}>
    @if ($subtitle)
        <span class="eyebrow">{{ $subtitle }}</span>
    @endif
    <h2>{{ $title }}</h2>
    @if ($text)
        <p>{{ $text }}</p>
    @endif
</div>
