#!/usr/bin/env node
// Renders OG (social-card) PNGs for oiatc.ca.
// Requires: npm install playwright && npx playwright install chromium
//
// Two sources of cards:
//   1. Hand-crafted overrides — bespoke layouts (e.g. Massey explainer,
//      Sagamok disclosure). Listed in `overrides` below by template path.
//   2. Auto-discovered pages — every templates/**/*.html.twig that extends
//      base.html.twig and isn't already in `overrides` gets a generic card
//      rendered from og-template-auto.html, using the page's own
//      {% block title %} / {% block description %} content.
//
// Add a hand-crafted card by creating its template file and registering it
// in `overrides`. Add a new page anywhere under templates/ that extends base
// and the next run renders a card for it automatically.
//
// Run: node scripts/generate-og.js

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const projectRoot = path.join(__dirname, '..');
const templatesDir = path.join(projectRoot, 'templates');
const scriptDir = __dirname;
const imagesDir = path.join(projectRoot, 'public', 'images');
const autoOutDir = path.join(imagesDir, 'og');

// --only-missing (or OG_ONLY_MISSING=1): render only cards whose PNG does not
// already exist, leaving existing cards byte-for-byte untouched. This is the
// mode CI runs so a push never churns every card — it only fills gaps for new
// pages. Run without the flag locally to deliberately refresh existing cards.
const onlyMissing = process.argv.includes('--only-missing') || process.env.OG_ONLY_MISSING === '1';

function skipBecauseExists(outputAbs, label) {
  if (onlyMissing && fs.existsSync(outputAbs)) {
    console.log('skip (exists): ', path.relative(projectRoot, outputAbs), label ? `(${label})` : '');
    return true;
  }
  return false;
}

// Hand-crafted overrides, keyed by the template path they belong to (relative
// to templates/). Auto-discovery skips these. Output paths are relative to
// public/images/.
const overrides = {
  // Site default (not actually tied to a single template — rendered for the
  // failsafe and the "framework default" feel).
  '__default__': { template: 'og-template.html', output: 'og-default.png' },

  // Per-page bespoke cards.
  'disclosure/sagamok-portal.html.twig': {
    template: 'og-template-sagamok-portal.html',
    output: 'og-sagamok-portal.png',
  },
};

// Routes that aren't directly derivable from a template path. For most pages
// the URL is /<template-path-without-extension>; this map covers the rest.
const routeOverrides = {
  'home.html.twig': '/',
};

// Files / directories under templates/ to ignore when auto-discovering. These
// are partials, admin views, or otherwise not user-facing pages.
const ignoreTemplatePatterns = [
  /^_/,           // leading underscore = partial convention
  /^admin\//,     // admin views — internal, not socially shared
  /^_macros\//,
  // News index/post extend base.html.twig but are not generic auto pages: the
  // index has its own URL (/news) and the post template is a dynamic per-post
  // shell whose {% block title %} is "{{ post.title }} ...". Per-post cards come
  // from the news manifest path below, so skip the whole news/ dir here.
  /^news\//,
];

function listTwigTemplates(dir, base = dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      out.push(...listTwigTemplates(full, base));
    } else if (entry.isFile() && entry.name.endsWith('.html.twig')) {
      // Normalise to forward slashes so the slug/url/override logic (which all
      // assume '/') works on Windows too, where path.relative yields '\'.
      // Without this, slugForTemplate leaves the separator in and cards are
      // written to og/<dir>/<name>.png — a path PHP's flat, dash-joined slug
      // (og/<dir>-<name>.png) never looks up, so the card silently 404s.
      out.push(path.relative(base, full).split(path.sep).join('/'));
    }
  }
  return out;
}

function shouldIgnore(relPath) {
  return ignoreTemplatePatterns.some(rx => rx.test(relPath));
}

