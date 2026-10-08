import Chart from 'chart.js/auto';

// Palet proyek (lihat tailwind.config.js)
const INK = '#101010';
const NUDE = '#E3DBCC';
const IVORY = '#F3F0E9';
const OFFWHITE = '#FDFCF8';
const LIGHT = '#CFC6B3'; // ujung terang rampa monokrom, masih terbaca di atas kartu ivory
const MUTED = 'rgba(16, 16, 16, 0.7)';

// Gradasi batang: dasar batang tipis -> ujung batang pekat (ganti opasitas di sini)
const BAR_FILL_START = 0.22; // opasitas di pangkal batang
const BAR_FILL_END = 0.88;   // opasitas di ujung batang

// Gradasi area di bawah grafik garis: pekat di dekat garis -> memudar ke bawah
const AREA_FILL_TOP = 0.2;
const AREA_FILL_BOTTOM = 0.02;

// Grafik garis (Apply per minggu/bulan, dll.)
const LINE_WIDTH = 0.5; // px, bisa pecahan: 0.4 = hampir hairline, 0.75 = agak tegas
const LINE_POINT = 1;   // radius titik (hover otomatis +2)

Chart.defaults.font.family = '"Nunito Variable", ui-sans-serif, system-ui, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = MUTED;
Chart.defaults.borderColor = NUDE;
Chart.defaults.responsive = true;
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.animation.duration = 400;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.tooltip.backgroundColor = INK;
Chart.defaults.plugins.tooltip.titleColor = OFFWHITE;
Chart.defaults.plugins.tooltip.bodyColor = OFFWHITE;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.padding = 10;

function hexToRgb(hex) {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function mixRgb(from, to, t) {
    const a = hexToRgb(from);
    const b = hexToRgb(to);
    return a.map((v, i) => Math.round(v + (b[i] - v) * t));
}

function mix(from, to, t) {
    const c = mixRgb(from, to, t);
    return `rgb(${c[0]}, ${c[1]}, ${c[2]})`;
}

const rgba = (rgb, alpha) => `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${alpha})`;

/** Warna ke-i dari n, rampa dari obsidian ke abu hangat. */
export function shade(i, n) {
    return n <= 1 ? INK : mix(INK, LIGHT, i / (n - 1));
}

/** Versi array [r, g, b] dari shade(), dipakai untuk gradasi batang dan area. */
function shadeRgb(i, n) {
    return n <= 1 ? hexToRgb(INK) : mixRgb(INK, LIGHT, i / (n - 1));
}

/**
 * Gradasi per batang (scriptable). Vertikal: dari dasar ke atas.
 * Horizontal: dari kiri ke kanan. Tiap batang memakai koordinatnya sendiri,
 * jadi batang pendek dan panjang sama-sama mendapat gradasi penuh.
 *
 * Saat grafik baru dibuat (atau dibaca legenda), posisi batang belum dihitung
 * sehingga koordinatnya bisa kosong/NaN. createLinearGradient() melempar error
 * untuk nilai seperti itu, jadi di kondisi tersebut dipakai warna solid dulu.
 */
function barGradient(rgb, horizontal) {
    const fallback = rgba(rgb, BAR_FILL_END);

    return (context) => {
        const { chart, element } = context;
        const ctx = chart?.ctx;

        if (!ctx || !chart.chartArea || !element || typeof element.getProps !== 'function') {
            return fallback;
        }

        const { x, y, base } = element.getProps(['x', 'y', 'base'], true);
        const coords = horizontal ? [base, x] : [base, y];

        if (!coords.every((v) => typeof v === 'number' && Number.isFinite(v))) {
            return fallback;
        }

        // Batang tanpa panjang (nilai 0): gradasi tidak perlu
        if (coords[0] === coords[1]) return fallback;

        try {
            const gradient = horizontal
                ? ctx.createLinearGradient(coords[0], 0, coords[1], 0)
                : ctx.createLinearGradient(0, coords[0], 0, coords[1]);

            gradient.addColorStop(0, rgba(rgb, BAR_FILL_START));
            gradient.addColorStop(1, rgba(rgb, BAR_FILL_END));
            return gradient;
        } catch (e) {
            return fallback;
        }
    };
}

/**
 * Gradasi area di bawah garis (scriptable): pekat di atas, memudar ke bawah.
 * Memakai area plot grafik (chartArea), jadi saat grafik belum siap
 * dikembalikan 'transparent' dan Chart.js menggambar ulang setelah ukurannya ada.
 */
function areaGradient(rgb) {
    return (context) => {
        const { chart } = context;
        const ctx = chart?.ctx;
        const area = chart?.chartArea;

        if (!ctx || !area) return 'transparent';

        const { top, bottom } = area;

        if (![top, bottom].every((v) => typeof v === 'number' && Number.isFinite(v)) || top === bottom) {
            return 'transparent';
        }

        try {
            const gradient = ctx.createLinearGradient(0, top, 0, bottom);
            gradient.addColorStop(0, rgba(rgb, AREA_FILL_TOP));
            gradient.addColorStop(1, rgba(rgb, AREA_FILL_BOTTOM));
            return gradient;
        } catch (e) {
            return 'transparent';
        }
    };
}

function withUnit(text, unit) {
    if (!unit) return text;
    if (unit === '%') return `${text}%`;
    if (unit === 'Rp') return `Rp ${text}`;
    return `${text} ${unit}`;
}

function formatNumber(value) {
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 1 });
}

