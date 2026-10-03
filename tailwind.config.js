/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        offwhite: '#FDFCF8', // latar halaman, dasar input
        ivory: '#F3F0E9',    // latar kartu, area sekunder, hover ringan
        nude: '#E3DBCC',     // border, elemen non-aktif, badge negatif
        obsidian: '#101010', // teks utama, tombol utama, ikon
        error: '#B42318',    // KHUSUS state error (usulan hex, bebas diganti)
      },
      fontFamily: {
        display: ['"Instrument Serif"', 'Georgia', 'serif'],
        sans: ['"Nunito Variable"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      fontSize: {
        h1: ['2rem', { lineHeight: '1.2' }],      // 32px
        h2: ['1.5rem', { lineHeight: '1.25' }],   // 24px
        stat: ['3rem', { lineHeight: '1' }],      // 48px (40px: text-[2.5rem])
      },
      borderRadius: {
        card: '16px',
        field: '8px', // input dan tombol
      },
      boxShadow: {
        card: '0 1px 2px rgba(16, 16, 16, 0.05)',
      },
      height: {
        btn: '42px',
      },
      maxWidth: {
        page: '1200px',
        auth: '400px',
      },
    },
  },
  plugins: [],
};