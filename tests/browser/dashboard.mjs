// Use only a local server with a disposable database and synthetic manager credentials.
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = process.env.DASHBOARD_URL ?? 'http://127.0.0.1:8767';
const email = process.env.DASHBOARD_QA_EMAIL;
const password = process.env.DASHBOARD_QA_PASSWORD;
assert.ok(email && password, 'Set DASHBOARD_QA_EMAIL/PASSWORD to a synthetic manager in a disposable local database.');
const out = process.env.DASHBOARD_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });

async function audit(page) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    if (!process.env.AXE_MODULE) return;
    await page.addScriptTag({ path: process.env.AXE_MODULE });
    const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
    assert.deepEqual(violations.map(({ id, nodes }) => ({ id, targets: nodes.map((node) => node.target) })), []);
}

async function waitPeriod(page, period, label) {
    await page.waitForFunction(({ period, label }) => {
        const button = [...document.querySelectorAll('[aria-controls="attention-list"]')].find((button) => button.textContent.trim() === label);
        return button?.getAttribute('aria-pressed') === 'true' && !button.disabled
            && (new URL(location.href).searchParams.get('period') ?? '3') === period;
    }, { period, label }).catch(async (error) => {
        console.error(await page.evaluate(() => ({ url: location.href, buttons: [...document.querySelectorAll('[aria-controls="attention-list"]')].map((button) => ({ label: button.textContent.trim(), pressed: button.getAttribute('aria-pressed'), disabled: button.disabled })) })));
        if (out) await page.screenshot({ path: `${out}/failed-period.png`, fullPage: true });
        throw error;
    });
    assert.equal(await page.locator('[aria-controls="attention-list"][aria-pressed="true"]').count(), 1);
    const keys = await page.locator('[data-attention]').evaluateAll((rows) => rows.map((row) => row.dataset.attention));
    assert.equal(keys.length, new Set(keys).size);
}

try {
    for (const width of [320, 390, 768, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: width < 768 ? 844 : 1000 }, serviceWorkers: 'block', reducedMotion: 'reduce' });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
        await page.goto(origin + '/login', { waitUntil: 'networkidle' });
        await page.locator('#email').fill(email);
        await page.locator('#password').fill(password);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/manager/dashboard');
        await page.locator('#attention-list').waitFor();
        assert.match(await page.title(), /Painel da gerência/);
        const announcement = page.getByRole('button', { name: 'Entendi, continuar' });
        if (await announcement.isVisible()) await announcement.click();
        await waitPeriod(page, '3', '3 dias');
        assert.equal(await page.getByText('Pendência mais antiga', { exact: true }).count(), 0);
        assert.equal(await page.getByText('Trocas aguardando aprovação', { exact: true }).count(), 0);
        const metrics = await page.locator('.coverage-metrics').innerText();

        // Keyboard activation, busy state, URL, and refresh. Filters replace the current URL.
        await page.route('**/livewire-*/update', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 350));
            await route.continue();
        });
        const today = page.getByRole('button', { name: 'Hoje', exact: true });
        await today.focus();
        await page.keyboard.press('Enter');
        await page.waitForFunction(() => [...document.querySelectorAll('[aria-controls="attention-list"]')].every((button) => button.disabled));
        await waitPeriod(page, '1', 'Hoje');
        assert.equal(await page.locator('.coverage-metrics').innerText(), metrics);
        await page.unroute('**/livewire-*/update');
        await page.getByRole('button', { name: '7 dias', exact: true }).click();
        await waitPeriod(page, '7', '7 dias');
        await page.reload({ waitUntil: 'networkidle' });
        await waitPeriod(page, '7', '7 dias');
        const nextPage = page.getByRole('button', { name: 'Próxima', exact: true });
        if (await nextPage.count() && await nextPage.isEnabled()) {
            const firstPage = await page.locator('[data-attention]').evaluateAll((rows) => rows.map((row) => row.dataset.attention));
            await nextPage.click();
            await page.waitForFunction(() => new URL(location.href).searchParams.get('page') === '2');
            await page.getByText('Página 2 de', { exact: false }).waitFor();
            const secondPage = await page.locator('[data-attention]').evaluateAll((rows) => rows.map((row) => row.dataset.attention));
            assert.deepEqual(firstPage.filter((key) => secondPage.includes(key)), []);
            await audit(page);
            if (out) {
                await page.evaluate(() => scrollTo(0, 0));
                await page.screenshot({ path: `${out}/dashboard-pendencias-${width}.png`, fullPage: true });
            }
            await today.click();
            await waitPeriod(page, '1', 'Hoje');
            assert.equal(new URL(page.url()).searchParams.get('page') ?? '1', '1');
            await page.getByRole('button', { name: '7 dias', exact: true }).click();
            await waitPeriod(page, '7', '7 dias');
        }
        await audit(page);
        if (out) {
            await page.evaluate(() => scrollTo(0, 0));
            await page.screenshot({ path: `${out}/dashboard-${width}.png`, fullPage: true });
        }

        // Every queue action navigates to a selected record, never approves it.
        const rowAction = page.locator('[data-attention] a').first();
        if (await rowAction.count()) {
            const target = new URL(await rowAction.getAttribute('href'));
            assert.ok(target.searchParams.has('focus') || target.searchParams.has('request'));
            await rowAction.click();
            await page.waitForURL(target.href);
            await page.getByText(/selecionado/).first().waitFor();
            await page.goBack();
            await waitPeriod(page, '7', '7 dias');
        }
        const metric = page.locator('.coverage-metric-danger a');
        await metric.click();
        await page.waitForURL('**/manager/shifts?filter=uncovered');
        assert.equal(await page.locator('#shift-filter').inputValue(), 'uncovered');
        assert.equal(await page.locator('#month').isDisabled(), true);
        await audit(page);
        await page.getByRole('link', { name: 'Limpar filtros', exact: true }).click();
        await page.locator('#shift-filter').waitFor();
        await page.waitForFunction(() => document.querySelector('#shift-filter')?.value === '');
        assert.equal(await page.locator('#month').isDisabled(), false);
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ width, keyboard: 'passed', filters: 'passed', refreshAndHistory: 'passed', actionableMetrics: 'passed', accessibility: 'passed', consoleErrors: errors.length }));
        await context.close();
    }
} finally {
    await browser.close();
}