function buildDataset(type, series, index, total, labels) {
    const base = { label: series.name, data: series.data };

    switch (type) {
        case 'bar':
        case 'hbar': {
            const rgb = shadeRgb(index, total);
            return {
                ...base,
                backgroundColor: barGradient(rgb, type === 'hbar'),
                hoverBackgroundColor: rgba(rgb, 1), // batang yang di-hover jadi solid
                borderRadius: 6,
                maxBarThickness: 40,
            };
        }
        case 'line': {
            const color = shade(index, total);
            const rgb = shadeRgb(index, total);
            // Area berisi hanya untuk grafik satu seri. Kalau seri lebih dari satu,
            // area saling menumpuk dan sulit dibaca, jadi cukup garisnya.
            const filled = total === 1;

            return {
                ...base,
                borderColor: color,
                borderWidth: LINE_WIDTH,
                tension: 0.3,
                pointRadius: LINE_POINT,
                pointHoverRadius: LINE_POINT + 2,
                pointBackgroundColor: color, // titik tetap solid, tidak ikut gradasi
                pointBorderColor: color,
                borderDash: index > 0 ? [6, 4] : [], // garis putus-putus membedakan seri selain warna
                fill: filled ? 'origin' : false,
                backgroundColor: filled ? areaGradient(rgb) : color,
            };
        }
        case 'doughnut':
            return {
                ...base,
                backgroundColor: labels.map((_, j) => shade(j, labels.length)),
                borderColor: IVORY,
                borderWidth: 2,
            };
        default: // scatter
            return { ...base, backgroundColor: INK, pointRadius: 4 };
    }
}

// ---- Setengah lingkaran radial (tipe 'halfradial') ----
// 'share': sudut irisan sebanding dengan porsi nilai (seperti di gambar referensi)
// 'equal': semua irisan sama lebar, hanya jari-jari yang beda
const RADIAL_ANGLE = 'share';
const RADIAL_INNER = 0.32;      // lubang tengah, sebagai porsi dari jari-jari terbesar
const RADIAL_LABEL_GAP = 52;    // jarak vertikal minimum antar label di sisi yang sama (px)
const RADIAL_PAD_TOP = 40;      // ruang atas untuk angka label paling atas (px)
const RADIAL_PAD_BOTTOM = 10;   // ruang bawah (px)
const RADIAL_LEADER = 40;       // panjang garis penghubung dari tepi irisan sampai teks (px)

// Posisi label:
// 'below'  : label berderet di bawah semi-donut, chart bisa selebar kartu (disarankan untuk kartu sempit)
// 'leader' : label di kiri/kanan dengan garis penghubung (butuh kartu yang lebar)
const RADIAL_LABELS = 'below';
const RADIAL_BELOW_PAD = 12;      // ruang atas di mode 'below' (px)
const RADIAL_BELOW_GAP = 18;      // jarak garis dasar semi-donut ke baris label (px)
const RADIAL_BELOW_LABEL_H = 68;  // tinggi blok label: swatch + angka + dua baris nama (px)

