const fs = require('fs'), path = require('path');
const out = path.join(__dirname, '..', 'public', 'assets', 'vendor');
fs.mkdirSync(out, { recursive: true });
const files = {
  'alpinejs/dist/cdn.min.js': 'alpine.min.js',
  'chart.js/dist/chart.umd.js': 'chart.umd.js',
  'tom-select/dist/js/tom-select.complete.min.js': 'tom-select.min.js',
  'tom-select/dist/css/tom-select.css': 'tom-select.css',
  'signature_pad/dist/signature_pad.umd.min.js': 'signature_pad.min.js',
  'fullcalendar/index.global.min.js': 'fullcalendar.min.js',
};
for (const [src, dst] of Object.entries(files)) {
  fs.copyFileSync(path.join(__dirname, '..', 'node_modules', src), path.join(out, dst));
  console.log('copied', dst);
}
