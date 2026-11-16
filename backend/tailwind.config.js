/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ['./app/Views/**/*.php', './app/Helpers/**/*.php', './public/assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        navy: { 50:'#eef0fb',100:'#d9dcf3',200:'#b3b8e6',300:'#8189d2',400:'#525bb5',500:'#353d96',600:'#262c78',700:'#1c205c',800:'#12153f',900:'#0b0d2e',950:'#06071c' },
        crimson: { 50:'#fdf2f4',100:'#fbe4e8',200:'#f6c9d2',300:'#ee9fb0',400:'#e26b86',500:'#cf3d60',600:'#b5244a',700:'#9b1631',800:'#80152d',900:'#6c152a' },
        brand: { red:'#ef1c25', navy:'#0b0d2e', crimson:'#9b1631' },
      },
      fontFamily: { sans: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'] },
      boxShadow: { soft: '0 1px 2px rgba(16,24,40,.04), 0 4px 16px rgba(16,24,40,.06)', glow: '0 10px 30px -10px rgba(155,22,49,.45)' },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