/** "Applied → Screening" jadi ["Applied", "→ Screening"] supaya label tidak melebar. */
function splitName(name) {
    const parts = String(name ?? '').split(' → ');
    return parts.length === 2 ? [parts[0], `→ ${parts[1]}`] : [String(name ?? '')];
}

/** Lebar teks label terlebar (angka besar atau nama tahap), untuk menghitung ruang kiri/kanan. */
function measureLabelWidth(ctx, labels, nums, unit, family) {
    let widest = 0;

    labels.forEach((name, i) => {
        ctx.font = `600 18px ${family}`;
        widest = Math.max(widest, ctx.measureText(withUnit(formatNumber(nums[i] ?? 0), unit)).width);

        ctx.font = `400 11px ${family}`;
        splitName(name).forEach((line) => {
            widest = Math.max(widest, ctx.measureText(line).width);
        });
    });

    return widest;
}

// ---- Animasi dan interaksi semi-donut ----
// Animasi digerakkan loop requestAnimationFrame sendiri (bukan animator Chart.js),
// karena irisan digambar manual oleh plugin. Status ada di chart.$radial.
const RADIAL_ENTER_MS = 650;     // lama satu irisan tumbuh
const RADIAL_STAGGER_MS = 140;   // jeda antar irisan saat tumbuh
const RADIAL_HOVER_MS = 110;     // kecepatan transisi hover (makin kecil makin cepat)
const RADIAL_POP = 8;            // irisan aktif menonjol keluar sejauh ini (px)
const RADIAL_DIM_SLICE = 0.35;   // opasitas irisan lain saat ada yang aktif
const RADIAL_DIM_LABEL = 0.4;    // opasitas label lain saat ada yang aktif

const easeOutCubic = (t) => 1 - (1 - t) ** 3;
const clamp01 = (t) => Math.min(1, Math.max(0, t));
const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

function radialState(chart, n) {
    if (!chart.$radial) {
        const reduced = prefersReducedMotion();

        chart.$radial = {
            reduced,
            started: reduced,                  // animasi masuk menunggu kartu terlihat di layar
            t0: 0,
            last: 0,
            raf: 0,
            enter: Array(n).fill(reduced ? 1 : 0), // progres tumbuh per irisan, 0..1
            hl: Array(n).fill(0),              // seberapa aktif tiap irisan, 0..1
            dim: 0,                            // seberapa kuat irisan lain diredupkan, 0..1
            hover: -1,
            pinned: -1,                        // irisan yang dikunci lewat klik atau tap
            lastActive: -1,
            pointer: null,
            geom: null,
            observer: null,
        };
    }

    return chart.$radial;
}

const radialActive = (s) => (s.hover >= 0 ? s.hover : s.pinned);

function radialTick(chart, now) {
    const s = chart.$radial;
    if (!s || !chart.ctx) return;

    s.raf = 0;
    const dt = Math.min(64, now - (s.last || now));
    s.last = now;
    let busy = false;

    if (s.started) {
        const elapsed = now - s.t0;

        s.enter.forEach((_, i) => {
            const p = s.reduced ? 1 : easeOutCubic(clamp01((elapsed - i * RADIAL_STAGGER_MS) / RADIAL_ENTER_MS));
            s.enter[i] = p;
            if (p < 1) busy = true;
        });
    }

    const active = radialActive(s);
    if (active >= 0) s.lastActive = active;

    const k = s.reduced ? 1 : 1 - Math.exp(-dt / RADIAL_HOVER_MS);
    const approach = (value, target) => {
        const next = value + (target - value) * k;
        if (Math.abs(target - next) < 0.005) return target;
        busy = true;
        return next;
    };

    s.hl = s.hl.map((value, i) => approach(value, i === active ? 1 : 0));
    s.dim = approach(s.dim, active >= 0 ? 1 : 0);

    chart.draw();

    if (busy) s.raf = requestAnimationFrame((t) => radialTick(chart, t));
}

