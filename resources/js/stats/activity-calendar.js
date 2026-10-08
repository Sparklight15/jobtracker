import { shade } from './chart-theme';

/*
 * Kalender aktivitas: satu kotak = satu hari, baris = minggu (Senin..Minggu dari kiri ke kanan).
 * Minggu terbaru ada di ATAS; geser (scroll) ke bawah untuk melihat hari-hari sebelumnya.
 * Setiap kotak menampilkan angka tanggalnya, dan nama hari ada di header yang menempel di atas.
 *
 * Tema proyek hitam-putih, jadi status dibedakan HANYA dengan gradasi monokrom: satu rampa
 * enam langkah dari obsidian ke abu hangat (shade() di chart-theme.js, sama dengan grafik lain).
 * Urutan gelap -> terang: Offer, Interview, Screening, Applied, Rejected, Ghosted.
 * Hari tanpa aktivitas = nude tipis. Ganti urutan atau langkahnya di STATUS_STYLES.
 */

const STEPS = 6;

const STATUS_STYLES = {
    offer: { backgroundColor: shade(0, STEPS) },
    interview: { backgroundColor: shade(1, STEPS) },
    screening: { backgroundColor: shade(2, STEPS) },
    applied: { backgroundColor: shade(3, STEPS) },
    rejected: { backgroundColor: shade(4, STEPS) },
    ghosted: { backgroundColor: shade(5, STEPS) },
};
const FALLBACK_STYLE = { backgroundColor: shade(2, STEPS) };
const EMPTY_STYLE = { backgroundColor: 'rgba(227, 219, 204, 0.4)' };

// Warna angka tanggal di dalam kotak: terang di atas warna gelap, gelap di atas warna terang.
const TEXT_ON_DARK = '#FDFCF8';
const TEXT_ON_LIGHT = '#101010';
const TEXT_ON_EMPTY = 'rgba(16, 16, 16, 0.45)';
const DARK_STATUSES = ['offer', 'interview', 'screening']; // status yang warnanya cukup gelap untuk teks terang

// Dipakai kalau warna latar kartu tidak bisa dibaca (header yang menempel butuh warna solid).
const FALLBACK_CARD_BG = '#FDFCF8';

const MAX_SEGMENTS = 3;       // bagian warna maksimal per kotak; sisanya hanya di popup
const MAX_ITEMS = 5;          // nama loker maksimal per status di popup
const CELL_SIZE = '2rem';     // lebar dan tinggi satu kotak
const LABEL_WIDTH = '3rem';   // lebar kolom rentang tanggal minggu (di kiri)
const GAP = '3px';
const SCROLL_HEIGHT = '26rem'; // tinggi maksimal area kalender; lebih dari ini jadi bisa di-scroll
const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

const styleFor = (status) => STATUS_STYLES[status] ?? FALLBACK_STYLE;

function paint(node, style) {
    Object.assign(node.style, style);
}

/** Angka tanggal (1-31) dari string 'YYYY-MM-DD'. */
const dayOfMonth = (date) => Number(String(date).slice(8, 10));

/** Teks terang kalau status pertama di kotak berwarna gelap (atau tidak dikenal), selain itu teks gelap. */
function textColorFor(status) {
    return DARK_STATUSES.includes(status) || !(status in STATUS_STYLES) ? TEXT_ON_DARK : TEXT_ON_LIGHT;
}

/** Warna latar kartu, supaya header yang menempel menutupi kotak yang bergulir di bawahnya. */
function cardBackground(root) {
    const card = root.closest('[data-chart-panel]') ?? root.parentElement;
    const color = card ? getComputedStyle(card).backgroundColor : '';

    return !color || color === 'transparent' || color === 'rgba(0, 0, 0, 0)' ? FALLBACK_CARD_BG : color;
}

function swatch(status) {
    const node = el('span', 'inline-block h-3 w-3 shrink-0 rounded-[3px] ring-1 ring-inset ring-obsidian/10');
    paint(node, status === null ? EMPTY_STYLE : styleFor(status));
    return node;
}

function summary(day) {
    if (day.events.length === 0) return `${day.label}: tidak ada aktivitas`;

    return `${day.label}: ` + day.events.map((e) => `${e.label} ${e.items.length}`).join(', ');
}

function dateNumber(day, color) {
    const number = el(
        'span',
        'pointer-events-none absolute inset-0 flex items-center justify-center text-[11px] font-medium leading-none',
        String(dayOfMonth(day.date)),
    );
    number.style.color = color;

    return number;
}

