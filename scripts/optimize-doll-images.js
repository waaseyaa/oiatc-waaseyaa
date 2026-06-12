// One-off: web-optimize the doll concept renders and render their OG cards.
//
// No ImageMagick/sharp on this box, but Playwright/Chromium is available, so we
// resize + recompress through a canvas and render the OG cards as HTML
// screenshots (same approach as generate-og.js). Source PNGs live outside the
// repo; outputs land in public/images/doll (webp + jpg, max 1600px, < 300 KB)
// and public/images/og (1200x630 cards using the hero render).
//
// Run: node scripts/optimize-doll-images.js

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const projectRoot = path.resolve(__dirname, '..');
const SRC = 'C:/Users/jones/Projects/OIATC/doll-images';
const OUT_IMG = path.join(projectRoot, 'public', 'images', 'doll');
const OUT_OG = path.join(projectRoot, 'public', 'images', 'og');
const MAX_BYTES = 300 * 1024;

const FIGS = [
  { name: 'doll-hero', file: 'doll-hero.png' },
  { name: 'doll-internals', file: 'doll-internals.png' },
  { name: 'doll-hands', file: 'doll-hands.png' },
];

const CARDS = [
  { out: 'anishinaabemowin-doll.png', title: 'A doll that speaks Anishinaabemowin', url: 'oiatc.ca/anishinaabemowin/doll' },
  { out: 'anishinaabemowin-doll-build.png', title: 'Building the doll', url: 'oiatc.ca/anishinaabemowin/doll/build' },
  { out: 'anishinaabemowin-doll-process.png', title: "From an Elder's voice to a child's hands", url: 'oiatc.ca/anishinaabemowin/doll/process' },
];

function toDataUrl(file) {
  const buf = fs.readFileSync(file);
  return 'data:image/png;base64,' + buf.toString('base64');
}

function dataUrlToBuffer(dataUrl) {
  return Buffer.from(dataUrl.split(',')[1], 'base64');
}

// Encode through a canvas at a given max width / type / quality.
async function encode(page, dataUrl, maxW, type, quality) {
  return await page.evaluate(async ({ dataUrl, maxW, type, quality }) => {
    const img = new Image();
    await new Promise((res, rej) => { img.onload = res; img.onerror = rej; img.src = dataUrl; });
    const scale = Math.min(1, maxW / img.naturalWidth);
    const w = Math.round(img.naturalWidth * scale);
    const h = Math.round(img.naturalHeight * scale);
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    const ctx = c.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(img, 0, 0, w, h);
    return c.toDataURL(type, quality);
  }, { dataUrl, maxW, type, quality });
}

// Try qualities (and, if needed, widths) until the output fits MAX_BYTES.
async function encodeUnder(page, dataUrl, type) {
  for (const maxW of [1600, 1400, 1200]) {
    for (const q of [0.84, 0.78, 0.72, 0.66, 0.6]) {
      const out = await encode(page, dataUrl, maxW, type, q);
      const buf = dataUrlToBuffer(out);
      if (buf.length <= MAX_BYTES) return { buf, maxW, q };
    }
  }
  // Fall back to the smallest attempt if nothing fit (still write something).
  const out = await encode(page, dataUrl, 1200, type, 0.6);
  return { buf: dataUrlToBuffer(out), maxW: 1200, q: 0.6 };
}

function ogHtml(heroDataUrl, title, url) {
  return `<!doctype html><html><head><meta charset="utf-8"><style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{width:1200px;height:630px;overflow:hidden;position:relative;background:#141c28;
      font-family:"Segoe UI","Helvetica Neue",Arial,sans-serif;color:#fff}
    .hero{position:absolute;inset:0;background-image:url('${heroDataUrl}');background-size:cover;background-position:center}
    .scrim{position:absolute;inset:0;background:
      linear-gradient(90deg,rgba(10,14,20,0.86) 0%,rgba(10,14,20,0.55) 42%,rgba(10,14,20,0.20) 100%),
      linear-gradient(180deg,rgba(10,14,20,0.30) 0%,transparent 30%,rgba(10,14,20,0.78) 100%)}
    .accent-bar{position:absolute;left:0;top:0;bottom:0;width:6px;
      background:linear-gradient(180deg,#e8a020 0%,#3890b8 60%,transparent 100%);z-index:2}
    .content{position:relative;z-index:3;display:flex;flex-direction:column;justify-content:space-between;height:100%;padding:54px 80px 50px 84px}
    .top{display:flex;align-items:flex-start;justify-content:space-between}
    .brand-mark{font-size:15px;font-weight:700;letter-spacing:0.22em;text-transform:uppercase;color:#e8a020;
      text-shadow:0 1px 6px rgba(0,0,0,0.6)}
    .concept{font-size:11px;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:rgba(255,255,255,0.82);
      border:1px solid rgba(255,255,255,0.3);padding:5px 12px;border-radius:999px;background:rgba(10,14,20,0.35)}
    .headline{max-width:760px}
    .headline h1{font-size:54px;font-weight:700;line-height:1.05;letter-spacing:-0.022em;color:#fff;
      text-shadow:0 2px 14px rgba(0,0,0,0.55);display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
    .eyebrow{margin-top:18px;font-size:14px;font-weight:600;letter-spacing:0.04em;color:rgba(255,255,255,0.82)}
    .url{position:absolute;bottom:50px;right:80px;font-size:13px;font-weight:500;letter-spacing:0.04em;
      color:rgba(255,255,255,0.7);z-index:3;text-shadow:0 1px 6px rgba(0,0,0,0.6)}
  </style></head><body>
    <div class="hero"></div><div class="scrim"></div><div class="accent-bar"></div>
    <div class="content">
      <div class="top"><div class="brand-mark">OIATC</div><div class="concept">Concept render · AI-generated</div></div>
      <div class="headline"><h1>${title.replace(/&/g, '&amp;').replace(/</g, '&lt;')}</h1>
        <div class="eyebrow">Ontario Indigenous AI &amp; Technology Council</div></div>
    </div>
    <div class="url">${url}</div>
  </body></html>`;
}

(async () => {
  fs.mkdirSync(OUT_IMG, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });

  const heroDataUrl = toDataUrl(path.join(SRC, 'doll-hero.png'));

  // 1) Figure images: webp + jpg, max 1600px, under 300 KB.
  for (const fig of FIGS) {
    const src = path.join(SRC, fig.file);
    const dataUrl = toDataUrl(src);
    for (const type of ['image/webp', 'image/jpeg']) {
      const ext = type === 'image/webp' ? 'webp' : 'jpg';
      const { buf, maxW, q } = await encodeUnder(page, dataUrl, type);
      const dest = path.join(OUT_IMG, `${fig.name}.${ext}`);
      fs.writeFileSync(dest, buf);
      console.log(`img  ${fig.name}.${ext}  ${(buf.length / 1024).toFixed(0)} KB  (w<=${maxW}, q${q})`);
    }
  }

  // 2) OG cards from the hero render.
  for (const card of CARDS) {
    await page.setContent(ogHtml(heroDataUrl, card.title, card.url), { waitUntil: 'networkidle' });
    const dest = path.join(OUT_OG, card.out);
    await page.screenshot({ path: dest, clip: { x: 0, y: 0, width: 1200, height: 630 } });
    const kb = (fs.statSync(dest).size / 1024).toFixed(0);
    console.log(`og   ${card.out}  ${kb} KB  (${card.title})`);
  }

  await browser.close();
})();
