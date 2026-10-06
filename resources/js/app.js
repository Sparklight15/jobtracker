import './bootstrap';
import '@fontsource/instrument-serif/400-italic.css';
import '@fontsource-variable/nunito';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Muat skrip statistik hanya jika ada komponen statistik di halaman
if (document.querySelector('[data-stats-group]')) {
    import('./stats/lazy-load.js');
}