/** Minta satu frame (atau loop animasi) baru. Aman dipanggil berkali-kali. */
function radialKick(chart) {
    const s = chart.$radial;
    if (!s || s.raf) return;

    s.last = 0;
    s.raf = requestAnimationFrame((t) => radialTick(chart, t));
}

/** Mulai animasi masuk setelah kartu benar-benar terlihat, bukan saat data selesai dimuat. */
function startRadial(chart, canvas, n) {
    const s = radialState(chart, n);
    if (s.started) return; // reduced motion: langsung tampil penuh

    const begin = () => {
        s.started = true;
        s.t0 = performance.now();
        radialKick(chart);
    };

    if (typeof IntersectionObserver === 'undefined') return begin();

    s.observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        s.observer?.disconnect();
        s.observer = null;
        begin();
    }, { threshold: 0.4 });

    s.observer.observe(canvas);
}

/** Irisan (atau kolom label) di bawah titik x,y; -1 kalau tidak ada. */
function radialHit(g, x, y) {
    const dx = x - g.cx;
    const dy = y - g.cy;
    const dist = Math.hypot(dx, dy);

    if (dy <= 0 && dist >= g.r0) {
        let a = Math.atan2(dy, dx);
        if (a < 0) a += Math.PI * 2; // setengah atas lingkaran: sudut π..2π

        const hit = g.slices.find((sl) => a >= sl.start && a <= sl.end && dist <= sl.rOut + RADIAL_POP);
        if (hit) return hit.i;
    }

    if (g.labelTop !== null
        && y >= g.labelTop - 8 && y <= g.labelTop + 68
        && x >= g.margin && x <= g.w - g.margin) {
        const i = Math.floor((x - g.margin) / g.colW);
        return i >= 0 && i < g.n ? i : -1;
    }

    return -1;
}

function handleRadialEvent(chart, event) {
    const s = chart.$radial;
    if (!s?.geom || !event) return;

    let changed = false;

    if (event.type === 'mouseout') {
        if (s.hover !== -1) {
            s.hover = -1;
            changed = true;
        }
    } else if (typeof event.x === 'number' && typeof event.y === 'number') {
        const idx = radialHit(s.geom, event.x, event.y);
        s.pointer = { x: event.x, y: event.y };

        if (event.type === 'click') {
            // Klik/tap mengunci irisan; klik lagi di irisan yang sama atau di area kosong melepasnya.
            if (idx < 0 || idx === s.pinned) {
                s.pinned = -1;
                s.hover = -1;
            } else {
                s.pinned = idx;
                s.hover = idx;
            }
            changed = true;
        } else if (event.type === 'mousemove') {
            // Tooltip mengikuti pointer, jadi tetap digambar ulang selama masih di atas irisan.
            changed = idx !== s.hover || idx >= 0;
            s.hover = idx;
        }
    }

    chart.canvas.style.cursor = s.hover >= 0 ? 'pointer' : '';
    if (changed) radialKick(chart);
}

function roundedRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

/**
 * Tooltip gelap bergaya tooltip Chart.js proyek ini. Isi: nama perpindahan, angka besar,
 * dan baris keterangan opsional dari payload.details (mis. "46 dari 60 lamaran lanjut").
 */
