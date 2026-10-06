import { renderChart } from './chart-theme';

const show = (el, on = true) => el?.classList.toggle('hidden', !on);

function formatNumber(value) {
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 1 });
}

/** Kartu: loading | ok | insufficient | error | belum tersedia */
function applyCard(card, data, failed = false) {
    const q = (s) => card.querySelector(s);
    const [loading, value, note, empty, error] = [
        '[data-stat-loading]', '[data-stat-value]', '[data-stat-note]', '[data-stat-empty]', '[data-stat-error]',
    ].map(q);

    [loading, value, note, empty, error].forEach((el) => show(el, false));

    if (failed) return show(error);

    if (!data) {
        note.textContent = 'Belum tersedia.';
        return show(note);
    }

    if (data.status === 'insufficient') {
        q('[data-stat-empty-detail]').textContent =
            `Butuh minimal ${data.min_required} loker, baru ada ${data.current}.`;
        return show(empty);
    }

    const unit = data.unit ?? null;
    const number = data.value === null ? '—' : formatNumber(data.value);

    value.textContent = '';
    if (unit === 'Rp') value.append(`Rp ${number}`);
    else if (unit === '%') value.append(`${number}%`);
    else value.append(number);

    if (unit && unit !== 'Rp' && unit !== '%') {
        const suffix = document.createElement('span');
        suffix.className = 'ml-2 font-sans text-sm text-obsidian/70';
        suffix.textContent = unit;
        value.append(suffix);
    }
    show(value);

    if (data.note) {
        note.textContent = data.note;
        show(note);
    }
}

/** Panel grafik: loading | ok | insufficient | error | belum tersedia */
function applyChart(panel, data, failed = false) {
    const q = (s) => panel.querySelector(s);
    const [loading, canvas, empty, error] = [
        '[data-chart-loading]', '[data-chart-canvas]', '[data-chart-empty]', '[data-chart-error]',
    ].map(q);

    [loading, canvas, empty, error].forEach((el) => show(el, false));
    empty.classList.remove('flex');
    error.classList.remove('flex');

    const showFlex = (el) => { show(el); el.classList.add('flex'); };

    if (failed) return showFlex(error);

    const detail = q('[data-chart-empty-detail]');

    if (!data) {
        detail.textContent = 'Belum tersedia.';
        return showFlex(empty);
    }

    if (data.status === 'insufficient') {
        detail.textContent = `Butuh minimal ${data.min_required} loker, baru ada ${data.current}.`;
        return showFlex(empty);
    }

    show(canvas); // tampilkan dulu supaya Chart.js bisa mengukur ukurannya
    renderChart(canvas, data);
}

async function loadGroup(section) {
    const cards = section.querySelectorAll('[data-stat-card]');
    const panels = section.querySelectorAll('[data-chart-panel]');
    show(section.querySelector('[data-stats-retry]'), false);

    try {
        const response = await fetch(section.dataset.url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const payload = await response.json();

        cards.forEach((card) => applyCard(card, payload.cards?.[card.dataset.statCard]));
        panels.forEach((panel) => applyChart(panel, payload.charts?.[panel.dataset.chartPanel]));

        const sample = section.querySelector('[data-stats-sample]');
        if (sample) sample.textContent = `Berdasarkan ${payload.sample} loker`;
    } catch (e) {
        cards.forEach((card) => applyCard(card, null, true));
        panels.forEach((panel) => applyChart(panel, null, true));
        show(section.querySelector('[data-stats-retry]'));
    }
}

const sections = document.querySelectorAll('[data-stats-group][data-url]');

const observer = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            loadGroup(entry.target);
        });
    },
    { rootMargin: '200px 0px' },
);

sections.forEach((section) => observer.observe(section));

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-stats-retry]');
    if (button) loadGroup(button.closest('[data-stats-group]'));
});