// resources/js/sidebar-nav.js
// 1) Kotak hitam penanda menu aktif (meluncur antar menu).
// 2) Kalau ada #page-content: pindah menu tanpa reload penuh (hanya isinya diganti).
//    Kalau tidak ada atau ada yang gagal: otomatis pindah halaman biasa.

const PUTIH          = '!text-offwhite';
const TOKEN_AKTIF    = ['text-obsidian'];
const TOKEN_NONAKTIF = ['text-obsidian/70', 'hover:bg-nude/60', 'hover:text-obsidian'];
const BATAS_WAKTU    = 8000;               // ms
const KUNCI          = 'sidebarNavIndex';  // posisi menu terakhir (sessionStorage)
const BREAKPOINT_LG  = 1024;               // px, sama dengan "lg" Tailwind

let nav, box, links, profil, konten, controller;
let urlSekarang = location.pathname + location.search;

const kurangGerak = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---------- sessionStorage (aman kalau diblokir) ----------

function simpanIndex(i) {
    try { sessionStorage.setItem(KUNCI, String(i)); } catch (e) {}
}

function bacaIndex() {
    try {
        const v = sessionStorage.getItem(KUNCI);
        return v === null ? null : parseInt(v, 10);
    } catch (e) {
        return null;
    }
}

// ---------- Kotak hitam & status menu ----------

// Taruh kotak langsung di menu ke-i, tanpa animasi, dan langsung terlihat.
function taruh(i) {
    box.style.transition = 'none';
    box.style.transform  = `translateY(${links[i].offsetTop}px)`;
    box.style.opacity    = 1;
    box.getBoundingClientRect(); // paksa reflow supaya transisi berikutnya terbaca
}

function geserKotak(ke, dari) {
    // Tidak ada menu aktif (mis. halaman profil): kotak memudar
    if (ke < 0) {
        box.style.transition = kurangGerak() ? 'none' : 'opacity 150ms';
        box.style.opacity = 0;
        return;
    }

    const target = `translateY(${links[ke].offsetTop}px)`;

    // Sebelumnya tidak ada kotak: loncat ke posisi, lalu memudar masuk
    if (dari < 0) {
        box.style.transition = 'none';
        box.style.transform  = target;
        box.getBoundingClientRect();
        box.style.transition = kurangGerak() ? 'none' : 'opacity 150ms';
        box.style.opacity    = 1;
        return;
    }

    // Meluncur dari menu lama ke menu baru
    box.style.opacity    = 1;
    box.style.transition = kurangGerak() ? 'none' : 'transform 300ms cubic-bezier(.4,0,.2,1)';
    box.style.transform  = target;
}

function setAktif(index, animasi = true) {
    const lama = links.findIndex(l => l.hasAttribute('data-active'));

    links.forEach((link, i) => {
        const on = i === index;
        link.toggleAttribute('data-active', on);
        if (on) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
        link.classList.toggle(PUTIH, on);
        TOKEN_AKTIF.forEach(t => link.classList.toggle(t, on));
        TOKEN_NONAKTIF.forEach(t => link.classList.toggle(t, !on));
    });

    geserKotak(index, animasi ? lama : -1);
    simpanIndex(index);
}

function setProfil(on) {
    if (! profil) return;
    profil.classList.toggle('bg-nude', on);
    profil.classList.toggle('hover:bg-nude/60', ! on);
    if (on) profil.setAttribute('aria-current', 'page');
    else profil.removeAttribute('aria-current');
}

// ---------- Ganti konten (mode AJAX) ----------

function jalankanScript(el) {
    el.querySelectorAll('script').forEach(lama => {
        const baru = document.createElement('script');
        [...lama.attributes].forEach(a => baru.setAttribute(a.name, a.value));
        baru.textContent = lama.textContent;
        lama.replaceWith(baru);
    });
}

// Hanya menutup sidebar di HP/iPad. Di desktop dibiarkan sesuai pilihan pengguna.
function tutupSidebarHp() {
    if (window.innerWidth >= BREAKPOINT_LG) return;
    try {
        const state = window.Alpine?.$data(document.getElementById('sidebar'));
        if (state && 'terbuka' in state) state.terbuka = false;
    } catch (e) {}
}

