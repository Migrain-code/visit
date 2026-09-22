@php
    $recaptcha = app(\App\Services\Security\Recaptcha::class);
@endphp

@if ($recaptcha->enabled())
    {{--
        Google betiği SAYFA AÇILIŞINDA YÜKLENMEZ. ~700 KB indirip 1 saniyeden fazla
        işlemci harcıyordu (PageSpeed: TBT ve "kullanılmayan JavaScript"). Ziyaretçi forma
        dokununca iner (v2'de form ekrana yaklaşınca, kutucuk görünsün diye).
        Yükleyici: resources/js/app.js → recaptcha()
    --}}
    @once
        @push('scripts')
            <script>
                window.__recaptcha = {
                    version: @json($recaptcha->version()),
                    siteKey: @json($recaptcha->siteKey()),
                    action: @json($recaptcha->action()),
                    src: @json($recaptcha->version() === 'v2'
                        ? 'https://www.google.com/recaptcha/api.js'
                        : 'https://www.google.com/recaptcha/api.js?render='.$recaptcha->siteKey()),
                };
            </script>
        @endpush
    @endonce

    @if ($recaptcha->version() === 'v2')
        <div class="g-recaptcha mb-2" data-sitekey="{{ $recaptcha->siteKey() }}"></div>
    @else
        <input type="hidden" name="{{ \App\Services\Security\Recaptcha::FIELD }}">

        {{--
            Rozet gizlendiğinde Google bu bilgilendirmeyi zorunlu tutuyor.
            Rozet, sağ alttaki WhatsApp butonuyla aynı köşeye denk geldiği için gizleniyor.
        --}}
        <p class="form-text recaptcha-notice mb-0">
            Bu form reCAPTCHA ile korunmaktadır; Google
            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener nofollow">Gizlilik Politikası</a> ve
            <a href="https://policies.google.com/terms" target="_blank" rel="noopener nofollow">Kullanım Şartları</a> geçerlidir.
        </p>
    @endif

    @error(\App\Services\Security\Recaptcha::FIELD)
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
@endif
