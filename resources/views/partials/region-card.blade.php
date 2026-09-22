<div class="region-card">
    <span class="count">{{ $province->activeDistricts->count() }} ilçeden katılım</span>
    <h3><a href="{{ $province->url }}">{{ $province->name }} çıkışlı turlar</a></h3>
    @if ($province->description)
        <p>{{ $province->description }}</p>
    @endif
    <div class="chips mb-3">
        @foreach ($province->activeDistricts as $district)
            <a class="chip" href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a>
        @endforeach
    </div>
    <a class="link-arrow" href="{{ $province->url }}">{{ $province->name }} kalkış bilgileri <i class="fa-solid fa-arrow-right"></i></a>
</div>
