@php
    $phone = site_phone();
    $email = setting('email');
@endphp
<footer class="footer">
    <div class="container app-shell">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">{{ site_name() }}</div>
                <p class="footer-tagline">{{ setting('site_tagline', 'Keşfet · Tanış · Yaşa') }}</p>
                @if (instagram_url())
                    <a class="footer-instagram" href="{{ instagram_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i>{{ instagram_handle() }}</a>
                @endif
            </div>
            <div>
                <h4>Sayfalar</h4>
                <ul class="footer-links">
                    <li><a href="{{ route('home') }}">Ana Sayfa</a></li>
                    <li><a href="{{ route('home') }}#turlar">Turlar</a></li>
                    <li><a href="{{ route('contact') }}">İletişim</a></li>
                    <li><a href="{{ route('jobs.create') }}">İş Başvurusu</a></li>
                </ul>
            </div>
            <div>
                <h4>İletişim</h4>
                <ul class="footer-contact">
                    @if ($phone)
                        <li><i class="fa-solid fa-phone"></i><a href="{{ phone_href() }}">{{ $phone }}</a></li>
                    @endif
                    @if (whatsapp_number())
                        <li><i class="fa-brands fa-whatsapp"></i><a href="{{ whatsapp_url() }}" target="_blank" rel="noopener">WhatsApp'tan yazın</a></li>
                    @endif
                    @if ($email)
                        <li><i class="fa-regular fa-envelope"></i><!--email_off--><a href="mailto:{{ $email }}">{{ $email }}</a><!--/email_off--></li>
                    @endif
                    @if (setting('address'))
                        <li><i class="fa-solid fa-location-dot"></i><span>{{ setting('address') }}</span></li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ site_name() }}</span>
            <span>Formlarla paylaştığınız bilgiler yalnız size dönüş yapmak için kullanılır.</span>
        </div>
    </div>
</footer>
