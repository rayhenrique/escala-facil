// Browser plugin unavailable; Playwright fallback with a disposable synthetic database.
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.AUDIT_QA_PASSWORD && process.env.AUDIT_SHIFT_ID && process.env.AUDIT_DELETED_ID, 'Provide a disposable audit fixture.');
const origin = process.env.AUDIT_URL ?? 'http://127.0.0.1:8789';
const out = process.env.AUDIT_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function settled(page, accessible = false) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    if (accessible && process.env.AXE_MODULE) {
        await page.addScriptTag({ path: process.env.AXE_MODULE });
        const errors = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
        assert.deepEqual(errors.map(({ id, nodes }) => ({ id, targets: nodes.map(node => node.target) })), []);
    }
}
async function update(page, action) {
    const response = page.waitForResponse(r => r.url().includes('/update') && r.request().method() === 'POST');
    await action();
    await (await response).finished();
    await settled(page);
}
async function login(page, email, role) {
    await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(process.env.AUDIT_QA_PASSWORD);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(`**/${role}/dashboard`);
    await settled(page);
}
async function count(page, total) {
    await page.getByRole('status').filter({ hasText: `${total} evento(s) encontrado(s).` }).waitFor();
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', serviceWorkers: 'block' });
        const page = await context.newPage();
        page.setDefaultTimeout(30000);
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', e => { if (e.type() === 'error') errors.push(e.text()); });
        await login(page, 'audit-manager@example.test', 'manager');
        await page.goto(`${origin}/manager/shifts?search=Ana%20Revisada%20QA`, { waitUntil: 'networkidle' });
        const history = page.getByRole('link', { name: `Histórico de plantão #${process.env.AUDIT_SHIFT_ID}`, exact: true }).filter({ visible: true });
        const target = await history.getAttribute('href');
        await history.click();
        await page.waitForURL(target);
        await settled(page, true);
        await count(page, 13);
        assert.equal(await page.locator('#audit-record').inputValue(), process.env.AUDIT_SHIFT_ID);
        const shared = page.url();
        await update(page, () => page.getByRole('button', { name: 'Próxima', exact: true }).click());
        assert.equal(new URL(page.url()).searchParams.get('page'), '2');
        await update(page, () => page.locator('#audit-action').selectOption('updated'));
        await count(page, 12);
        assert.equal(new URL(page.url()).searchParams.get('page') ?? '1', '1');
        await update(page, () => page.locator('#audit-author').fill('Gerente Auditor QA'));
        await count(page, 12);
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await page.locator('#audit-action').inputValue(), 'updated');
        await settled(page, true);
        if (out) {
            await page.evaluate(() => scrollTo(0, 0));
            await page.screenshot({ path: `${out}/history-${width}.png`, fullPage: true });
            await page.screenshot({ path: `${out}/history-viewport-${width}.png` });
        }
        await update(page, () => page.locator('#audit-author').fill('Sem autor QA'));
        await count(page, 0);
        await page.getByText('Nenhuma alteração corresponde aos filtros.', { exact: true }).waitFor();
        await update(page, () => page.getByRole('button', { name: 'Limpar filtros', exact: true }).click());
        assert.equal(await page.locator('#audit-record').inputValue(), '');
        const back = page.getByRole('link', { name: '← Voltar à escala', exact: true });
        const backUrl = await back.getAttribute('href');
        await back.click();
        await page.waitForURL(backUrl);
        await settled(page);
        assert.equal(await page.locator('#list-search').inputValue(), 'Ana Revisada QA');
        await page.goto(shared, { waitUntil: 'networkidle' });
        await count(page, 13);
        await page.goto(`${origin}/manager/audit?entity=shift&record=${process.env.AUDIT_DELETED_ID}&action=deleted`, { waitUntil: 'networkidle' });
        await count(page, 1);
        await settled(page, true);
        assert.equal(await page.getByRole('button', { name: /Editar|Excluir|Salvar/ }).count(), 0);
        await page.locator('#audit-entity').focus();
        await page.keyboard.press('Tab');
        assert.equal(await page.locator('#audit-record').evaluate(el => el === document.activeElement), true);
        assert.deepEqual(errors, []);
        await context.close();
        console.log(`Audit history ${width}px: filters, pagination, refresh, contextual return, deleted record, keyboard and accessibility passed.`);
    }
    const context = await browser.newContext({ serviceWorkers: 'block' });
    const page = await context.newPage();
    page.setDefaultTimeout(30000);
    await login(page, 'audit-ana@example.test', 'professional');
    const response = await page.goto(`${origin}/manager/audit?entity=shift&record=${process.env.AUDIT_SHIFT_ID}`);
    assert.equal(response.status(), 403);
    await context.close();
    console.log('Professional access to audit history denied.');
} finally {
    await browser.close();
}
