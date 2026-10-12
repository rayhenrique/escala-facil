// Browser plugin unavailable; Playwright against an explicitly supplied synthetic database.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert(process.env.FILTERS_FIXTURE && process.env.FILTERS_QA_PASSWORD);
const fixture = JSON.parse(readFileSync(process.env.FILTERS_FIXTURE, 'utf8'));
const origin = process.env.FILTERS_URL ?? 'http://127.0.0.1:8798';
const out = process.env.FILTERS_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
const report = { checks: [], errors: [] };
async function update(page, action) {
    const response = page.waitForResponse(r => r.url().includes('/update') && r.request().method() === 'POST');
    await action(); await (await response).finished();
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, hasTouch: width < 1024, reducedMotion: 'reduce', serviceWorkers: width === 1440 ? 'allow' : 'block' });
        const page = await context.newPage(); page.setDefaultTimeout(30000);
        page.on('pageerror', e => report.errors.push(e.message));
        page.on('console', e => { if (e.type() === 'error') report.errors.push(e.text()); });
        await page.goto(origin + '/login'); await page.locator('#email').fill(fixture.managerEmail);
        await page.locator('#password').fill(process.env.FILTERS_QA_PASSWORD); await page.getByRole('button', { name: 'Entrar', exact: true }).click();
        await page.waitForURL('**/manager/dashboard'); await page.waitForLoadState('networkidle');
        const releaseNotice = page.getByRole('button', { name: 'Entendi, continuar', exact: true });
        if (await releaseNotice.isVisible()) await releaseNotice.click();
        for (const [route, triggerName, fieldId, submitName] of [
            ['sectors', 'Adicionar setor', 'sector-name', 'Criar setor'],
            ['categories', 'Adicionar categoria', 'category-name', 'Criar categoria'],
            ['users', 'Adicionar usuário', 'user-name', 'Criar usuário'],
            ['shifts', 'Adicionar plantão', 'date', 'Criar plantão'],
        ]) {
            await page.goto(`${origin}/manager/${route}`); await page.waitForLoadState('networkidle');
            const trigger = page.getByRole('button', { name: triggerName, exact: true });
            await (width < 1024 ? trigger.tap() : trigger.click());
            const dialog = page.getByRole('dialog', { name: triggerName, exact: true }); await dialog.waitFor();
            const field = page.locator('#' + fieldId);
            assert.equal(await field.evaluate(el => getComputedStyle(el).fontSize), '16px');
            await update(page, () => dialog.getByRole('button', { name: submitName, exact: true }).click());
            await dialog.locator('[aria-invalid="true"]').first().waitFor();
            await page.waitForFunction(() => document.activeElement?.getAttribute('aria-invalid') === 'true');
            await page.keyboard.press('Tab');
            assert(await dialog.evaluate(el => el.contains(document.activeElement)), 'Focus remains in the form');
            if (route === 'shifts') {
                await update(page, () => page.locator('#sector').selectOption(String(fixture.sector)));
                await update(page, () => page.locator('#category').selectOption(String(fixture.category)));
                await update(page, () => page.locator('#professional-search').fill(fixture.professionalName));
                assert(await page.locator('#professional option').allTextContents().then(names => names.includes(fixture.professionalName)));
                await page.locator('#professional').selectOption({ label: fixture.professionalName });
                await page.locator('#professional').focus(); await page.keyboard.press('ArrowDown'); await page.keyboard.press('Tab');
            } else await field.fill('Rascunho sem persistir');
            await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
            await dialog.getByRole('button', { name: 'Continuar editando', exact: true }).waitFor();
            await dialog.getByRole('button', { name: 'Continuar editando', exact: true }).click();
            assert(await dialog.isVisible());
            if (width < 1024) {
                await page.setViewportSize({ width, height: 480 });
                const footer = await dialog.locator('.modal-footer').first().boundingBox();
                assert(footer && footer.y >= 0 && footer.y + footer.height <= 481, 'Actions stay within the short viewport');
                if (out) await page.screenshot({ path: `${out}/${route}-sheet-${width}.png`, fullPage: false });
                await page.setViewportSize({ width, height: 900 });
            }
            if (process.env.AXE_MODULE) {
                await page.addScriptTag({ path: process.env.AXE_MODULE });
                const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target) })));
                assert.deepEqual(violations, []);
            }
            await page.keyboard.press('Escape');
            await dialog.getByRole('button', { name: 'Descartar alterações', exact: true }).click();
            await dialog.waitFor({ state: 'hidden' });
            await page.waitForFunction(name => document.activeElement?.textContent.trim() === name, triggerName);
            if (route !== 'shifts') {
                const searchId = { users: 'user-search', sectors: 'sector-search', categories: 'category-search' }[route];
                await update(page, () => page.locator('#' + searchId).fill('Sem resultado QA'));
                const chip = page.getByRole('button', { name: 'Remover filtro Pesquisa: Sem resultado QA', exact: true });
                await chip.focus(); await update(page, () => page.keyboard.press('Space'));
                assert.equal(await page.locator('#' + searchId).inputValue(), '');
                if (route === 'users') {
                    await update(page, () => page.locator('#role-filter').selectOption('professional'));
                    await page.getByRole('button', { name: 'Remover filtro Perfil: Profissional', exact: true }).waitFor();
                    await update(page, () => page.getByRole('button', { name: 'Limpar filtros', exact: true }).click());
                    assert.equal(await page.locator('#role-filter').inputValue(), '');
                }
            }
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            report.checks.push({ width, route, validationFocusKeyboardTouchDiscard: 'passed' });
        }
        await context.close();
    }
    assert.deepEqual(report.errors, []); console.log(JSON.stringify(report));
} finally { await browser.close(); }