function buildCell(day) {
    const cell = el('div', 'relative aspect-square overflow-hidden rounded-[4px] ring-1 ring-inset ring-obsidian/[0.06]');
    cell.dataset.date = day.date;
    cell.setAttribute('role', 'img');
    cell.setAttribute('aria-label', summary(day));

    if (day.events.length === 0) {
        paint(cell, EMPTY_STYLE);
        cell.append(dateNumber(day, TEXT_ON_EMPTY));
        return cell;
    }

    cell.tabIndex = 0;
    cell.classList.add('flex', 'cursor-pointer', 'focus-visible:outline', 'focus-visible:outline-2', 'focus-visible:outline-offset-1', 'focus-visible:outline-obsidian');

    day.events.slice(0, MAX_SEGMENTS).forEach((event, index) => {
        // Garis offwhite tipis antar bagian, supaya dua abu yang bersebelahan tetap terpisah
        const part = el('span', `block h-full min-w-0 flex-1 ${index > 0 ? 'border-l border-offwhite' : ''}`);
        paint(part, styleFor(event.status));
        cell.append(part);
    });

    cell.append(dateNumber(day, textColorFor(day.events[0].status)));

    return cell;
}

function buildLegend(legend) {
    const list = el('ul', 'mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-obsidian/70');

    [...legend, { status: null, label: 'Tidak ada aktivitas' }].forEach((item) => {
        const li = el('li', 'inline-flex items-center gap-1.5');
        li.append(swatch(item.status), el('span', '', item.label));
        list.append(li);
    });

    return list;
}

/** Popup detail: satu instans per kalender, dipindah-pindah antar kotak. */
function buildTooltip() {
    const tip = el(
        'div',
        'pointer-events-none absolute left-0 top-0 z-20 hidden w-max max-w-[16rem] rounded-field bg-obsidian p-2.5 text-xs text-offwhite shadow-card',
    );
    tip.setAttribute('role', 'tooltip');

    return tip;
}

function fillTooltip(tip, day) {
    tip.replaceChildren(el('p', 'font-semibold', day.label));

    if (day.events.length === 0) {
        tip.append(el('p', 'mt-1 text-offwhite/70', 'Tidak ada aktivitas'));
        return;
    }

    day.events.forEach((event) => {
        const block = el('div', 'mt-2');
        const head = el('p', 'flex items-center gap-1.5 font-semibold');
        const mark = swatch(event.status);
        mark.classList.add('ring-offwhite/40');
        head.append(mark, el('span', '', `${event.label} (${event.items.length})`));
        block.append(head);

        event.items.slice(0, MAX_ITEMS).forEach((name) => {
            block.append(el('p', 'mt-0.5 break-words pl-[18px] text-offwhite/80', name));
        });

        if (event.items.length > MAX_ITEMS) {
            block.append(el('p', 'mt-0.5 pl-[18px] text-offwhite/60', `dan ${event.items.length - MAX_ITEMS} lainnya`));
        }

        tip.append(block);
    });
}

function placeTooltip(tip, cell, root) {
    const rootBox = root.getBoundingClientRect();
    const cellBox = cell.getBoundingClientRect();
    const tipBox = tip.getBoundingClientRect();

    const centerX = cellBox.left - rootBox.left + cellBox.width / 2;
    const left = Math.min(Math.max(centerX - tipBox.width / 2, 0), Math.max(rootBox.width - tipBox.width, 0));

    // Di atas kotak; kalau tidak muat di layar, pindah ke bawah
    const above = cellBox.top - tipBox.height - 8;
    const top = above >= 0 ? above - rootBox.top : cellBox.bottom + 8 - rootBox.top;

    tip.style.left = `${left}px`;
    tip.style.top = `${top}px`;
}

/** Baris pertama (header): kolom kosong untuk rentang tanggal + nama hari. Menempel di atas saat di-scroll. */
function appendHeader(grid, background) {
    ['', ...WEEKDAYS].forEach((name) => {
        const cell = el('span', 'text-center text-xs font-medium text-obsidian/70', name);
        Object.assign(cell.style, {
            position: 'sticky',
            top: '0',
            zIndex: '5',
            backgroundColor: background,
            paddingBottom: '4px',
        });
        grid.append(cell);
    });
}