async function muat(url, push) {
    controller?.abort();
    const ctrl = new AbortController();
    controller = ctrl;

    let habisWaktu = false;
    const timer = setTimeout(() => { habisWaktu = true; ctrl.abort(); }, BATAS_WAKTU);

    try {
        const res = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin',
            signal: ctrl.signal,
        });

        const tipe = res.headers.get('content-type') || '';
        if (! tipe.includes('text/html') || res.status >= 500) {
            location.href = url;
            return;
        }

        const doc  = new DOMParser().parseFromString(await res.text(), 'text/html');
        const baru = doc.getElementById('page-content');

        // Respons bukan halaman ber-sidebar (mis. redirect ke login): pindah biasa
        if (! baru || ! doc.getElementById('sidebar-nav')) {
            location.href = res.url || url;
            return;
        }

        const akhir = new URL(res.url);
        if (push) history.pushState({}, '', res.url);
        urlSekarang = akhir.pathname + akhir.search;

        document.title = doc.title;
        konten.innerHTML = baru.innerHTML;
        jalankanScript(konten);
        window.scrollTo(0, 0);

        const linkBaru = [...doc.querySelectorAll('#sidebar-nav .nav-link')];
        setAktif(linkBaru.findIndex(l => l.hasAttribute('data-active')));
        setProfil(doc.getElementById('nav-profil')?.hasAttribute('aria-current') ?? false);

        tutupSidebarHp();

        // Aksesibilitas: pindahkan fokus ke konten baru
        konten.focus({ preventScroll: true });
    } catch (e) {
        // Dibatalkan karena klik lain yang lebih baru: biarkan
        if (e.name === 'AbortError' && ! habisWaktu) return;
        console.error('[sidebar-nav] gagal muat, pindah halaman biasa:', e);
        location.href = url;
    } finally {
        clearTimeout(timer);
    }
}

// ---------- Pasang ----------

function pasangKlik() {
    document.addEventListener('click', (e) => {
        try {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

            const a = e.target.closest('a[href]');
            if (! a) return;
            if (a.target && a.target !== '_self') return;
            if (a.hasAttribute('download') || a.hasAttribute('data-full-reload')) return;

            const url = new URL(a.href, location.href);
            if (url.origin !== location.origin) return;
            if (url.pathname.startsWith('/storage/')) return;

            const samaHalaman = url.pathname + url.search === urlSekarang;
            if (url.hash && samaHalaman) return;

            if (samaHalaman) {
                e.preventDefault();
                tutupSidebarHp();
                return;
            }

            // Kotak hitam langsung bergerak saat diklik
            if (nav.contains(a) && links.includes(a)) {
                setAktif(links.indexOf(a));
                setProfil(false);
            } else if (a === profil) {
                setAktif(-1);
                setProfil(true);
            }

            // preventDefault dipanggil paling akhir: kalau ada error di atas,
            // klik tidak diblokir dan browser pindah halaman seperti biasa.
            e.preventDefault();
            muat(url.href, true);
        } catch (err) {
            console.error('[sidebar-nav] error saat klik, pakai navigasi biasa:', err);
        }
    });

    window.addEventListener('popstate', () => {
        if (location.pathname + location.search === urlSekarang) return;
        muat(location.href, false);
    });
}

function init() {
    nav    = document.getElementById('sidebar-nav');
    box    = document.getElementById('nav-indicator');
    konten = document.getElementById('page-content');

    // Kotak hitam hanya butuh nav + indikator
    if (! nav || ! box) {
        console.warn('[sidebar-nav] nonaktif. Ada yang hilang:', {
            'sidebar-nav': !! nav, 'nav-indicator': !! box,
        });
        return;
    }

    links  = [...nav.querySelectorAll('.nav-link')];
    profil = document.getElementById('nav-profil');

    const sekarang = links.findIndex(l => l.hasAttribute('data-active'));
    const sebelum  = bacaIndex();

    try {
        const bisaMeluncur = sebelum !== null && sebelum >= 0 && sebelum < links.length && sebelum !== sekarang;

        if (bisaMeluncur) {
            // Reload penuh: mulai dari menu sebelumnya, lalu meluncur ke menu aktif
            taruh(sebelum);
            setAktif(sekarang, true);
        } else {
            setAktif(sekarang, false);
        }
    } catch (err) {
        console.error('[sidebar-nav] gagal set posisi awal:', err);
    }

    if (! konten) {
        console.info('[sidebar-nav] #page-content tidak ada: pindah menu pakai halaman biasa.');
        return;
    }

    konten.setAttribute('tabindex', '-1');
    konten.style.outline = 'none';

    pasangKlik();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();