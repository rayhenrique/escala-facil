// Browser plugin unavailable; Playwright against a disposable synthetic database.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.SWAP_BLOCKERS_FIXTURE && process.env.SWAP_BLOCKERS_QA_PASSWORD);
const fixture = JSON.parse(readFileSync(process.env.SWAP_BLOCKERS_FIXTURE, 'utf8').replace(/^\uFEFF/, ''));
const origin = process.env.SWAP_BLOCKERS_URL ?? 'http://127.0.0.1:8793';
const out = process.env.SWAP_BLOCKERS_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function settled(page, audit = false) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    if (audit && process.env.AXE_MODULE) {
        await page.addScriptTag({ path: process.env.AXE_MODULE });
        const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
        assert.deepEqual(violations.map(({ id, nodes }) => ({ id, targets: nodes.map(n => n.target) })), []);
    }
}
async function update(page, action) {
    const response = page.waitForResponse(r => r.url().includes('/update') && r.request().method() === 'POST');
    await action(); await (await response).finished(); await settled(page);
}
async function login(page) {
    await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
    await page.locator('#email').fill(fixture.managerEmail);
    await page.locator('#password').fill(process.env.SWAP_BLOCKERS_QA_PASSWORD);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/manager/dashboard'); await settled(page);
    const announcement = page.getByRole('button', { name: 'Entendi, continuar', exact: true });
    if (await announcement.isVisible()) await update(page, () => announcement.click());
}
async function open(page, id) {
    await page.goto(`${origin}/manager/swaps?request=${id}`, { waitUntil: 'networkidle' });
    await update(page, () => page.getByRole('button', { name: 'Aprovar e transferir', exact: true }).click());
    const dialog = page.getByRole('alertdialog');
    await dialog.waitFor(); return dialog;
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', serviceWorkers: 'block' });
        const page = await context.newPage(); page.setDefaultTimeout(30000); page.setDefaultNavigationTimeout(30000);
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        await login(page);
        let dialog = await open(page, fixture.valid);
        assert.equal(await dialog.getByRole('button', { name: 'Confirmar aprovação', exact: true }).isEnabled(), true);
        assert.match(await dialog.innerText(), /3 de 4/);
        assert.match(await dialog.innerText(), /participação: 4 de 4/);
        await settled(page, true);
        await update(page, () => dialog.getByRole('button', { name: 'Voltar à análise' }).click());
        assert.equal(await dialog.getByRole('button', { name: 'Aprovar…', exact: true }).isEnabled(), true);
        if (out) await page.screenshot({ path: `${out}/valid-${width}.png` });
        await update(page, () => dialog.getByRole('button', { name: 'Fechar', exact: true }).click());

        dialog = await open(page, fixture.quota);
        assert.equal(await dialog.getByRole('button', { name: 'Aprovar…', exact: true }).isDisabled(), true);
        await dialog.locator('[data-approval-blocker="substitute_quota"]').waitFor();
        assert.match(await dialog.innerText(), /4 de 4/);
        assert.equal(await dialog.getByRole('button', { name: 'Rejeitar…', exact: true }).isEnabled(), true);
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/quota-${width}.png` });
        await update(page, () => dialog.getByRole('button', { name: 'Rejeitar…', exact: true }).click());
        await update(page, () => dialog.getByRole('button', { name: 'Confirmar rejeição', exact: true }).click());
        await dialog.locator('#rejection-reason-error').waitFor();
        assert.equal(await dialog.locator('#rejection-reason').evaluate(n => n === document.activeElement), true);
        await settled(page, true);
        await update(page, () => dialog.getByRole('button', { name: 'Fechar', exact: true }).click());

        dialog = await open(page, fixture.changed);
        await dialog.locator('[data-approval-blocker="substituteShift_assignment"]').waitFor();
        assert.match(await dialog.innerText(), /Plantão de contrapartida #\d+: atribuição modificada/);
        assert.match(await dialog.innerText(), /Nenhum conflito de horários ou dobra identificado/);
        assert.equal(await dialog.getByRole('button', { name: 'Aprovar…', exact: true }).isDisabled(), true);
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/assignment-${width}.png` });
        await page.keyboard.press('Escape'); await dialog.waitFor({ state: 'detached' });
        assert.equal(await page.getByRole('button', { name: 'Aprovar e transferir', exact: true }).evaluate(n => n === document.activeElement), true);
        assert.deepEqual(errors, []); await context.close();
        console.log(`swap blockers ${width}px: valid 3/4, quota 4/4, counterpart assignment, keyboard, axe passed`);
    }
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'allow' });
    const first = await context.newPage(); first.setDefaultTimeout(30000);
    await login(first); const one = await open(first, fixture.stale);
    const second = await context.newPage(); second.setDefaultTimeout(30000);
    const two = await open(second, fixture.stale);
    await update(second, () => two.getByRole('button', { name: 'Voltar à análise' }).click());
    await update(second, () => two.getByRole('button', { name: 'Rejeitar…', exact: true }).click());
    await two.locator('#rejection-reason').fill('Decisão sintética para conferir concorrência.');
    await update(first, () => one.getByRole('button', { name: 'Confirmar aprovação' }).evaluate(n => { n.click(); n.click(); }));
    await one.waitFor({ state: 'detached' });
    await update(second, () => two.getByRole('button', { name: 'Confirmar rejeição' }).click());
    await two.getByRole('alert').filter({ hasText: 'Análise desatualizada.' }).waitFor();
    assert.match(await two.innerText(), /situação incompatível \(Aprovada\)/);
    await settled(second, true);
    await update(second, () => two.getByRole('button', { name: 'Atualizar análise', exact: true }).click());
    assert.equal(await two.getByRole('button', { name: 'Aprovar…', exact: true }).isDisabled(), true);
    assert.equal(await two.getByRole('button', { name: 'Rejeitar…', exact: true }).isDisabled(), true);
    await settled(second, true);
    if (out) await second.screenshot({ path: `${out}/stale.png` });
    await context.close(); console.log('competing decision, duplicate click, specific stale reason, explicit refresh and PWA passed');
} finally { await browser.close(); }
