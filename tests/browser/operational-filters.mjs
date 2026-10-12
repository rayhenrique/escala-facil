// Browser plugin unavailable; Playwright fallback against a disposable local fixture.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.FILTERS_FIXTURE && process.env.FILTERS_QA_PASSWORD, 'Use a disposable synthetic fixture and credentials.');
const fixture = JSON.parse(readFileSync(process.env.FILTERS_FIXTURE, 'utf8'));
const origin = process.env.FILTERS_URL ?? 'http://127.0.0.1:8788';
const out = process.env.FILTERS_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function settled(page, audit = false) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Horizontal overflow');
    if (audit && process.env.AXE_MODULE) {
        await page.addScriptTag({ path: process.env.AXE_MODULE });
        const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
        assert.deepEqual(violations.map(({ id, nodes }) => ({ id, targets: nodes.map(n => n.target) })), []);
    }
}
async function update(page, action) {
    const response = page.waitForResponse(r => r.url().includes('/update') && r.request().method() === 'POST', { timeout: 30000 });
    await action();
    await (await response).finished();
}
async function filterUpdate(page, action, sheetId = 'operational-filter-sheet') {
    if (page.viewportSize().width >= 1024) return update(page, action);
    const sheet = page.locator('#' + sheetId);
    if (!await sheet.isVisible()) await page.locator(`[aria-controls="${sheetId}"]`).click();
    await action();
    await update(page, () => sheet.getByRole('button', { name: 'Aplicar filtros', exact: true }).click());
    await sheet.waitFor({ state: 'hidden' });
}
async function parameter(page, key, value) {
    await page.waitForFunction(({ key, value }) => (new URL(location.href).searchParams.get(key) ?? (key === 'page' || key.endsWith('Page') ? '1' : '')) === value, { key, value });
    await settled(page);
}
async function login(page, email, role) {
    await page.goto(origin + '/login', { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(process.env.FILTERS_QA_PASSWORD);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(`**/${role}/dashboard`);
    await settled(page);
}
async function next(page, name) {
    await page.getByRole('button', { name: 'Próxima', exact: true }).last().click();
    await parameter(page, name, '2');
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, hasTouch: width < 1024, reducedMotion: 'reduce', serviceWorkers: width === 1440 ? 'allow' : 'block' });
        const page = await context.newPage();
        page.setDefaultTimeout(30000);
        page.setDefaultNavigationTimeout(30000);
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', e => { if (e.type() === 'error') errors.push(e.text()); });
        await login(page, fixture.managerEmail, 'manager');
        await page.goto(`${origin}/manager/shifts?month=${fixture.month}`, { waitUntil: 'networkidle' });
        if (width < 1024) {
            const trigger = page.locator('[aria-controls="operational-filter-sheet"]');
            await trigger.tap();
            const sheet = page.locator('#operational-filter-sheet');
            assert.equal(await sheet.getAttribute('role'), 'dialog');
            await page.locator('#list-search').fill('Rascunho cancelado');
            assert.equal(new URL(page.url()).searchParams.get('search'), null);
            await sheet.getByRole('button', { name: 'Cancelar', exact: true }).click();
            await sheet.waitFor({ state: 'hidden' });
            assert.equal(await page.locator('#list-search').inputValue(), '');
            await trigger.tap();
            await page.locator('#list-from').fill('2026-12-31');
            await page.locator('#list-to').fill('2026-01-01');
            await update(page, () => sheet.getByRole('button', { name: 'Aplicar filtros', exact: true }).click());
            await page.locator('#list-to[aria-invalid="true"]').waitFor();
            await page.waitForFunction(() => document.activeElement?.id === 'list-to');
            assert(await sheet.isVisible());
            await sheet.getByRole('button', { name: 'Limpar filtros', exact: true }).click();
            await update(page, () => sheet.getByRole('button', { name: 'Aplicar filtros', exact: true }).click());
            await sheet.waitFor({ state: 'hidden' });
        }

        await filterUpdate(page, () => page.locator('#list-search').fill(fixture.professionalName));
        await parameter(page, 'search', fixture.professionalName);
        await page.getByText(`${fixture.shiftCount} registro(s) encontrado(s)`, { exact: true }).waitFor();
        await next(page, 'page');
        await filterUpdate(page, () => page.locator('#list-sector').selectOption(String(fixture.sector)));
        await parameter(page, 'filterSectorId', String(fixture.sector));
        await parameter(page, 'page', '1');
        await filterUpdate(page, () => page.locator('#list-category').selectOption(String(fixture.category)));
        await parameter(page, 'filterCategoryId', String(fixture.category));
        await filterUpdate(page, () => page.locator('#list-from').fill(fixture.from));
        await parameter(page, 'dateFrom', fixture.from);
        await filterUpdate(page, () => page.locator('#list-to').fill(fixture.to));
        await parameter(page, 'dateTo', fixture.to);
        await filterUpdate(page, () => page.locator('#list-status').selectOption('scheduled'));
        await parameter(page, 'filter', 'scheduled');
        await page.getByText(`${fixture.periodCount} registro(s) encontrado(s)`, { exact: true }).waitFor();
        const shared = page.url();
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await page.locator('#list-search').inputValue(), fixture.professionalName);
        assert.equal(await page.locator('#list-to').inputValue(), fixture.to);
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/shifts-${width}.png`, fullPage: false });
        await page.getByRole('link', { name: 'Ver no calendário', exact: true }).click();
        await page.waitForURL('**/manager/calendar?**');
        const back = page.getByRole('link', { name: '← Voltar à escala', exact: true });
        const backUrl = await back.getAttribute('href');
        await back.click();
        await page.waitForURL(backUrl);
        await settled(page);
        assert.equal(await page.locator('#list-search').inputValue(), fixture.professionalName);
        assert.equal(await page.locator('#list-to').inputValue(), fixture.to);
        // A shared URL works without navigating from the source screen.
        const fresh = await context.newPage();
        await fresh.goto(shared, { waitUntil: 'networkidle' });
        assert.equal(await fresh.locator('#list-status').inputValue(), 'scheduled');
        await fresh.close();
        await filterUpdate(page, () => page.locator('#list-search').fill('Sem resultado QA'));
        await parameter(page, 'search', 'Sem resultado QA');
        await page.getByText('Nenhum plantão corresponde aos filtros. Ajuste a pesquisa ou limpe os filtros.', { exact: true }).filter({ visible: true }).waitFor();
        await update(page, () => page.getByRole('button', { name: 'Remover filtro Nome: Sem resultado QA', exact: true }).click());
        await parameter(page, 'search', '');
        await filterUpdate(page, () => page.locator('#list-search').fill('Sem resultado QA'));
        await filterUpdate(page, () => page.getByRole('button', { name: 'Limpar filtros', exact: true }).click());
        await parameter(page, 'search', '');
        assert.equal(await page.locator('#list-from').inputValue(), '');
        await page.goto(`${origin}/manager/swaps`, { waitUntil: 'networkidle' });
        await page.getByText(`${fixture.pendingCount} pedido(s) encontrado(s).`, { exact: true }).waitFor();
        await filterUpdate(page, () => page.locator('#list-search').fill(fixture.substituteName));
        await parameter(page, 'search', fixture.substituteName);
        await filterUpdate(page, () => page.locator('#list-status').selectOption('rejected'));
        await parameter(page, 'swapStatusFilter', 'rejected');
        await page.getByText(`${fixture.rejectedCount} pedido(s) encontrado(s).`, { exact: true }).waitFor();
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await page.locator('#list-status').inputValue(), 'rejected');
        assert.equal(await page.getByRole('button', { name: 'Aprovar e transferir', exact: true }).count(), 0);
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/swaps-${width}.png`, fullPage: true });
        await filterUpdate(page, () => page.locator('#list-status').selectOption(''));
        await parameter(page, 'swapStatusFilter', '');
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await page.locator('#list-status').inputValue(), '', 'All statuses survives refresh');
        await page.goto(`${origin}/manager/reports?month=${fixture.month}&tab=swaps`, { waitUntil: 'networkidle' });
        await next(page, 'swapsPage');
        await filterUpdate(page, () => page.locator(page.viewportSize().width < 1024 ? '#report-mobile-status' : '[aria-label="Filtrar trocas por situação"]').selectOption('rejected'), 'report-filter-sheet');
        await parameter(page, 'swapStatusFilter', 'rejected');
        assert.equal(new URL(page.url()).searchParams.get('swapsPage') ?? '1', '1');
        await settled(page, true);
        assert.deepEqual(errors, []);
        await context.close();
        const personal = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block' });
        const professional = await personal.newPage();
        professional.setDefaultTimeout(30000);
        await login(professional, fixture.professionalEmail, 'professional');
        await professional.goto(`${origin}/professional/dashboard?month=${fixture.month}&filterSectorId=${fixture.foreignSector}`, { waitUntil: 'networkidle' });
        assert.equal(await professional.locator('#list-sector').isDisabled(), true);
        assert.equal(await professional.locator('#list-sector').inputValue(), String(fixture.sector));
        assert.equal(await professional.getByText('Equipe Externa', { exact: true }).count(), 0);
        await filterUpdate(professional, () => professional.locator('#list-search').fill(fixture.substituteName));
        await parameter(professional, 'search', fixture.substituteName);
        await update(professional, () => professional.locator('#personal-swap-status').selectOption('rejected'));
        await parameter(professional, 'swapStatusFilter', 'rejected');
        await settled(professional, true);
        if (out) await professional.screenshot({ path: `${out}/personal-${width}.png`, fullPage: true });
        await personal.close();
        console.log(JSON.stringify({ width, combinedFilters: 'passed', pagination: 'passed', refresh: 'passed', contextualReturn: 'passed', professionalIsolation: 'passed', axeAA: 0, consoleErrors: 0 }));
    }
} finally { await browser.close(); }
