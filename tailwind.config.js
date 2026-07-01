/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./public/**/*.php', './src/**/*.php'],
  theme: {
    extend: {
      // KAMC-Brand-Tokens — 1:1 aus kamc.koeln (Elementor Global Colors).
      colors: {
        navy: {
          DEFAULT: '#030053', // Elementor primary
          950: '#02003a',
          900: '#030053',
          800: '#0a0a6e',
          700: '#16167f',
        },
        akzent: {
          DEFAULT: '#CF0000', // warmer Akzent (KAMC-Rot) — sparsam für Aktionen/Status
          600: '#CF0000',
          700: '#a90707',
        },
        himmel: {
          DEFAULT: '#64C6FD', // Sky
          light: '#C7EBFF',
        },
        sand: '#F6F4EF',      // warme Flaeche (Weiss/Sand)
        schiefer: '#6C6B7E',  // Elementor secondary (Text gedaempft)
        nebel: '#EEEEEE',
        stahl: '#C7C7C7',
      },
      fontFamily: {
        // Headlines: Slab-Serif (Display) · Fliesstext: Open Sans · UI: Roboto
        display: ['"Roboto Slab"', 'Georgia', 'serif'],
        sans: ['"Open Sans"', 'system-ui', 'sans-serif'],
        ui: ['Roboto', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        card: '0 1px 2px rgba(3,0,83,0.06), 0 8px 24px -12px rgba(3,0,83,0.18)',
      },
      borderRadius: {
        xl2: '1.25rem',
      },
    },
  },
  plugins: [],
};
