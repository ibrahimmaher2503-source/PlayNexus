const path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require('playwright');

async function run() {
  const expectedScreenCount = 15;
  const root = path.resolve(__dirname, '..');
  const input = path.join(root, 'docs', 'wireframes', 'playnexus-wireframes.html');
  const outputDir = path.join(root, 'deliverables', 'qa', 'wireframes');
  const fs = require('fs');
  fs.mkdirSync(outputDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  page.setDefaultTimeout(5000);
  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => consoleErrors.push(error.message));

  await page.goto(pathToFileURL(input).href, { waitUntil: 'load' });
  const nav = page.locator('[data-screen-target]');
  const screens = page.locator('[data-screen]');
  if (await nav.count() !== expectedScreenCount || await screens.count() !== expectedScreenCount) {
    throw new Error(`expected ${expectedScreenCount} navigation buttons and screens, found ${await nav.count()} and ${await screens.count()}`);
  }

  const duplicateIds = await page.evaluate(() => {
    const counts = new Map();
    document.querySelectorAll('[id]').forEach((element) => {
      counts.set(element.id, (counts.get(element.id) || 0) + 1);
    });
    return [...counts.entries()].filter(([, count]) => count > 1).map(([id]) => id);
  });
  if (duplicateIds.length) throw new Error(`duplicate element IDs: ${duplicateIds.join(', ')}`);

  const activate = async (name) => page.evaluate((target) => {
    document.querySelector(`[data-screen-target="${target}"]`).click();
  }, name);

  for (let index = 0; index < expectedScreenCount; index += 1) {
    const name = await nav.nth(index).getAttribute('data-screen-target');
    await activate(name);
    const visible = page.locator('[data-screen]:visible');
    if (await visible.count() !== 1) throw new Error(`screen ${index + 1}: expected one visible screen`);
    const headings = visible.locator('h1');
    if (await headings.count() !== 1) throw new Error(`screen ${index + 1}: expected exactly one h1`);
    const heading = await headings.textContent();
    if (!heading || !heading.trim()) throw new Error(`screen ${index + 1}: missing heading text`);

    const accessibilityIssues = await visible.evaluate((screen) => {
      const issues = [];
      screen.querySelectorAll('input, select, textarea').forEach((control) => {
        const labelled = control.getAttribute('aria-label')
          || control.getAttribute('aria-labelledby')
          || (control.id && document.querySelector(`label[for="${CSS.escape(control.id)}"]`))
          || control.closest('label');
        if (!labelled) issues.push(`unlabelled ${control.tagName.toLowerCase()}${control.id ? `#${control.id}` : ''}`);
      });
      screen.querySelectorAll('button').forEach((button) => {
        const name = button.getAttribute('aria-label') || button.textContent.trim();
        if (!name) issues.push('button without accessible name');
      });
      return issues;
    });
    if (accessibilityIssues.length) {
      throw new Error(`screen ${index + 1}: ${accessibilityIssues.join('; ')}`);
    }
    await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
    await page.screenshot({
      path: path.join(outputDir, `screen-${String(index + 1).padStart(2, '0')}-${name}.png`),
      fullPage: true,
    });
  }

  await activate('checkin');
  await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
  await page.screenshot({ path: path.join(outputDir, 'desktop-checkin.png'), fullPage: true });

  await page.setViewportSize({ width: 768, height: 1024 });
  await activate('checkout');
  await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
  await page.screenshot({ path: path.join(outputDir, 'tablet-checkout.png'), fullPage: true });

  await page.setViewportSize({ width: 390, height: 844 });
  await activate('registration');
  await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
  await page.screenshot({ path: path.join(outputDir, 'narrow-registration.png'), fullPage: true });

  const directionToggle = page.locator('#locale-toggle');
  if (await directionToggle.count()) {
    await directionToggle.click();
    if ((await page.locator('html').getAttribute('dir')) !== 'rtl') {
      throw new Error('direction toggle did not set RTL');
    }
  }

  await activate('incidents');
  await page.evaluate(() => {
    document.getElementById('live-status').textContent = '';
    document.activeElement?.blur();
    window.scrollTo(0, 0);
  });
  await page.screenshot({ path: path.join(outputDir, 'narrow-rtl-incidents.png'), fullPage: true });

  await browser.close();
  if (consoleErrors.length) throw new Error(`browser console errors: ${consoleErrors.join(' | ')}`);
  console.log(`Wireframe smoke passed: ${expectedScreenCount} screens, desktop/tablet/narrow and RTL layouts, no console errors.`);
}

run().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
