// Optional real-browser regression: NODE_PATH=<dev modules> node tests/receipt-browser.cjs
// Uses the production renderer and CSS with synthetic server payloads. No AI or live site.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'assets/js/chatbot.js'), 'utf8');
const helpers = source.slice(source.indexOf('        function el('), source.indexOf('        /** Inline markdown'));
const renderer = source.slice(source.indexOf('        function renderReceipt('), source.indexOf('        /** Action row under an answer'));
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  try {
    for (const direction of ['rtl', 'ltr']) {
      for (const width of [320, 390, 1280]) {
        const page = await browser.newPage({ viewport: { width, height: 844 } });
        await page.setContent(`<style>${fs.readFileSync(path.join(root, 'assets/css/chatbot.css'), 'utf8')} body{margin:0;padding:12px;box-sizing:border-box} .ssc-root{position:relative;width:100%;max-width:384px} .receipt-host{padding:12px;border:1px solid #ddd}</style><div class="ssc-root" dir="${direction}"><div class="receipt-host" id="answer">پاسخ / Answer</div></div>`);
        await page.addScriptTag({ content: `var cfg={direction:${JSON.stringify(direction)}};${helpers}${renderer}` });
        assert.equal(await page.evaluate(() => renderReceipt(document.getElementById('answer'), undefined)), false);
        assert.equal(await page.locator('details').count(), 0);
        await page.evaluate(() => renderReceipt(document.getElementById('answer'), {
          version: 1, kind: 'ai', label: 'پاسخ هوش مصنوعی / AI answer', detailsLabel: 'دربارهٔ این پاسخ',
          note: '<img src=x onerror=alert(1)> Sources do not verify every claim.',
          generated_at: '2026-11-02T01:02:03Z', timeLabel: 'زمان تهیه:',
          sources: [{ title: 'منبع '.repeat(50) + '<script>bad()</script>', url: 'https://example.com/' + 'long'.repeat(100) }]
        }));
        const summary = page.locator('summary');
        await summary.focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('details').evaluate(n => n.open), true);
        assert.equal(await page.locator('details img, details script').count(), 0);
        assert.equal(await page.locator('.ssc-sources').count(), 1);
        assert.equal(await page.locator('time').getAttribute('datetime'), '2026-11-02T01:02:03Z');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('details').evaluate(n => n.open), false);
        await page.close();
      }
    }
    console.log('PASS: receipt renderer at 320/390/1280, RTL/LTR, keyboard disclosure, no overflow, escaped metadata and absent receipt');
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
