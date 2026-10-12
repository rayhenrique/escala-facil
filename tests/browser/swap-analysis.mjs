// Browser plugin unavailable: Playwright fallback, synthetic disposable database only.
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = process.env.DASHBOARD_URL ?? 'http://127.0.0.1:8767';
assert.ok(process.env.DASHBOARD_QA_EMAIL && process.env.DASHBOARD_QA_PASSWORD);
const out = process.env.DASHBOARD_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function login(page) {
    await page.goto(origin + '/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(process.env.DASHBOARD_QA_EMAIL);
    await page.locator('#password').fill(process.env.DASHBOARD_QA_PASSWORD);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/manager/dashboard');
    await settled(page);
    const announcement = page.getByRole('button', { name: 'Entendi, continuar' });
    if (await announcement.isVisible()) await announcement.click();
}
async function settled(page) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
}
async function audit(page) {
    await settled(page);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    if (!process.env.AXE_MODULE) return;
    await page.addScriptTag({ path: process.env.AXE_MODULE });
    const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
    if (violations.length) console.error(JSON.stringify(violations.map(({ id, nodes }) => ({ id, nodes: nodes.map(({ html, failureSummary }) => ({ html, failureSummary })) }))));
    assert.deepEqual(violations.map(({ id }) => id), []);
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', serviceWorkers: 'block' });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await login(page);
        const open = page.locator('button[wire\\:click^="openSwapAnalysis"]').first();
        await open.click();
        const dialog = page.locator('#decide-swap');
        await dialog.waitFor();
        await audit(page);
        await page.waitForFunction(() => document.activeElement?.matches('[data-confirm-cancel]'));
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.evaluate(() => document.activeElement?.closest('#decide-swap') !== null), true);
        await page.keyboard.press('Tab');
        assert.equal(await page.evaluate(() => document.activeElement?.matches('[data-confirm-cancel]')), true);
        if (out) await page.screenshot({ path: `${out}/analysis-${width}.png` });
        await dialog.getByRole('button', { name: 'Rejeitar…', exact: true }).click();
        await dialog.getByRole('button', { name: 'Confirmar rejeição' }).click();
        await dialog.locator('#rejection-reason-error').waitFor();
        await audit(page);
        await dialog.locator('#rejection-reason').fill('Manter a cobertura original.');
        // Escape returns to the initiating control; no decision was made.
        await page.keyboard.press('Escape');
        await dialog.getByRole('button', { name: 'Descartar alterações' }).click();
        await dialog.waitFor({ state: 'detached' });
        await page.waitForFunction(() => document.activeElement?.matches('button[wire\\:click^="openSwapAnalysis"]'));
        await open.click();
        await dialog.waitFor();
        await dialog.getByRole('button', { name: 'Aprovar…', exact: true }).click();
        await audit(page);
        if (out) await page.screenshot({ path: `${out}/confirm-${width}.png` });
        await dialog.getByRole('button', { name: 'Voltar à análise', exact: true }).click();
        await dialog.getByRole('button', { name: 'Fechar', exact: true }).click();
        await dialog.waitFor({ state: 'detached' });
        assert.deepEqual(errors, []);
        await context.close();
    }
    // Two independently reviewed snapshots, then repeated clicks and a competing rejection.
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'allow' });
    const first = await context.newPage();
    await login(first);
    const second = await context.newPage();
    await second.goto(origin + '/manager/dashboard', { waitUntil: 'networkidle' });
    const row = first.locator('[data-attention^="swap-"]').first();
    const key = await row.getAttribute('data-attention');
    await row.getByRole('button', { name: /Analisar pedido/ }).click();
    await second.locator(`[data-attention="${key}"]`).getByRole('button', { name: /Analisar pedido/ }).click();
    await first.getByRole('button', { name: 'Aprovar…', exact: true }).click();
    await second.getByRole('button', { name: 'Rejeitar…', exact: true }).click();
    await second.locator('#rejection-reason').fill('Manter escala original.');
    await first.getByRole('button', { name: 'Confirmar aprovação' }).evaluate((button) => { button.click(); button.click(); });
    await first.getByRole('alertdialog').waitFor({ state: 'detached' });
    assert.equal(await first.locator(`[data-attention="${key}"]`).count(), 0);
    await second.getByRole('button', { name: 'Confirmar rejeição' }).click();
    await second.getByRole('alertdialog').getByRole('alert').filter({ hasText: /desatualizada|mudaram|mudou|disponível/ }).waitFor();
    await audit(second);
    if (out) await second.screenshot({ path: `${out}/stale-request.png` });
    await context.close();
    console.log('Swap analysis: 320/390/1440, AA audit, focus trap/return, reason validation, explicit confirmation, duplicate click, stale competing decision and PWA passed.');
} finally { await browser.close(); }
