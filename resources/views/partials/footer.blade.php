@php
    $phone = site_phone();
    $email = setting('email');
    $socials = array_filter([
        'facebook' => setting('facebook_url'),
        'instagram' => setting('instagram_url'),
        'youtube' => setting('youtube_url'),
    ]);
@endphp
<footer class="footer">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4 col-md-6">
                <a class="footer-brand d-inline-block" href="{{ route('home') }}">
                    <img src="{{ versioned_asset('images/brand/logo-light.svg') }}" alt="{{ site_name() }}" width="203" height="52" loading="lazy">
                </a>
                <p>{{ setting('footer_text') }}</p>

                {{-- Seyahat acentelerinin belge numarasını sitede göstermesi yasal zorunluluktur. --}}
                @if (setting('tursab_no') || setting('company_title'))
                    <div class="license">
                        @if (setting('company_title'))
                            <div>{{ setting('company_title') }}</div>
                        @endif
                        @if (setting('tursab_no'))
                            <div>TÜRSAB Belge No: <strong>{{ setting('tursab_no') }}</strong></div>
                        @endif
                    </div>
                @endif

                @if ($socials)
                    <div class="footer-social">
                        @foreach ($socials as $network => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><i class="fa-brands fa-{{ $network }}"></i></a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h4>Turlar</h4>
                <ul class="footer-links">
                    @foreach ($navCategories as $category)
                        <li><a href="{{ $category->url }}">{{ $category->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('tours.index') }}">Tüm turlar</a></li>
                    <li><a href="{{ route('tours.calendar') }}">Tur takvimi</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <h4>Kalkış Noktaları</h4>
                <ul class="footer-links">
                    @foreach ($navProvinces as $province)
                        <li><a href="{{ $province->url }}">{{ $province->name }} çıkışlı turlar</a></li>
                        @foreach ($province->activeDistricts->take(2) as $district)
                            <li><a href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a></li>
                        @endforeach
                    @endforeach
                    <li><a href="{{ route('regions.index') }}">Tüm kalkış noktaları</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h4>İletişim</h4>
                <ul class="footer-contact">
                    @if ($phone)
                        <li><i class="fa-solid fa-phone"></i><a href="{{ phone_href() }}">{{ $phone }}</a></li>
                    @endif
                    <li><i class="fa-brands fa-whatsapp"></i><a href="{{ whatsapp_url() }}" target="_blank" rel="noopener">WhatsApp'tan yazın</a></li>
                    @if ($email)
                        <li><i class="fa-regular fa-envelope"></i><!--email_off--><a href="mailto:{{ $email }}">{{ $email }}</a><!--/email_off--></li>
                    @endif
                    @if (setting('address'))
                        <li><i class="fa-solid fa-location-dot"></i><span>{{ setting('address') }}</span></li>
                    @endif
                    @if (setting('working_hours'))
                        <li><i class="fa-regular fa-clock"></i><span>{{ setting('working_hours') }}</span></li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="footer-bottom d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span>© {{ date('Y') }} {{ site_name() }}. Tüm hakları saklıdır.</span>
            <ul class="list-inline mb-0">
                <li class="list-inline-item"><a href="{{ route('about') }}">Hakkımızda</a></li>
                <li class="list-inline-item"><a href="{{ route('faq') }}">S.S.S.</a></li>
                @foreach ($footerPages as $page)
                    <li class="list-inline-item"><a href="{{ $page->url }}">{{ $page->title }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</footer>
