#!/usr/bin/env node
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

async function main() {
  const templatePath = path.join(__dirname, 'og-template.html');
  const outputPath = path.join(__dirname, '..', 'public', 'images', 'og-default.png');

  const template = fs.readFileSync(templatePath, 'utf-8');

  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1200, height: 630 });
  await page.setContent(template, { waitUntil: 'load' });
  await page.screenshot({ path: outputPath, type: 'png' });
  await browser.close();

  console.log('OG image saved to:', outputPath);
}

main().catch(err => { console.error(err); process.exit(1); });
