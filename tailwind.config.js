/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./public/**/*.php', './src/**/*.php'],
  theme: {
    extend: {
      // TODO: KAMC-Brand-Tokens aus kamc.koeln (Elementor Global Colors) eintragen
      // colors: { navy: '#…', akzent: '#…' },
    },
  },
  plugins: [],
};
