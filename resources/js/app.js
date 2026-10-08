import './bootstrap';
import './sidebar-nav';

// Font self-hosted (nama family cocok dengan tailwind.config.js)
import '@fontsource-variable/nunito';
import '@fontsource/instrument-serif/400.css';
import '@fontsource/instrument-serif/400-italic.css';

// Statistik Beranda: ambil data tiap grup secara lazy, lalu gambar kartu dan grafik.
// Sesuaikan path kalau file-nya tidak ada di resources/js/stats/
import './stats/lazy-load';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();