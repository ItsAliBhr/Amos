// Copies pinned frontend libraries into public/vendor (committed and shipped in the ZIP).
import { cpSync, mkdirSync, rmSync } from 'node:fs';

const out = 'public/vendor';
rmSync(out, { recursive: true, force: true });
const copy = (from, to) => { mkdirSync(to.substring(0, to.lastIndexOf('/')), { recursive: true }); cpSync(from, to, { recursive: true }); };

copy('node_modules/bootstrap/dist/css/bootstrap.rtl.min.css', `${out}/bootstrap/bootstrap.rtl.min.css`);
copy('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', `${out}/bootstrap/bootstrap.bundle.min.js`);
copy('node_modules/bootstrap-icons/font/bootstrap-icons.min.css', `${out}/bootstrap-icons/bootstrap-icons.min.css`);
copy('node_modules/bootstrap-icons/font/fonts', `${out}/bootstrap-icons/fonts`);
copy('node_modules/chart.js/dist/chart.umd.js', `${out}/chartjs/chart.umd.js`);
copy('node_modules/vazirmatn/fonts/webfonts/Vazirmatn[wght].woff2', `${out}/vazirmatn/Vazirmatn-Variable.woff2`);
copy('node_modules/vazirmatn/OFL.txt', `${out}/vazirmatn/OFL.txt`);
console.log('Assets copied to', out);
