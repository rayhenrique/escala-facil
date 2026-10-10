// Run against a local server with Playwright available (or PLAYWRIGHT_MODULE set).
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE
    ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const url = process.env.LANDING_URL ?? 'http://127.0.0.1:8765/landing';
const targets = '.gsap-friction-chaos, .gsap-friction-flow, .gsap-bento-card, .gsap-cta-box';
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
let scenarios = 0;

async function visibleContent(page) {
    const content = await page.locator(targets).evaluateAll((elements) => elements.map((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return { opacity: style.opacity, visibility: style.visibility, height: rect.height, text: element.textContent.trim() };
    }));
    assert.equal(content.length, 6);
    for (const item of content) {
        assert.equal(item.opacity, '1');
        assert.equal(item.visibility, 'visible');
        assert.ok(item.height > 50 && item.text.length > 20);
    }
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
}

try {
    for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
        for (const mode of ['normal', 'no-js', 'reduced', 'blocked-js', 'stalled-animation']) {
            const context = await browser.newContext({ viewport, javaScriptEnabled: mode !== 'no-js',
                reducedMotion: mode === 'reduced' ? 'reduce' : 'no-preference', serviceWorkers: 'block' });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            if (mode === 'blocked-js') await page.route('**/*.js', (route) => route.abort());
            if (mode === 'stalled-animation') await page.addInitScript(() => {
                window.requestAnimationFrame = () => 1;
                window.cancelAnimationFrame = () => {};
            });
            await page.goto(url, { waitUntil: 'networkidle' });
            assert.match(await page.title(), /Escala Fácil/);
            await visibleContent(page);
            await page.evaluate(() => scrollTo(0, document.body.scrollHeight));
            await visibleContent(page);
            await page.evaluate(() => scrollTo(0, document.body.scrollHeight / 2));
            await page.reload({ waitUntil: 'networkidle' });
            await visibleContent(page);
            await page.locator('.gsap-bento-card').last().scrollIntoViewIfNeeded();
            await visibleContent(page);
            if (mode === 'normal') {
                await page.setViewportSize(viewport.width > 768 ? { width: 390, height: 844 } : { width: 1440, height: 900 });
                await visibleContent(page);
                await page.emulateMedia({ reducedMotion: 'reduce' });
                await page.waitForFunction(() => !document.documentElement.classList.contains('lenis'));
                assert.equal(await page.evaluate(() => document.documentElement.classList.contains('lenis')), false);
                await visibleContent(page);
                await page.emulateMedia({ reducedMotion: 'no-preference' });
                await visibleContent(page);
                await page.setViewportSize(viewport);
                await page.locator('.gsap-cta-box').scrollIntoViewIfNeeded();
                const login = page.locator('.gsap-cta-box a').first();
                await login.click();
                await page.waitForURL('**/login');
                assert.ok(await page.locator('input[type="password"]').isVisible());
                await page.goto(url, { waitUntil: 'networkidle' });
                await page.locator('.gsap-bento-card').first().scrollIntoViewIfNeeded();
                if (process.env.LANDING_SCREENSHOTS) {
                    mkdirSync(process.env.LANDING_SCREENSHOTS, { recursive: true });
                    await page.screenshot({ path: `${process.env.LANDING_SCREENSHOTS}/${viewport.width}.png` });
                }
            }
            assert.deepEqual(errors, []);
            console.log(`PASS ${viewport.width}px ${mode}: load, fast scroll, middle refresh, content visibility`);
            scenarios++;
            await context.close();
        }
    }
    console.log(`${scenarios} landing scenarios passed`);
} finally {
    await browser.close();
}