function drawRadialTooltip(ctx, s, { w, h, labels, values, unit, details, family }) {
    const idx = radialActive(s) >= 0 ? radialActive(s) : s.lastActive;
    if (idx < 0 || !s.pointer) return;

    const alpha = s.hl[idx];
    if (alpha < 0.02) return;

    const raw = values[idx];
    const valueText = typeof raw === 'number' && Number.isFinite(raw) ? withUnit(formatNumber(raw), unit) : '—';
    const title = String(labels[idx] ?? '');
    const detail = details?.[idx] ? String(details[idx]) : '';

    ctx.save();
    ctx.globalAlpha = alpha;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'alphabetic';

    ctx.font = `400 11px ${family}`;
    let textW = Math.max(ctx.measureText(title).width, detail ? ctx.measureText(detail).width : 0);
    ctx.font = `600 16px ${family}`;
    textW = Math.max(textW, ctx.measureText(valueText).width);

    const padX = 12;
    const bw = Math.ceil(textW + padX * 2);
    const bh = detail ? 72 : 54;

    // Di kanan-atas pointer; berbalik kalau mentok tepi kartu.
    let x = s.pointer.x + 14;
    let y = s.pointer.y - bh - 12;
    if (x + bw > w - 4) x = s.pointer.x - bw - 14;
    x = Math.max(4, Math.min(x, w - bw - 4));
    if (y < 4) y = s.pointer.y + 18;
    y = Math.max(4, Math.min(y, h - bh - 4)) + (1 - alpha) * 4; // sedikit naik saat muncul

    roundedRect(ctx, x, y, bw, bh, 8);
    ctx.fillStyle = INK;
    ctx.fill();

    ctx.fillStyle = 'rgba(253, 252, 248, 0.7)';
    ctx.font = `400 11px ${family}`;
    ctx.fillText(title, x + padX, y + 22);

    ctx.fillStyle = OFFWHITE;
    ctx.font = `600 16px ${family}`;
    ctx.fillText(valueText, x + padX, y + 43);

    if (detail) {
        ctx.fillStyle = 'rgba(253, 252, 248, 0.7)';
        ctx.font = `400 11px ${family}`;
        ctx.fillText(detail, x + padX, y + 61);
    }

    ctx.restore();
}

/** Mode 'below': satu kolom per irisan, berisi swatch warna, angka besar, dan nama tahap dua baris. */
function drawBelowLabels(ctx, { labels, values, unit, n, w, top, family, s }) {
    const margin = 12;
    const colW = (w - margin * 2) / n;

    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';

    for (let i = 0; i < n; i++) {
        const appear = s.enter[i];
        if (appear <= 0.002) continue;

        const hl = s.hl[i];
        const x = margin + colW * (i + 0.5);
        const raw = values[i];
        const hasValue = typeof raw === 'number' && Number.isFinite(raw);
        // Angka berhitung naik dari 0 mengikuti progres irisannya
        const text = hasValue ? withUnit(formatNumber(raw * appear), unit) : '—';

        ctx.save();
        ctx.globalAlpha = appear * (1 - (1 - RADIAL_DIM_LABEL) * s.dim * (1 - hl));
        ctx.translate(0, (1 - appear) * 8); // naik pelan sambil muncul

        // Latar lembut di kolom yang aktif
        if (hl > 0.01) {
            roundedRect(ctx, x - colW / 2 + 4, top - 10, colW - 8, 78, 12);
            ctx.fillStyle = `rgba(16, 16, 16, ${0.07 * hl})`;
            ctx.fill();
        }

        // Swatch warna irisan
        ctx.beginPath();
        ctx.arc(x, top + 5, 4.5 + 1.5 * hl, 0, Math.PI * 2);
        ctx.fillStyle = shade(i, n);
        ctx.fill();
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(16, 16, 16, 0.25)';
        ctx.stroke();

        // Angka besar
        ctx.fillStyle = INK;
        ctx.font = `600 18px ${family}`;
        ctx.fillText(text, x, top + 30);

        // Nama tahap, dua baris
        ctx.fillStyle = MUTED;
        ctx.font = `400 11px ${family}`;
        splitName(labels[i]).forEach((line, k) => {
            ctx.fillText(line, x, top + 48 + k * 14);
        });

        ctx.restore();
    }
}

