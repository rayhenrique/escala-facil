// Browser plugin unavailable: Playwright fallback. Only a disposable synthetic database.
import assert from 'node:assert/strict';
import { mkdirSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = process.env.DASHBOARD_URL ?? 'http://127.0.0.1:8767';
assert.ok(process.env.DASHBOARD_QA_EMAIL && process.env.DASHBOARD_QA_PASSWORD && process.env.REALLOCATION_FIXTURE, 'Provide synthetic manager credentials and a fixture JSON with night/day24/date24/professionalName.');
const fixture = JSON.parse(readFileSync(process.env.REALLOCATION_FIXTURE, 'utf8'));
const out = process.env.DASHBOARD_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function settled(page) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
}
async function login(page) {
    await page.goto(origin + '/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(process.env.DASHBOARD_QA_EMAIL);
    await page.locator('#password').fill(process.env.DASHBOARD_QA_PASSWORD);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL('**/manager/dashboard', { timeout: 60000 }); await settled(page);
    const announcement = page.getByRole('button', { name: 'Entendi, continuar' });
    if (await announcement.isVisible()) await announcement.click();
}
async function audit(page) {
    await settled(page);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    if (!process.env.AXE_MODULE) return;
    await page.addScriptTag({ path: process.env.AXE_MODULE });
    const sheet = page.locator('#reallocate-shift');
    if (await sheet.isVisible()) {
        const box = await sheet.locator('.modal-footer:visible').boundingBox();
        assert(box.y >= 0 && box.y + box.height <= page.viewportSize().height + 1);
    }
    const result = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a','wcag2aa','wcag21aa','best-practice'] } })).violations);
    if (result.length) console.error(JSON.stringify(result.map(({ id, nodes }) => ({ id, nodes: nodes.map(({ html, failureSummary }) => ({ html, failureSummary })) }))));
    assert.deepEqual(result.map(({ id }) => id), []);
}
async function openFromDashboard(page) {
    const trigger = page.getByRole('button', { name: `Preencher plantão #${fixture.night}`, exact: true });
    for (let i = 0; i < 30 && !await trigger.count(); i++) {
        const next = page.getByRole('navigation', { name: 'Paginação das pendências', exact: true }).getByRole('button', { name: 'Próxima', exact: true });
        assert(await next.isEnabled(), 'Fixture must be in the operational queue');
        await next.click(); await settled(page);
    }
    await trigger.click();
}
async function select(page) {
    await page.getByRole('radio', { name: new RegExp(fixture.professionalName ?? 'Carlos Lima') }).check();
    await page.getByRole('button', { name: 'Revisar alteração', exact: true }).click();
    await page.getByRole('button', { name: 'Confirmar realocação', exact: true }).waitFor();
}
try {
    for (const width of [320,390,1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block', reducedMotion: 'reduce' });
        const page = await context.newPage(); page.setDefaultTimeout(15000); const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await login(page);
        await openFromDashboard(page); const dialog = page.locator('#reallocate-shift'); await dialog.waitFor();
        await audit(page);
        await page.waitForFunction(() => document.activeElement?.matches('[data-confirm-cancel]'));
        assert.ok(await dialog.getByRole('radio').count());
        await dialog.getByLabel('Pesquisar profissional').fill('nome inexistente');
        await dialog.getByText('Nenhum profissional corresponde à pesquisa.', { exact: true }).waitFor();
        await dialog.getByRole('button', { name: 'Limpar pesquisa', exact: true }).click();
        await dialog.getByRole('radio', { name: new RegExp(fixture.professionalName) }).waitFor();
        assert.equal(await dialog.getByRole('radio', { name: /Bruna QA ocupada/ }).count(), 0);
        await dialog.locator('[data-confirm-cancel]').focus();
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.evaluate(() => !!document.activeElement?.closest('#reallocate-shift')), true);
        await page.keyboard.press('Tab');
        await dialog.getByRole('button', { name: 'Revisar alteração' }).click();
        await dialog.locator('#reallocation-user-error').waitFor();
        await audit(page);
        if (out) await page.screenshot({ path: `${out}/selection-${width}.png` });
        if (width === 390) await page.setViewportSize({ width, height: 480 });
        await select(page); await audit(page);
        if (out) await page.screenshot({ path: `${out}/preview-${width}.png` });
        await page.keyboard.press('Escape'); await dialog.getByRole('button', { name: 'Descartar alterações' }).click(); await dialog.waitFor({ state: 'detached' });
        await page.waitForFunction(() => document.activeElement?.matches('button[wire\\:click^="openReallocation"]'));
        await page.setViewportSize({ width, height: 900 });
        if (fixture.empty) {
            await page.goto(origin + `/manager/calendar?month=${fixture.dateEmpty.slice(0,7)}&selectedDate=${fixture.dateEmpty}&sectorId=${fixture.emptySector}`, { waitUntil: 'networkidle' });
            if (width < 1024) {
                await page.getByRole('button', { name: `Ver detalhes do plantão #${fixture.empty}`, exact: true }).click();
                await page.locator('#calendar-shift-detail').getByRole('button', { name: 'Preencher plantão', exact: true }).click();
            } else {
                await page.getByRole('button', { name: `Preencher plantão #${fixture.empty}`, exact: true }).click();
            }
            await dialog.waitFor();
            await dialog.getByText('Nenhum profissional disponível para este plantão.', { exact: true }).waitFor();
            assert(await dialog.getByRole('button', { name: 'Revisar alteração', exact: true }).isDisabled());
            await audit(page);
            // Model an overlay keyboard changing only the visual viewport, not layout height.
            await page.evaluate(() => {
                Object.defineProperty(visualViewport, 'height', { configurable: true, get: () => 380 });
                visualViewport.dispatchEvent(new Event('resize'));
            });
            const footer = await dialog.locator('.modal-footer:visible').boundingBox();
            assert(footer.y + footer.height <= 381);
            await page.evaluate(() => { delete visualViewport.height; visualViewport.dispatchEvent(new Event('resize')); });
            await dialog.getByRole('button', { name: 'Fechar', exact: true }).click();
            await dialog.waitFor({ state: 'detached' });
            await page.waitForFunction(() => document.activeElement?.matches('[data-decision-return], button[wire\\:click^="openReallocation"]'));
        }
        assert.deepEqual(errors, []); await context.close();
    }
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'allow' });
    const page = await context.newPage(); page.setDefaultTimeout(15000); await login(page);
    const other = await context.newPage(); other.setDefaultTimeout(15000); await other.goto(origin + '/manager/dashboard', { waitUntil: 'networkidle' });
    for (const target of [page,other]) {
        await openFromDashboard(target); await select(target);
    }
    let release; let signal;
    const started = new Promise((resolve) => signal = resolve);
    await page.route('**/livewire-*/update', async (route) => {
        if (!route.request().postData()?.includes('confirmReallocation')) return route.continue();
        signal(); await new Promise((resolve) => release = resolve); await route.continue();
    });
    await page.getByRole('button', { name: 'Confirmar realocação', exact: true }).evaluate((button) => { button.click(); button.click(); });
    await started;
    await page.waitForFunction(() => document.querySelector('[data-confirm-cancel]')?.disabled);
    await page.keyboard.press('Escape'); assert.equal(await page.getByRole('alertdialog').isVisible(), true);
    release(); await page.getByRole('alertdialog').waitFor({ state: 'detached' }); await page.unroute('**/livewire-*/update');
    assert.equal(await page.locator(`[data-attention="uncovered-${fixture.night}"]`).count(), 0);
    await page.waitForFunction(() => document.activeElement?.id === 'attention-title');
    await other.getByRole('button', { name: 'Confirmar realocação', exact: true }).click();
    await other.getByRole('alertdialog').getByRole('alert').filter({ hasText: /não está mais disponível|mudou/ }).waitFor();
    await audit(other);
    if (out) await other.screenshot({ path: `${out}/stale-reallocation.png` });
    await page.getByRole('link', { name: 'Abrir calendário', exact: true }).click();
    await page.waitForURL('**/manager/calendar');
    await page.goto(origin + `/manager/calendar?month=${fixture.date24.slice(0,7)}&selectedDate=${fixture.date24}`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: `Preencher plantão #${fixture.day24}`, exact: true }).click();
    await select(page);
    assert.match(await page.getByRole('alertdialog').innerText(), /24h/);
    assert.match(await page.getByRole('alertdialog').innerText(), /Dobra/);
    await audit(page);
    if (out) await page.screenshot({ path: `${out}/calendar-24h-preview.png` });
    await page.getByRole('button', { name: 'Confirmar realocação', exact: true }).click();
    await page.getByRole('alertdialog').waitFor({ state: 'detached' });
    const assigned = page.locator(`[wire\\:key="calendar-shift-${fixture.day24}"]`);
    assert.match(await assigned.innerText(), new RegExp(fixture.professionalName ?? 'Carlos Lima'));
    assert.match(await assigned.innerText(), /Agendado/);
    assert.equal(await assigned.getByRole('button', { name: /Preencher plantão/ }).count(), 0);
    await page.reload({ waitUntil: 'networkidle' });
    assert.match(await assigned.innerText(), /Agendado/);
    await context.close();
    console.log('Reallocation: 320/390/1440, AA, reduced viewport, focus trap/return, preview, slow request/Escape, duplicate click, competing reallocation, PWA, calendar/24h and refresh passed.');
} finally { await browser.close(); }
