import { shade } from './chart-theme';

/*
 * Kalender aktivitas: satu kotak = satu hari, kolom = minggu, baris = Senin..Minggu.
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

const MAX_SEGMENTS = 3;       // bagian warna maksimal per kotak; sisanya hanya di popup
const MAX_ITEMS = 5;          // nama loker maksimal per status di popup
const CELL_MAX = '28px';      // lebar kolom maksimal, supaya periode pendek tidak membuat kotak raksasa
const GAP = '3px';
const WEEKDAYS = ['Sen', '', 'Rab', '', 'Jum', '', ''];

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

function swatch(status) {
    const node = el('span', 'inline-block h-3 w-3 shrink-0 rounded-[3px] ring-1 ring-inset ring-obsidian/10');
    paint(node, status === null ? EMPTY_STYLE : styleFor(status));
    return node;
}

function summary(day) {
    if (day.events.length === 0) return `${day.label}: tidak ada aktivitas`;

    return `${day.label}: ` + day.events.map((e) => `${e.label} ${e.items.length}`).join(', ');
}

function buildCell(day) {
    const cell = el('div', 'aspect-square overflow-hidden rounded-[4px] ring-1 ring-inset ring-obsidian/[0.06]');
    cell.dataset.date = day.date;
    cell.setAttribute('role', 'img');
    cell.setAttribute('aria-label', summary(day));

    if (day.events.length === 0) {
        paint(cell, EMPTY_STYLE);
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
    const top = above >= 0 ? above - rootBox.top + 0 : cellBox.bottom + 8 - rootBox.top;

    tip.style.left = `${left}px`;
    tip.style.top = `${top}px`;
}

/**
 * Gambar kalender ke dalam `root` (wadah [data-calendar-body]).
 * payload.weeks = [{ month, days: [{ date, label, future, events: [{ status, label, items }] }] }]
 */
export function renderActivityCalendar(root, payload) {
    const weeks = payload.weeks ?? [];
    if (weeks.length === 0) throw new Error('Payload kalender kosong.');

    root.replaceChildren();
    root.classList.add('relative');

    // Satu grid: kolom pertama label hari, baris pertama label bulan, sisanya kotak
    const grid = el('div', 'w-full');
    grid.style.display = 'grid';
    grid.style.gap = GAP;
    grid.style.gridTemplateColumns = `auto repeat(${weeks.length}, minmax(0, ${CELL_MAX}))`;
    grid.style.justifyContent = 'start';

    grid.append(el('span'));
    weeks.forEach((week) => {
        const label = el('span', 'overflow-visible whitespace-nowrap text-xs text-obsidian/70', week.month ?? '');
        grid.append(label);
    });

    for (let row = 0; row < 7; row++) {
        grid.append(el('span', 'flex items-center pr-1 text-xs text-obsidian/70', WEEKDAYS[row]));

        weeks.forEach((week) => {
            const day = week.days[row];

            if (!day || day.future) {
                grid.append(el('span', 'aspect-square')); // hari yang belum terjadi: kosong, bukan "tanpa aktivitas"
                return;
            }

            const cell = buildCell(day);
            cell.__day = day;
            grid.append(cell);
        });
    }

    const scroller = el('div', 'overflow-x-auto pb-1'); // kalender 26 minggu di layar sempit digeser, bukan menggeser halaman
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

    scroller.addEventListener('scroll', () => { if (active) placeTooltip(tip, active, root); });

    // Tap/klik di luar kalender menutup popup yang disematkan
    document.addEventListener('click', (event) => {
        if (!root.isConnected) return;
        if (!root.contains(event.target)) close();
    });

    // Mulai dari minggu terbaru: geser ke kanan kalau kalender lebih lebar dari kartu
    scroller.scrollLeft = scroller.scrollWidth;
}