function drawHalfRadial(chart, opts) {
    const { ctx, width: w, height: h } = chart;
    const { labels = [], values = [], unit = null, max = null, details = [] } = opts;
    const family = Chart.defaults.font.family;

    const below = RADIAL_LABELS === 'below';
    const padTop = below ? RADIAL_BELOW_PAD : RADIAL_PAD_TOP;
    const padBottom = RADIAL_PAD_BOTTOM;

    const nums = values.map((v) => (typeof v === 'number' && Number.isFinite(v) && v > 0 ? v : 0));
    const sum = nums.reduce((a, b) => a + b, 0);
    if (sum === 0) return;

    const n = nums.length;
    const scaleMax = max ?? Math.max(...nums);
    const s = radialState(chart, n);

    const cx = w / 2;
    let R;
    let cy;

    if (below) {
        // Semi-donut selebar kartu; label berderet di bawahnya.
        // Tengahkan secara vertikal: sisa tinggi dibagi rata atas dan bawah.
        const reserved = padTop + RADIAL_BELOW_GAP + RADIAL_BELOW_LABEL_H + padBottom;
        R = Math.max(40, Math.min(w / 2 - 12, h - reserved));
        const free = Math.max(0, h - reserved - R);
        cy = padTop + R + free / 2;
    } else {
        // Ruang kiri/kanan untuk label dihitung dari lebar teks sebenarnya
        const labelSpace = RADIAL_LEADER + measureLabelWidth(ctx, labels, nums, unit, family) + 6;
        R = Math.max(40, Math.min(w / 2 - labelSpace, h - padTop - padBottom));
        const free = Math.max(0, h - padTop - R - padBottom);
        cy = padTop + R + free / 2;
    }

    const r0 = R * RADIAL_INNER;

    // Geometri akhir (tanpa animasi) disimpan untuk deteksi hover dan tap
    s.geom = {
        cx, cy, r0, w, n,
        margin: 12,
        colW: (w - 24) / n,
        labelTop: below ? cy + RADIAL_BELOW_GAP : null,
        slices: [],
    };

    ctx.save();
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';

    // Tahap 1: hitung geometri tiap irisan (sudut akhir tetap, jari-jari dan sapuan mengikuti animasi)
    const items = [];
    let angle = Math.PI; // mulai dari kiri, searah jarum jam lewat atas

    nums.forEach((v, i) => {
        const sweep = RADIAL_ANGLE === 'share' ? (v / sum) * Math.PI : Math.PI / n;
        const start = angle;
        const end = angle + sweep;
        angle = end;

        if (v <= 0) return;

        const rOut = r0 + (R - r0) * Math.min(v / scaleMax, 1);
        s.geom.slices.push({ i, start, end, rOut });

        const p = s.enter[i];
        if (p <= 0.002) return; // belum mulai tumbuh

        const hl = s.hl[i];
        const rDraw = r0 + (rOut - r0) * (0.4 + 0.6 * p) + RADIAL_POP * hl;
        const mid = (start + end) / 2;
        const cos = Math.cos(mid);
        const sin = Math.sin(mid);

        const rDot = r0 + (rDraw - r0) * 0.6;
        const [r, g, b] = shadeRgb(i, n);
        const onDark = (r * 299 + g * 587 + b * 114) / 1000 < 110;

        items.push({
            i,
            v,
            p,
            hl,
            start,
            drawEnd: start + sweep * p, // irisan menyapu dari awalnya sampai sudut akhir
            rDraw,
            cos,
            sin,
            side: cos >= 0 ? 1 : -1, // 1 = label di kanan, -1 = di kiri
            dot: { x: cx + cos * rDot, y: cy + sin * rDot },
            edge: { x: cx + cos * rDraw, y: cy + sin * rDraw },
            elbow: { x: cx + cos * (rDraw + 14), y: cy + sin * (rDraw + 14) },
            inside: onDark ? OFFWHITE : INK, // warna garis di dalam irisan
            hy: cy + sin * (rDraw + 14),     // tinggi garis label, disesuaikan di tahap 2
            labelAlpha: p * (1 - (1 - RADIAL_DIM_LABEL) * s.dim * (1 - hl)),
        });
    });

    // Irisan paling aktif digambar terakhir supaya bayangannya tidak tertimpa tetangga
    [...items].sort((a, b) => a.hl - b.hl).forEach((it) => {
        ctx.save();
        ctx.globalAlpha = 1 - (1 - RADIAL_DIM_SLICE) * s.dim * (1 - it.hl);

        ctx.beginPath();
        ctx.arc(cx, cy, it.rDraw, it.start, it.drawEnd, false);
        ctx.arc(cx, cy, r0, it.drawEnd, it.start, true);
        ctx.closePath();

        ctx.shadowColor = `rgba(16, 16, 16, ${0.3 * it.hl})`;
        ctx.shadowBlur = 14 * it.hl;
        ctx.shadowOffsetY = 3 * it.hl;
        ctx.fillStyle = shade(it.i, n);
        ctx.fill();

        ctx.shadowColor = 'transparent';
        ctx.lineWidth = 2;
        ctx.strokeStyle = IVORY; // celah tipis antar irisan
        ctx.stroke();
        ctx.restore();
    });

    const tooltip = { w, h, labels, values, unit, details, family };

    // Mode 'below': label berderet di bawah chart, tanpa garis penghubung
    if (below) {
        drawBelowLabels(ctx, {
            labels, values, unit, n, w, family, s,
            top: cy + RADIAL_BELOW_GAP,
        });
        drawRadialTooltip(ctx, s, tooltip);
        ctx.restore();
        return;
    }

    // Tahap 2: cegah label bertumpuk di sisi yang sama. Ditumpuk dari bawah ke atas,
    // dan batas bawahnya dijaga supaya dua baris nama tahap (hy + 28) tetap di dalam canvas.
    const maxHy = Math.min(cy - 4, h - 34);

    [-1, 1].forEach((side) => {
        const group = items.filter((it) => it.side === side).sort((a, b) => b.hy - a.hy);
        let next = maxHy + RADIAL_LABEL_GAP;

        group.forEach((it) => {
            it.hy = Math.min(it.hy, next - RADIAL_LABEL_GAP);
            next = it.hy;
        });
    });

    // Tahap 3: gambar garis, titik, dan teks
    items.forEach((it) => {
        const { side } = it;
        const turnX = it.elbow.x + side * 8;
        const endX = it.elbow.x + side * 22;

        ctx.globalAlpha = it.labelAlpha;

        // Garis di dalam irisan + titik
        ctx.lineWidth = 1;
        ctx.strokeStyle = it.inside;
        ctx.beginPath();
        ctx.moveTo(it.dot.x, it.dot.y);
        ctx.lineTo(it.edge.x, it.edge.y);
        ctx.stroke();

        ctx.fillStyle = it.inside;
        ctx.beginPath();
        ctx.arc(it.dot.x, it.dot.y, 2.5, 0, Math.PI * 2);
        ctx.fill();

        // Garis di luar irisan: tepi -> siku -> garis datar
        ctx.strokeStyle = INK;
        ctx.beginPath();
        ctx.moveTo(it.edge.x, it.edge.y);
        ctx.lineTo(it.elbow.x, it.elbow.y);
        ctx.lineTo(turnX, it.hy);
        ctx.lineTo(endX, it.hy);
        ctx.stroke();

        // Teks: angka besar di atas garis, nama tahap (dua baris) di bawah garis
        const tx = endX + side * 4;
        ctx.textAlign = side === 1 ? 'left' : 'right';
        ctx.textBaseline = 'alphabetic';

        ctx.fillStyle = INK;
        ctx.font = `600 18px ${family}`;
        ctx.fillText(withUnit(formatNumber(it.v * it.p), unit), tx, it.hy - 5);

        ctx.fillStyle = MUTED;
        ctx.font = `400 11px ${family}`;
        splitName(labels[it.i]).forEach((line, k) => {
            ctx.fillText(line, tx, it.hy + 14 + k * 14);
        });
    });

    ctx.globalAlpha = 1;
    drawRadialTooltip(ctx, s, tooltip);
    ctx.restore();
}

