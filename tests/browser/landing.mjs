// Run against a local server with Playwright available (or PLAYWRIGHT_MODULE set).
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE
    ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const url = process.env.LANDING_URL ?? 'http://127.0.0.1:8765/landing';
const targets = '.gsap-friction-chaos, .gsap-friction-flow, .gsap-bento-card, .gsap-cta-box';
const widths = (process.env.LANDING_WIDTHS ?? '1440,768,390,320').split(',').map(Number);
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
    assert.equal(await page.locator('.gsap-simulation-card ol > li').count(), 3);
    const steps = await page.locator('.gsap-simulation-card ol > li').evaluateAll((elements) => elements.map((el) => {
        const style = getComputedStyle(el);
        return { opacity: style.opacity, visibility: style.visibility, height: el.getBoundingClientRect().height };
    }));
    for (const step of steps) {
        assert.equal(step.opacity, '1');
        assert.equal(step.visibility, 'visible');
        assert.ok(step.height > 50);
    }
}

try {
    for (const viewport of [{ width: 1440, height: 1000 }, { width: 768, height: 1024 }, { width: 390, height: 844 }, { width: 320, height: 568 }]) {
        if (!widths.includes(viewport.width)) continue;
        for (const mode of ['normal', 'no-js', 'reduced', 'blocked-js', 'stalled-animation']) {
            const context = await browser.newContext({ viewport, javaScriptEnabled: mode !== 'no-js',
                reducedMotion: mode === 'reduced' ? 'reduce' : 'no-preference', serviceWorkers: 'block' });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            if (mode === 'normal') page.on('console', (message) => {
                // O bloqueio do worker é deliberado para isolar cada cenário.
                if (message.text() === 'Service Worker registration blocked by Playwright') return;
                if (['error', 'warning'].includes(message.type())) errors.push(message.text());
            });
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
                await page.goto(url, { waitUntil: 'networkidle' });
                if (process.env.AXE_MODULE) {
                    await page.addScriptTag({ path: process.env.AXE_MODULE });
                    const audit = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
                    assert.deepEqual(audit.map(({ id, nodes }) => ({ id, targets: nodes.map((node) => node.target) })), []);
                }
                await page.keyboard.press('Tab');
                assert.equal(await page.locator('.landing-skip-link').evaluate((el) => el === document.activeElement), true);
                await page.keyboard.press('Enter');
                await page.waitForFunction(() => document.activeElement.id === 'hero');
                if (viewport.width < 1024) {
                    const menu = page.locator('button[aria-controls="landing-mobile-menu"]');
                    await menu.focus();
                    await page.keyboard.press('Enter');
                    assert.equal(await menu.getAttribute('aria-expanded'), 'true');
                    await page.locator('#landing-mobile-menu a').first().waitFor({ state: 'visible' });
                    await page.keyboard.press('Tab');
                    assert.equal(await page.locator('#landing-mobile-menu').evaluate((el) => el.contains(document.activeElement)), true);
                    await page.keyboard.press('Escape');
                    assert.equal(await menu.getAttribute('aria-expanded'), 'false');
                    assert.equal(await menu.evaluate((el) => el === document.activeElement), true);
                    await page.keyboard.press('Enter');
                    const resourceLink = page.locator('#landing-mobile-menu a[href="#superpoderes"]');
                    await resourceLink.waitFor({ state: 'visible' });
                    await resourceLink.focus();
                    await page.keyboard.press('Enter');
                    assert.equal(await menu.getAttribute('aria-expanded'), 'false');
                } else {
                    await page.locator('.gsap-hero-ctas a').last().focus();
                    await page.keyboard.press('Enter');
                }
                await page.waitForFunction(() => location.hash === '#superpoderes' && document.activeElement.id === 'superpoderes');
                assert.ok((await page.locator('#superpoderes').boundingBox()).y >= 80);
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
                if (process.env.LANDING_SCREENSHOTS) {
                    mkdirSync(process.env.LANDING_SCREENSHOTS, { recursive: true });
                    await page.screenshot({ path: `${process.env.LANDING_SCREENSHOTS}/${viewport.width}.png`, fullPage: true });
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
