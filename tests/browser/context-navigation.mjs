// Browser plugin not available. Playwright fallback; disposable local database only.
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = process.env.CONTEXT_URL ?? 'http://127.0.0.1:8767';
assert.ok(process.env.CONTEXT_QA_EMAIL && process.env.CONTEXT_QA_PASSWORD, 'Use synthetic CONTEXT_QA_EMAIL/PASSWORD in a disposable local database.');
const out = process.env.CONTEXT_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });

async function settled(page) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
}

async function destination(page, kind, id) {
    await page.locator(`[data-context-record="${kind}-${id}"]:visible`).waitFor();
    await page.waitForFunction(() => document.activeElement?.matches('[data-context-record]:not([data-context-record=""])'));
    assert.equal(await page.locator(`[data-context-record="${kind}-${id}"]:visible`).count(), 1);
    await settled(page);
    if (process.env.AXE_MODULE) {
        await page.addScriptTag({ path: process.env.AXE_MODULE });
        const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
        if (violations.length) {
            console.error(JSON.stringify(violations.map(({ id, nodes }) => ({ id, nodes: nodes.map(({ html, failureSummary }) => ({ html, failureSummary })) }))));
            if (out) await page.screenshot({ path: `${out}/failed-context.png`, fullPage: true });
        }
        assert.deepEqual(violations.map(({ id, nodes }) => ({ id, targets: nodes.map((node) => node.target) })), []);
    }
}

async function link(page, name) {
    const anchor = page.getByRole('navigation', { name: 'Navegação contextual' }).getByRole('link', { name, exact: true });
    const target = await anchor.getAttribute('href');
    await anchor.click();
    await page.waitForURL(target);
    await settled(page);
    return new URL(target);
}

try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: width < 1024 ? 844 : 1000 }, reducedMotion: 'reduce', serviceWorkers: width === 1440 ? 'allow' : 'block' });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
        await page.goto(origin + '/login', { waitUntil: 'networkidle' });
        await page.locator('#email').fill(process.env.CONTEXT_QA_EMAIL);
        await page.locator('#password').fill(process.env.CONTEXT_QA_PASSWORD);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/manager/dashboard');
        await page.getByRole('button', { name: '7 dias', exact: true }).click();
        await page.waitForFunction(() => document.querySelector('[aria-pressed="true"]')?.textContent.trim() === '7 dias');
        await settled(page);

        const shiftLink = page.locator('[data-attention^="uncovered-"] a').first();
        const shiftUrl = new URL(await shiftLink.getAttribute('href'));
        const shiftId = shiftUrl.searchParams.get('focus');
        await shiftLink.click();
        await destination(page, 'shift', shiftId);
        const sharedShiftUrl = page.url();
        await page.reload({ waitUntil: 'networkidle' });
        await destination(page, 'shift', shiftId);
        if (out) await page.screenshot({ path: `${out}/shift-${width}.png`, fullPage: true });

        const calendar = await link(page, 'Ver no calendário');
        const date = calendar.searchParams.get('selectedDate');
        assert.equal(await page.locator('#calendar-month').inputValue(), calendar.searchParams.get('month'));
        assert.equal(await page.locator('#calendar-sector').inputValue(), calendar.searchParams.get('sectorId'));
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(new URL(page.url()).searchParams.get('selectedDate'), date);
        await link(page, 'Ver relatório');
        await page.getByRole('button', { name: /^Cobertura e Indicadores/ }).click();
        await page.waitForFunction(() => new URL(location.href).searchParams.get('tab') === 'coverage');
        await link(page, '← Voltar ao calendário');
        assert.equal(new URL(page.url()).searchParams.get('selectedDate'), date);
        await link(page, '← Voltar à escala');
        await destination(page, 'shift', shiftId);
        await link(page, '← Voltar ao painel');
        assert.equal(new URL(page.url()).searchParams.get('period'), '7');

        // Start a fresh tab: contextual back must work with no previous browser history.
        const fresh = await context.newPage();
        await fresh.goto(sharedShiftUrl, { waitUntil: 'networkidle' });
        await destination(fresh, 'shift', shiftId);
        await link(fresh, '← Voltar ao painel');
        assert.equal(new URL(fresh.url()).searchParams.get('period'), '7');
        await fresh.close();

        const next = page.getByRole('button', { name: 'Próxima', exact: true });
        if (await next.count() && await next.isEnabled()) {
            await next.click();
            await page.waitForFunction(() => new URL(location.href).searchParams.get('page') === '2');
            await settled(page);
        }
        const swapLink = page.locator('[data-attention^="swap-"] a').first();
        const swapUrl = new URL(await swapLink.getAttribute('href'));
        const swapId = swapUrl.searchParams.get('request');
        const sourcePage = new URL(page.url()).searchParams.get('page') ?? '1';
        await swapLink.click();
        await destination(page, 'swap', swapId);
        await page.reload({ waitUntil: 'networkidle' });
        await destination(page, 'swap', swapId);
        if (out) await page.screenshot({ path: `${out}/swap-${width}.png`, fullPage: true });
        await link(page, 'Abrir plantão');
        const requestedShift = new URL(page.url()).searchParams.get('focus');
        await destination(page, 'shift', requestedShift);
        await link(page, '← Voltar às trocas');
        await destination(page, 'swap', swapId);
        await link(page, '← Voltar ao painel');
        assert.equal(new URL(page.url()).searchParams.get('page') ?? '1', sourcePage);

        if (width === 1440) {
            await page.evaluate(() => navigator.serviceWorker.ready);
            const cached = await page.evaluate(async () => (await Promise.all((await caches.keys()).map(async (name) => (await (await caches.open(name)).keys()).map((request) => request.url)))).flat());
            assert.equal(cached.some((url) => /\/(manager|professional)\//.test(url)), false);
        }
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ width, exactRecord: 'passed', highlightAndFocus: 'passed', nestedReturn: 'passed', refreshAndSharedLink: 'passed', serviceWorker: width === 1440 ? 'passed' : 'blocked for QA', consoleErrors: 0 }));
        await context.close();
    }
} finally {
    await browser.close();
}
