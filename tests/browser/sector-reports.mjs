// Browser plugin unavailable; use Playwright with an isolated synthetic report fixture.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.REPORT_FIXTURE && process.env.REPORT_QA_PASSWORD, 'Use disposable report data.');
const fixture = JSON.parse(readFileSync(process.env.REPORT_FIXTURE, 'utf8').replace(/^\uFEFF/, ''));
const origin = process.env.REPORT_URL ?? 'http://127.0.0.1:8792';
const out = process.env.REPORT_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function settled(page, accessible = false) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Page overflow');
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
async function login(page, email, role) {
    await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(process.env.REPORT_QA_PASSWORD);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(`**/${role}/dashboard`);
    await settled(page);
    const announcement = page.getByRole('button', { name: 'Entendi, continuar', exact: true });
    if (await announcement.isVisible()) await update(page, () => announcement.click());
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', serviceWorkers: width === 1440 ? 'allow' : 'block' });
        const page = await context.newPage();
        page.setDefaultTimeout(30000);
        page.setDefaultNavigationTimeout(30000);
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', e => { if (e.type() === 'error') errors.push(e.text()); });
        await login(page, fixture.managerEmail, 'manager');
        await page.goto(`${origin}/manager/reports?month=${fixture.month}&tab=coverage`, { waitUntil: 'networkidle' });
        const indicators = page.locator('section[aria-labelledby="sector-indicators-title"]');
        await indicators.getByText('13 setor(es)', { exact: true }).waitFor();
        const definitions = indicators.locator('summary');
        await definitions.focus();
        await page.keyboard.press('Enter');
        assert.equal(await indicators.locator('details').getAttribute('open'), '');
        await page.keyboard.press('Enter');
        assert.equal(await indicators.locator('details').getAttribute('open'), null);
        await settled(page, true);
        await update(page, () => indicators.getByRole('button', { name: 'Próxima', exact: true }).click());
        assert.equal(new URL(page.url()).searchParams.get('sectorsPage'), '2');
        await update(page, () => page.locator('#report-sector').selectOption(String(fixture.sector)));
        assert.equal(new URL(page.url()).searchParams.get('sectorsPage') ?? '1', '1');
        await update(page, () => page.locator('#report-category').selectOption(String(fixture.category)));
        await indicators.getByText('1 setor(es)', { exact: true }).waitFor();
        await indicators.getByText(/^50%/).filter({ visible: true }).waitFor();
        await settled(page, true);
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await page.locator('#report-sector').inputValue(), String(fixture.sector));
        assert.equal(await page.locator('#report-category').inputValue(), String(fixture.category));
        const downloadEvent = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Exportar setores CSV', exact: true }).click();
        const download = await downloadEvent;
        if (out) {
            const path = `${out}/sectors-${width}.csv`;
            await download.saveAs(path);
            assert.match(readFileSync(path, 'utf8'), /^"Emergência QA";2;1;50;1;1;0;1;1$/m);
            const pdfEvent = page.waitForEvent('download');
            await page.getByRole('link', { name: 'Exportar PDF', exact: true }).click();
            await (await pdfEvent).saveAs(`${out}/report-${width}.pdf`);
            await indicators.scrollIntoViewIfNeeded();
            await page.screenshot({ path: `${out}/indicators-${width}.png` });
        }
        await update(page, () => page.getByRole('button', { name: 'Limpar filtros', exact: true }).click());
        await indicators.getByText('13 setor(es)', { exact: true }).waitFor();
        await update(page, () => page.locator('#report-month').fill('2026-09'));
        await indicators.getByText(/^—/).filter({ visible: true }).first().waitFor();
        await settled(page, true);
        assert.deepEqual(errors, []);
        await context.close();

        const professional = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
        const own = await professional.newPage();
        own.setDefaultTimeout(30000);
        await login(own, fixture.professionalEmail, 'professional');
        await own.goto(`${origin}/professional/reports?month=${fixture.month}&sectorId=${fixture.otherSector}&tab=coverage`, { waitUntil: 'networkidle' });
        assert.equal(await own.getByRole('link', { name: 'Exportar setores CSV' }).count(), 0);
        assert.equal(await own.locator('#sector-indicators-title').count(), 0);
        await settled(own, true);
        assert.equal((await own.request.get(`${origin}/manager/reports/sectors/csv`)).status(), 403);
        await professional.close();
        console.log(`sector reports ${width}px: filters, pagination, refresh, exports, empty period, authorization, axe passed`);
    }
} finally { await browser.close(); }