function extendsBase(html) {
  return /\{%\s*extends\s+['"]base\.html\.twig['"]\s*%\}/.test(html);
}

function readBlock(html, blockName) {
  const rx = new RegExp(`\\{%\\s*block\\s+${blockName}\\s*%\\}([\\s\\S]*?)\\{%\\s*endblock(?:\\s+${blockName})?\\s*%\\}`);
  const m = html.match(rx);
  if (!m) return null;
  return m[1].trim();
}

// Strip common site title suffixes so the card text reads cleanly.
function cleanTitle(raw) {
  return raw
    .replace(/\s*·\s*OIATC\s*$/i, '')
    .replace(/\s*[—-]\s*OIATC\s*$/i, '')
    .replace(/&amp;/g, '&')
    .replace(/&nbsp;/g, ' ')
    .trim();
}

function cleanDescription(raw) {
  return raw
    .replace(/&amp;/g, '&')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

// Escape special characters so user-provided text doesn't break the HTML we
// substitute it into.
function htmlEscape(s) {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// Template path -> URL slug. "explainers/where-your-data-lives.html.twig"
// becomes "explainers-where-your-data-lives".
function slugForTemplate(relPath) {
  return relPath.replace(/\.html\.twig$/, '').replace(/\//g, '-');
}

// Template path -> public URL. Returns the canonical-style "oiatc.ca/..."
// (no scheme) suitable for the URL watermark in the card.
function urlForTemplate(relPath) {
  if (routeOverrides[relPath] !== undefined) {
    return 'oiatc.ca' + routeOverrides[relPath].replace(/\/$/, '') + (routeOverrides[relPath] === '/' ? '' : '');
  }
  return 'oiatc.ca/' + relPath.replace(/\.html\.twig$/, '');
}

function discoverAutoPages() {
  const all = listTwigTemplates(templatesDir);
  const overrideKeys = new Set(Object.keys(overrides));
  const pages = [];
  for (const rel of all) {
    if (shouldIgnore(rel)) continue;
    if (overrideKeys.has(rel)) continue;
    if (rel === 'base.html.twig') continue;
    const html = fs.readFileSync(path.join(templatesDir, rel), 'utf-8');
    if (!extendsBase(html)) continue;
    const titleRaw = readBlock(html, 'title');
    const descRaw = readBlock(html, 'description');
    if (!titleRaw || !descRaw) {
      console.warn('skip (missing title/description block):', rel);
      continue;
    }
    pages.push({
      templatePath: rel,
      title: cleanTitle(titleRaw),
      description: cleanDescription(descRaw),
      url: urlForTemplate(rel),
      output: `og/${slugForTemplate(rel)}.png`,
    });
  }
  return pages;
}

async function renderTemplateString(page, html, outputAbs) {
  fs.mkdirSync(path.dirname(outputAbs), { recursive: true });
  await page.setContent(html, { waitUntil: 'load' });
  await page.screenshot({ path: outputAbs, type: 'png' });
}

async function main() {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1200, height: 630 });

    // 1. Hand-crafted overrides (use the dedicated template file as-is).
    for (const [key, { template, output }] of Object.entries(overrides)) {
      const tplPath = path.join(scriptDir, template);
      const outPath = path.join(imagesDir, output);
      if (skipBecauseExists(outPath, key === '__default__' ? 'default' : key)) continue;
      const html = fs.readFileSync(tplPath, 'utf-8');
      await renderTemplateString(page, html, outPath);
      console.log('hand-crafted:', path.relative(projectRoot, outPath), key === '__default__' ? '(default)' : `(for ${key})`);
    }

    // 2. Auto-discovered pages (substitute into og-template-auto.html).
    const autoTemplate = fs.readFileSync(path.join(scriptDir, 'og-template-auto.html'), 'utf-8');
    const pages = discoverAutoPages();
    for (const p of pages) {
      const outPath = path.join(imagesDir, p.output);
      if (skipBecauseExists(outPath, p.title.slice(0, 40))) continue;
      const html = autoTemplate
        .replace('{{TITLE}}', htmlEscape(p.title))
        .replace('{{DESCRIPTION}}', htmlEscape(p.description))
        .replace('{{URL}}', htmlEscape(p.url));
      await renderTemplateString(page, html, outPath);
      console.log('auto:        ', path.relative(projectRoot, outPath), `(${p.title.slice(0, 50)}${p.title.length > 50 ? '…' : ''})`);
    }

    // 3. Per-post news cards. News posts are dynamic entities, not static
    //    templates, so the list comes from the app (app:news-og-manifest) and
    //    each post gets a card at og/news/<slug>.png.
    const newsTemplate = fs.readFileSync(path.join(scriptDir, 'og-template-news.html'), 'utf-8');
    let newsPosts = [];
    try {
      const raw = execSync('php bin/waaseyaa app:news-og-manifest', { cwd: projectRoot, encoding: 'utf-8' });
      const start = raw.indexOf('[');
      const end = raw.lastIndexOf(']');
      newsPosts = start !== -1 && end !== -1 ? JSON.parse(raw.slice(start, end + 1)) : [];
    } catch (err) {
      console.warn('news manifest unavailable, skipping per-post cards:', err.message);
    }
    for (const post of newsPosts) {
      const outPath = path.join(imagesDir, 'og', 'news', post.slug + '.png');
      if (skipBecauseExists(outPath, post.slug)) continue;
      const html = newsTemplate
        .replace('{{TITLE}}', htmlEscape(post.title || ''))
        .replace('{{DESCRIPTION}}', htmlEscape(post.meta_description || ''))
        .replace('{{URL}}', htmlEscape('oiatc.ca/news/' + post.slug));
      await renderTemplateString(page, html, outPath);
      console.log('news:        ', path.relative(projectRoot, outPath), `(${(post.title || '').slice(0, 50)})`);
    }

    if (pages.length === 0 && newsPosts.length === 0 && Object.keys(overrides).length === 0) {
      console.warn('No cards rendered. Check that templates/ has base-extending pages or that overrides is populated.');
    }
  } finally {
    await browser.close();
  }
}

main().catch(err => { console.error(err); process.exit(1); });
