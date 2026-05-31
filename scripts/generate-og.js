#!/usr/bin/env node
// Renders OG (social-card) PNGs from the HTML templates in this folder.
// Requires: npm install playwright && npx playwright install chromium
//
// Each card is { template, output }. Templates live next to this file;
// outputs land in public/images/. Add a new card to render it.
// Run: node scripts/generate-og.js

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const cards = [
  { template: 'og-template.html',                output: 'og-default.png' },
  { template: 'og-template-massey-solar.html',   output: 'og-massey-solar-project.png' },
  { template: 'og-template-sagamok-portal.html', output: 'og-sagamok-portal.png' },
];

async function main() {
  const scriptDir = __dirname;
  const imagesDir = path.join(scriptDir, '..', 'public', 'images');

  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1200, height: 630 });

    for (const { template, output } of cards) {
      const templatePath = path.join(scriptDir, template);
      const outputPath = path.join(imagesDir, output);
      const html = fs.readFileSync(templatePath, 'utf-8');
      await page.setContent(html, { waitUntil: 'load' });
      await page.screenshot({ path: outputPath, type: 'png' });
      console.log('Rendered:', path.relative(path.join(scriptDir, '..'), outputPath));
    }
  } finally {
    await browser.close();
  }
}

main().catch(err => { console.error(err); process.exit(1); });