const halfRadialPlugin = {
    id: 'halfRadial',
    afterDraw(chart, args, opts) {
        if (opts?.values) drawHalfRadial(chart, opts);
    },
    afterEvent(chart, args) {
        handleRadialEvent(chart, args.event);
    },
    beforeDestroy(chart) {
        const s = chart.$radial;
        if (!s) return;

        cancelAnimationFrame(s.raf);
        s.observer?.disconnect();
        if (chart.canvas) chart.canvas.style.cursor = '';
        chart.$radial = null;
    },
};

function renderHalfRadial(canvas, payload) {
    const { labels = [], series = [], unit = null, max = null, details = [] } = payload;
    const values = series[0]?.data ?? [];

    // Pembaca layar tidak bisa membaca isi canvas, jadi ringkasannya dipasang sebagai teks
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', labels.map((label, i) => {
        const v = values[i];
        const text = typeof v === 'number' && Number.isFinite(v) ? withUnit(formatNumber(v), unit) : 'belum ada data';
        return `${label}: ${text}`;
    }).join('; '));

    const chart = new Chart(canvas, {
        type: 'doughnut', // wadah kosong; irisan digambar oleh plugin
        data: { labels: [], datasets: [{ data: [] }] },
        options: {
            events: ['mousemove', 'mouseout', 'click'], // tap di layar sentuh ikut terbaca lewat click
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false },
                halfRadial: { labels, values, unit, max, details },
            },
        },
        plugins: [halfRadialPlugin],
    });

    startRadial(chart, canvas, values.length);
}

