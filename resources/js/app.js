import Offcanvas from 'bootstrap/js/dist/offcanvas';
import Modal from 'bootstrap/js/dist/modal';

// Bootstrap bileşenlerini data-* API'siyle etkinleştir (import edilmeleri yeterli)
window.bootstrap = { Offcanvas, Modal };

document.addEventListener('DOMContentLoaded', () => {
    stickyHeader();
    revealOnScroll();
    recaptcha();
});

/*
 * Sayfa belli bir noktanın altına kaydırıldı mı?
 *
 * window.scrollY OKUNMAZ: Chrome bunu okurken düzeni yeniden hesaplamak zorunda kalıyor
 * (PageSpeed "zorunlu yeniden düzenleme"). Onun yerine sayfanın başından "offset" piksel
 * aşağıya görünmez bir işaret konur; tarayıcı işaretin ekrandan çıktığını kendisi bildirir.
 */
function onScrolledPast(offset, callback) {
    if (!('IntersectionObserver' in window)) return;

    const marker = document.createElement('div');
    marker.setAttribute('aria-hidden', 'true');
    marker.style.cssText = `position:absolute;top:${offset}px;left:0;width:1px;height:1px;pointer-events:none;`;
    document.body.prepend(marker);

    new IntersectionObserver(([entry]) => {
        // İşaret ekranın ÜSTÜNDEN çıktıysa kaydırılmış demektir (altından değil).
        callback(!entry.isIntersecting && entry.boundingClientRect.top < 0);
    }).observe(marker);
}

/* Sticky header gölgesi */
function stickyHeader() {
    const header = document.getElementById('siteHeader');
    if (!header) return;
    onScrolledPast(10, (past) => header.classList.toggle('is-sticky', past));
}

function revealOnScroll() {
    const items = document.querySelectorAll('.reveal');
    if (!items.length || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('in'));
        return;
    }
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) {
                e.target.classList.add('in');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    items.forEach((el) => io.observe(el));
}

/*
 * reCAPTCHA — İSTEĞE BAĞLI yükleme.
 *
 * Google betiği sayfa açılışında yüklenmez (~700 KB, 1 sn'den fazla işlemci). Ziyaretçi
 * forma dokununca iner; form doldurulurken hazır olur.
 *
 * v3: jeton GÖNDERİM ANINDA alınır. Jeton iki dakikada geçersizleşir; sayfa açılışında
 *     alınsaydı formu yavaş dolduran kullanıcının jetonu çoktan ölmüş olurdu.
 * v2: kutucuk görünür olmalı; form ekrana yaklaşınca yüklenir.
 *
 * Betik yüklenemezse form OLDUĞU GİBİ gönderilir; kullanıcı üçüncü taraf bir betiğe
 * rehin kalmaz. Sunucu kendi politikasına göre karar verir.
 */
function recaptcha() {
    const cfg = window.__recaptcha;
    if (!cfg || !cfg.siteKey) return;

    // Şablon "src" göndermese bile adres anahtardan üretilir: derlenmiş dosyalar ile
    // şablonların farklı sürümde kaldığı bir dağıtımda form jetonsuz gidip reddedilmesin.
    const version = cfg.version || 'v3';
    const src = cfg.src || (version === 'v2'
        ? 'https://www.google.com/recaptcha/api.js'
        : `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(cfg.siteKey)}`);

    const forms = document.querySelectorAll('form[data-recaptcha]');
    if (!forms.length) return;

    let loading = null;
    const load = () => loading || (loading = new Promise((resolve, reject) => {
        if (window.grecaptcha && window.grecaptcha.execute) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    }));
    const warmUp = () => load().catch(() => {});

    forms.forEach((form) => {
        ['focusin', 'pointerdown', 'touchstart'].forEach((ev) => form.addEventListener(ev, warmUp, { once: true, passive: true }));

        if (version === 'v2') {
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) {
                        observer.disconnect();
                        warmUp();
                    }
                }, { rootMargin: '300px' });
                observer.observe(form);
            } else {
                warmUp();
            }
            return;
        }

        const field = form.querySelector('input[name="g-recaptcha-response"]');
        if (!field) return;

        let tokenReady = false;

        form.addEventListener('submit', (event) => {
            if (tokenReady) return;                       // ikinci tur: gerçekten gönder
            event.preventDefault();

            const send = () => { tokenReady = true; form.requestSubmit(); };

            load()
                .then(() => new Promise((resolve) => window.grecaptcha.ready(resolve)))
                .then(() => window.grecaptcha.execute(cfg.siteKey, { action: cfg.action }))
                .then((token) => { field.value = token; })
                .catch(() => {})
                .finally(send);
        });
    });
}
