// Browser plugin unavailable; Playwright fallback. This changes only disposable fixture shifts.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.PREVENTIVE_FIXTURE && process.env.PREVENTIVE_QA_PASSWORD, 'Provide a disposable synthetic fixture.');
const fixture = JSON.parse(readFileSync(process.env.PREVENTIVE_FIXTURE, 'utf8').replace(/^\uFEFF/, ''));
const origin = process.env.PREVENTIVE_URL ?? 'http://127.0.0.1:8790';
const out = process.env.PREVENTIVE_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function settled(page, accessible = false) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    if (accessible && process.env.AXE_MODULE) {
        await page.addScriptTag({ path: process.env.AXE_MODULE });
        const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
        assert.deepEqual(violations.map(({ id, nodes }) => ({ id, targets: nodes.map(n => n.target) })), []);
    }
}
async function update(page, action) {
    const response = page.waitForResponse(r => r.url().includes('/update') && r.request().method() === 'POST');
    await action();
    await (await response).finished();
    await settled(page);
}
try {
    for (const width of [320, 390, 1440]) {
        const team = fixture.teams[width];
        const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block', reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.setDefaultTimeout(30000);
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', e => { if (e.type() === 'error') errors.push(e.text()); });
        await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
        await page.locator('#email').fill('alerts-manager@example.test');
        await page.locator('#password').fill(process.env.PREVENTIVE_QA_PASSWORD);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/manager/dashboard');
        await page.goto(`${origin}/manager/shifts?month=${fixture.month}&search=${encodeURIComponent(team.name)}`, { waitUntil: 'networkidle' });
        await update(page, () => page.getByRole('button', { name: 'Adicionar plantão', exact: true }).click());
        const dialog = page.getByRole('dialog');
        await dialog.waitFor();
        await page.waitForFunction(() => document.activeElement?.id === 'date');
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.evaluate(() => !!document.activeElement?.closest('.modal-overlay')), true);
        await page.locator('#date').fill(fixture.date);
        await page.locator('#start-time').fill('06:59');
        await page.locator('#end-time').fill('08:00');
        await page.locator('#end-date').fill(fixture.date);
        await update(page, () => page.locator('#sector').selectOption(String(fixture.sector)));
        await update(page, () => page.locator('#category').selectOption(String(fixture.category)));
        await page.locator('#professional').selectOption(String(team.user));
        await update(page, () => dialog.getByRole('button', { name: 'Criar plantão', exact: true }).click());
        await dialog.getByText(`Conflito com o plantão #${team.night}:`, { exact: false }).waitFor();
        await dialog.getByRole('heading', { name: 'Avisos para revisão' }).waitFor();
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/overlap-${width}.png` });
        await update(page, () => dialog.getByRole('button', { name: 'Revisar dados', exact: true }).click());
        assert.equal(await page.locator('#start-time').inputValue(), '06:59');
        await page.locator('#start-time').fill('07:00');
        await update(page, () => dialog.getByRole('button', { name: 'Criar plantão', exact: true }).click());
        await dialog.getByText(`Dobra com o plantão #${team.night}:`, { exact: false }).waitFor();
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/consecutive-${width}.png` });
        await update(page, () => dialog.getByRole('button', { name: 'Confirmar com os avisos', exact: true }).click());
        await dialog.waitFor({ state: 'detached' });
        await page.getByText('Plantão criado com sucesso.', { exact: true }).waitFor();
        // Edit the new daytime shift; its own interval must never appear as a conflict.
        const rows = page.locator(width < 768 ? '[wire\\:key^="mobile-shift-"]' : 'tbody tr').filter({ hasText: team.name }).filter({ hasText: '07:00' }).filter({ hasText: '08:00' });
        await update(page, () => rows.getByRole('button', { name: 'Editar', exact: true }).click());
        await update(page, () => dialog.getByRole('button', { name: 'Salvar alterações', exact: true }).click());
        assert.equal(await dialog.getByText('Conflito com o plantão', { exact: false }).count(), 0);
        await dialog.getByText(`Dobra com o plantão #${team.night}:`, { exact: false }).waitFor();
        await update(page, () => dialog.getByRole('button', { name: 'Revisar dados', exact: true }).click());
        await page.locator('#start-time').fill('09:00');
        await page.locator('#end-time').fill('10:00');
        await update(page, () => dialog.getByRole('button', { name: 'Salvar alterações', exact: true }).click());
        await dialog.waitFor({ state: 'detached' });
        await page.getByText('Plantão atualizado com sucesso.', { exact: true }).waitFor();
        await page.reload({ waitUntil: 'networkidle' });
        await page.goto(`${origin}/manager/shifts?focus=${team.uncovered}`, { waitUntil: 'networkidle' });
        await update(page, () => page.getByRole('button', { name: 'Preencher plantão', exact: true }).filter({ visible: true }).click());
        const reallocation = page.getByRole('alertdialog');
        await reallocation.getByText('24h', { exact: false }).first().waitFor();
        await reallocation.locator(`input[type="radio"][value="${team.user}"]`).check();
        await update(page, () => reallocation.getByRole('button', { name: 'Revisar alteração', exact: true }).click());
        await reallocation.getByRole('heading', { name: 'Avisos para revisão' }).waitFor();
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/reallocation-${width}.png` });
        await page.keyboard.press('Escape');
        await reallocation.waitFor({ state: 'detached' });
        assert.deepEqual(errors, []);
        await context.close();
        console.log(`Preventive alerts ${width}px: overlap, consecutive shifts, confirmation, editing, 24h reallocation, keyboard and accessibility passed.`);
    }
} finally {
    await browser.close();
}