export function renderChart(canvas, payload) {
    Chart.getChart(canvas)?.destroy();

    const { type, labels = [], series = [], unit = null } = payload;

    if (type === 'halfradial') return renderHalfRadial(canvas, payload);

    const isDoughnut = type === 'doughnut';
    const horizontal = type === 'hbar';

    const valueAxis = {
        beginAtZero: true,
        grid: { color: NUDE },
        border: { display: false },
        ticks: { callback: (v) => withUnit(v, unit) },
    };
    const categoryAxis = { grid: { display: false }, border: { color: NUDE } };

    const options = {
        indexAxis: horizontal ? 'y' : 'x',
        cutout: isDoughnut ? '65%' : undefined,
        plugins: {
            legend: { display: isDoughnut || series.length > 1, position: isDoughnut ? 'right' : 'bottom' },
            tooltip: {
                callbacks: {
                    label: (ctx) => {
                        const prefix = isDoughnut
                            ? `${ctx.label}: `
                            : ctx.dataset.label ? `${ctx.dataset.label}: ` : '';
                        return prefix + withUnit(ctx.formattedValue, unit);
                    },
                },
            },
        },
    };

    if (!isDoughnut) {
        if (type === 'scatter') {
            options.scales = { x: valueAxis, y: valueAxis };
        } else {
            options.scales = horizontal
                ? { x: valueAxis, y: categoryAxis }
                : { x: categoryAxis, y: valueAxis };
        }
    }

    new Chart(canvas, {
        type: horizontal ? 'bar' : type,
        data: {
            labels,
            datasets: series.map((s, i) => buildDataset(type, s, i, series.length, labels)),
        },
        options,
    });
}

// Sparkline kartu angka: garis sangat tipis + gradasi lembut di bawahnya (ganti warna di sini)
const SPARK_LINE = 'rgba(16, 16, 16, 0.7)';
const SPARK_FILL_TOP = 'rgba(16, 16, 16, 0.22)';
const SPARK_FILL_BOTTOM = 'rgba(16, 16, 16, 0.02)';
const SPARK_LINE_WIDTH = 0.6; // px, bisa pecahan (Chart.js mendukung); 0.5 = paling tipis yang masih terbaca

/** Grafik mini tanpa sumbu, legenda, atau interaksi. values = array angka berurutan waktu. */
export function renderSparkline(canvas, values) {
    Chart.getChart(canvas)?.destroy();

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: values.map((_, i) => i + 1),
            datasets: [{
                data: values,
                borderColor: SPARK_LINE,
                borderWidth: SPARK_LINE_WIDTH,
                tension: 0.4,
                pointRadius: 0,
                fill: true,
                backgroundColor: (context) => {
                    const { ctx, chartArea } = context.chart;
                    if (!chartArea) return 'transparent';
                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, SPARK_FILL_TOP);
                    gradient.addColorStop(1, SPARK_FILL_BOTTOM);
                    return gradient;
                },
            }],
        },
        options: {
            events: [],
            animation: { duration: 600 },
            layout: { padding: { top: 4, bottom: 2, left: 1, right: 1 } },
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } },
        },
    });
}