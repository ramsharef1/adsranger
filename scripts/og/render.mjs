// R2-47 share images: renders storage/app/share-cards.json (from `php artisan site:share-cards`) to
// <outDir>/<lang>/<slug>.jpg at 1200 x 630 with Playwright. Run where Chromium + Playwright exist (E1's build box), then commit the JPEGs.
// Usage: node scripts/og/render.mjs storage/app/share-cards.json public/brand/og
import { readFileSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const [, , dataFile, outDir] = process.argv;
const cards = JSON.parse(readFileSync(dataFile, 'utf8'));
const mark = readFileSync(new URL('../../public/brand/adsranger-mark-teal.svg', import.meta.url), 'utf8');
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const page = (c) => {
  const ar = c.lang === 'ar';
  const footer = ar
    ? '<span class="brand" dir="ltr">AdsRanger</span><span class="sep">·</span><span>عربية أولًا، للإعلانات والبحث</span>'
    : '<span class="brand">AdsRanger</span><span class="sep">·</span><span>Arabic-first, for ads and search</span>';
  return `<!doctype html><html lang="${c.lang}" dir="${ar ? 'rtl' : 'ltr'}"><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&family=IBM+Plex+Sans:wght@400;600;700&display=block" rel="stylesheet">
<style>
*{box-sizing:border-box}html,body{margin:0}
body{width:1200px;height:630px;background:linear-gradient(135deg,#0b3b37 0%,#0f766e 100%);color:#fff;
font-family:${ar ? '"IBM Plex Sans Arabic"' : '"IBM Plex Sans","IBM Plex Sans Arabic"'},sans-serif;display:flex;flex-direction:column;
padding:72px 80px 56px;text-align:${ar ? 'right' : 'left'}}
.eyebrow{font-size:30px;font-weight:600;color:#ccfbf1;min-height:42px}
.headline{font-size:${ar ? 68 : 66}px;font-weight:700;line-height:1.25;margin-top:28px;max-width:1040px}
.sub{font-size:34px;color:#e2f7f3;margin-top:24px}
.foot{margin-top:auto;display:flex;align-items:center;gap:16px;font-size:28px;color:#ccfbf1}
.foot svg{width:56px;height:56px}.brand{font-family:"IBM Plex Sans",sans-serif;font-weight:700;color:#fff}.sep{opacity:.7}
.url{margin-${ar ? 'right' : 'left'}:auto;font-family:"IBM Plex Sans",sans-serif;direction:ltr}
.mark{background:#fff;border-radius:14px;padding:6px;display:flex}
</style></head><body>
<div class="eyebrow">${esc(c.eyebrow)}</div>
<div class="headline">${esc(c.headline)}</div>
${c.subline ? `<div class="sub">${esc(c.subline)}</div>` : ''}
<div class="foot"><span class="mark">${mark}</span>${footer}<span class="url">adsranger.com</span></div>
</body></html>`;
};

const browser = await chromium.launch();
const p = await browser.newPage({ viewport: { width: 1200, height: 630 } });
for (const c of cards) {
  mkdirSync(`${outDir}/${c.lang}`, { recursive: true });
  await p.setContent(page(c), { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  const over = await p.evaluate(() => document.body.scrollHeight > 630);
  if (over) { console.error(`overflow: ${c.lang}/${c.slug}`); process.exitCode = 1; }
  await p.screenshot({ path: `${outDir}/${c.lang}/${c.slug}.jpg`, type: 'jpeg', quality: 82 });
}
await browser.close();
console.log(`${cards.length} images`);
