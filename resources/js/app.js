import Collapse from 'bootstrap/js/dist/collapse';
import Offcanvas from 'bootstrap/js/dist/offcanvas';
import Carousel from 'bootstrap/js/dist/carousel';

// Bootstrap bileşenlerini data-* API'siyle etkinleştir (import edilmeleri yeterli)
window.bootstrap = { Collapse, Offcanvas, Carousel };

document.addEventListener('DOMContentLoaded', () => {
    stickyHeader();
    revealOnScroll();
    animateCounters();
    galleryFilter();
    lightbox();
    reservationForm();
    tourFinder();
    recaptcha();
    backToTop();
});

/*
 * Sayfa belli bir noktanın altına kaydırıldı mı?
 *
 * window.scrollY OKUNMAZ: Chrome bunu okurken düzeni yeniden hesaplamak zorunda kalıyor
 * (PageSpeed "zorunlu yeniden düzenleme"). Onun yerine sayfanın başından "offset" piksel
 * aşağıya görünmez bir işaret konur; tarayıcı işaretin ekrandan çıktığını kendisi bildirir.
 * Kaydırma dinleyicisi de gerekmez.
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

/* Sayaç animasyonu (yalnızca sayısal değerler) */
function animateCounters() {
    const nums = document.querySelectorAll('[data-count]');
    if (!nums.length) return;
    const run = (el) => {
        const raw = el.dataset.count.trim();
        const match = raw.match(/^(\d+)(.*)$/);
        if (!match) { el.textContent = raw; return; }
        const target = parseInt(match[1], 10);
        const suffix = match[2] || '';
        const duration = 1200;
        const start = performance.now();
        const tick = (now) => {
            const p = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased) + suffix;
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };
    if (!('IntersectionObserver' in window)) { nums.forEach(run); return; }
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => { if (e.isIntersecting) { run(e.target); io.unobserve(e.target); } });
    }, { threshold: 0.5 });
    nums.forEach((el) => io.observe(el));
}

/* Galeri kategori filtresi */
function galleryFilter() {
    const wrap = document.querySelector('[data-gallery]');
    if (!wrap) return;
    const buttons = wrap.querySelectorAll('.filter-btn');
    const items = wrap.querySelectorAll('.gallery-item');
    const apply = (cat) => {
        buttons.forEach((b) => b.classList.toggle('active', b.dataset.filter === cat));
        items.forEach((it) => it.classList.toggle('is-hidden', cat !== 'all' && it.dataset.category !== cat));
        const url = new URL(window.location);
        if (cat === 'all') url.searchParams.delete('kategori'); else url.searchParams.set('kategori', cat);
        window.history.replaceState({}, '', url);
    };
    buttons.forEach((b) => b.addEventListener('click', () => apply(b.dataset.filter)));
    const active = wrap.querySelector('.filter-btn.active');
    if (active && active.dataset.filter !== 'all') apply(active.dataset.filter);
}

/* Basit lightbox */
function lightbox() {
    const links = Array.from(document.querySelectorAll('.gallery-item[data-full]'));
    if (!links.length) return;
    const box = document.createElement('div');
    box.className = 'lightbox';
    box.innerHTML = `
        <button class="lb-btn lb-close" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>
        <button class="lb-btn lb-prev" aria-label="Önceki"><i class="fa-solid fa-chevron-left"></i></button>
        <img alt="">
        <button class="lb-btn lb-next" aria-label="Sonraki"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="lb-caption"></div>`;
    document.body.appendChild(box);
    const img = box.querySelector('img');
    const caption = box.querySelector('.lb-caption');
    let index = 0;
    const visible = () => links.filter((l) => !l.classList.contains('is-hidden'));
    const show = (i) => {
        const list = visible();
        if (!list.length) return;
        index = (i + list.length) % list.length;
        const el = list[index];
        img.src = el.dataset.full;
        img.alt = el.dataset.alt || '';
        caption.textContent = el.dataset.title || '';
        box.classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    const close = () => { box.classList.remove('open'); document.body.style.overflow = ''; };
    links.forEach((l) => l.addEventListener('click', (e) => { e.preventDefault(); show(visible().indexOf(l)); }));
    box.querySelector('.lb-close').addEventListener('click', close);
    box.querySelector('.lb-prev').addEventListener('click', () => show(index - 1));
    box.querySelector('.lb-next').addEventListener('click', () => show(index + 1));
    box.addEventListener('click', (e) => { if (e.target === box) close(); });
    document.addEventListener('keydown', (e) => {
        if (!box.classList.contains('open')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') show(index - 1);
        if (e.key === 'ArrowRight') show(index + 1);
    });
}

/*
 * Rezervasyon formu: birbirine bağlı iki liste.
 *   il  → ilçe
 *   tur → o turun satıştaki tarihleri
 * Veri sayfaya JSON olarak gömülüdür; ek istek atılmaz.
 */
function reservationForm() {
    const dependentSelect = (form, parentName, childName, dataAttr, emptyLabel, pickLabel) => {
        const parent = form.querySelector(`[name="${parentName}"]`);
        const child = form.querySelector(`[name="${childName}"]`);
        const dataEl = form.querySelector(`script[${dataAttr}]`);
        if (!parent || !child || !dataEl) return;

        let map = {};
        try { map = JSON.parse(dataEl.textContent); } catch (e) { map = {}; }
        let preselected = child.dataset.selected || '';

        const fill = () => {
            const list = map[parent.value] || [];
            child.innerHTML = '';
            const first = document.createElement('option');
            first.value = '';
            first.textContent = !parent.value ? emptyLabel : (list.length ? pickLabel : 'Açık tarih yok, bize sorun');
            child.appendChild(first);
            list.forEach((item) => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.label || item.name;
                if (String(item.id) === preselected) opt.selected = true;
                child.appendChild(opt);
            });
            child.disabled = !parent.value || !list.length;
            preselected = '';       // ön seçim yalnız ilk doldurmada geçerli
        };
        parent.addEventListener('change', fill);
        fill();
    };

    document.querySelectorAll('form[data-reservation-form]').forEach((form) => {
        dependentSelect(form, 'province_id', 'district_id', 'data-districts', 'Önce il seçin', 'İlçe seçin');
        dependentSelect(form, 'tour_id', 'tour_departure_id', 'data-departures', 'Önce tur seçin', 'Tarih seçin (isteğe bağlı)');
    });
}

/* Ana sayfadaki tur bulucu: kategori seçildiyse o kategorinin adresine gider. */
function tourFinder() {
    const form = document.querySelector('form[data-tour-finder]');
    if (!form) return;
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const category = form.querySelector('[data-category]').value;
        const duration = form.querySelector('[name="sure"]').value;
        const url = new URL(form.dataset.base + (category ? '/' + category : ''), window.location.origin);
        if (duration) url.searchParams.set('sure', duration);
        window.location.assign(url.toString());
    });
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
        // Betik sayfada zaten yüklüyse (eski şablon) ikinci kez yükleme.
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

/* Yukarı çık */
function backToTop() {
    const btn = document.querySelector('.back-to-top');
    if (!btn) return;
    onScrolledPast(500, (past) => btn.classList.toggle('show', past));
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}
