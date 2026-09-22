<section class="page-banner">
    {{-- Bant alçak (≈300 px): mobil kırpım yatay, masaüstü boyları küçük. --}}
    <x-bg-picture :path="$bannerImage ?? setting('banner_image')" :fallback="asset('images/placeholder.svg')" :mobile="[800, 600]" :widths="[1280, 1920]" :priority="true" />
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                @foreach ($breadcrumbs as $crumb)
                    @if ($loop->last)
                        <li class="breadcrumb-item active" aria-current="page">{{ $crumb['name'] }}</li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a></li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h1>{{ $title }}</h1>
        @if (! empty($subtitle))
            <p class="lead">{{ $subtitle }}</p>
        @endif
        @if (! empty($badges))
            <div class="banner-meta">
                @foreach ($badges as [$icon, $label])
                    <span class="pill"><i class="{{ $icon }}"></i>{{ $label }}</span>
                @endforeach
            </div>
        @endif
    </div>
</section>