/** Rentang tanggal satu minggu, misalnya "5–11" (tanpa bulan; detail lengkap ada di popup). */
function weekRange(week) {
    const first = week.days?.[0];
    const last = week.days?.[week.days.length - 1];

    return first && last ? `${dayOfMonth(first.date)}–${dayOfMonth(last.date)}` : '';
}

/**
 * Gambar kalender ke dalam `root` (wadah [data-calendar-body]).
 * payload.weeks = [{ days: [{ date, label, future, events: [{ status, label, items }] }] }]
 * dalam urutan lama -> baru; di layar urutannya dibalik supaya minggu terbaru ada di atas.
 */
export function renderActivityCalendar(root, payload) {
    const weeks = payload.weeks ?? [];
    if (weeks.length === 0) throw new Error('Payload kalender kosong.');

    // Gambar ulang tidak boleh menumpuk listener di document
    root.__abort?.abort();
    const abort = new AbortController();
    root.__abort = abort;

    root.replaceChildren();
    root.classList.add('relative');

    // Satu grid: kolom pertama rentang tanggal minggu, 7 kolom berikutnya Senin..Minggu
    const grid = el('div', 'w-full');
    grid.style.display = 'grid';
    grid.style.gap = GAP;
    grid.style.gridTemplateColumns = `${LABEL_WIDTH} repeat(7, ${CELL_SIZE})`;
    grid.style.justifyContent = 'start';

    appendHeader(grid, cardBackground(root));

    [...weeks].reverse().forEach((week) => {
        grid.append(el('span', 'flex items-center whitespace-nowrap pr-1 text-[10px] text-obsidian/50', weekRange(week)));

        for (let i = 0; i < 7; i++) {
            const day = week.days[i];

            if (!day || day.future) {
                grid.append(el('span', 'aspect-square')); // hari yang belum terjadi: kosong, bukan "tanpa aktivitas"
                continue;
            }

            const cell = buildCell(day);
            cell.__day = day;
            grid.append(cell);
        }
    });

    // Area yang di-scroll: tinggi dibatasi supaya kartu tidak memanjang, bisa digeser ke bawah untuk hari-hari lama
    const scroller = el('div', 'overflow-auto pb-1');
    scroller.style.maxHeight = SCROLL_HEIGHT;
    scroller.tabIndex = 0; // supaya bisa di-scroll dengan keyboard
    scroller.setAttribute('role', 'region');
    scroller.setAttribute('aria-label', 'Kalender aktivitas, geser ke bawah untuk melihat hari-hari sebelumnya');
    scroller.append(grid);

    const tip = buildTooltip();
    root.append(scroller, buildLegend(payload.legend ?? []), tip);

    let active = null;
    let pinned = false;

    const open = (cell) => {
        active = cell;
        fillTooltip(tip, cell.__day);
        tip.classList.remove('hidden');
        placeTooltip(tip, cell, root);
    };

    const close = () => {
        active = null;
        pinned = false;
        tip.classList.add('hidden');
    };

    const cellOf = (event) => event.target.closest('[data-date]');

    // Mouse: popup mengikuti kursor. Sentuh: tap membuka, tap di luar menutup.
    grid.addEventListener('pointerover', (event) => {
        if (event.pointerType !== 'mouse' || pinned) return;
        const cell = cellOf(event);
        if (cell?.__day) open(cell);
    });

    grid.addEventListener('pointerout', (event) => {
        if (event.pointerType !== 'mouse' || pinned) return;
        if (!event.relatedTarget || !grid.contains(event.relatedTarget) || !cellOf({ target: event.relatedTarget })) close();
    });

    grid.addEventListener('click', (event) => {
        const cell = cellOf(event);
        if (!cell?.__day) return;

        if (cell === active && pinned) return close();

        pinned = event.pointerType !== 'mouse' || pinned;
        open(cell);
    });

    grid.addEventListener('focusin', (event) => {
        const cell = cellOf(event);
        if (cell?.__day) open(cell);
    });
    grid.addEventListener('focusout', () => { if (!pinned) close(); });

    grid.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });

    // Popup ikut bergeser saat area kalender di-scroll
    scroller.addEventListener('scroll', () => { if (active) placeTooltip(tip, active, root); });

    // Tap/klik di luar kalender menutup popup yang disematkan
    document.addEventListener('click', (event) => {
        if (!root.isConnected) return;
        if (!root.contains(event.target)) close();
    }, { signal: abort.signal });
}