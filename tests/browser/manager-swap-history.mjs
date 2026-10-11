// Browser plugin unavailable; run Playwright only against a disposable synthetic database.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.SWAP_HISTORY_FIXTURE && process.env.SWAP_HISTORY_QA_PASSWORD);
const fixture = JSON.parse(readFileSync(process.env.SWAP_HISTORY_FIXTURE, 'utf8').replace(/^\uFEFF/, ''));
const origin = process.env.SWAP_HISTORY_URL ?? 'http://127.0.0.1:8795';
const out = process.env.SWAP_HISTORY_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function settled(page, audit = false) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    if (audit && process.env.AXE_MODULE) {
        await page.addScriptTag({ path: process.env.AXE_MODULE });
        const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
        assert.deepEqual(violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target) })), []);
    }
}
async function update(page, action) {
    const response = page.waitForResponse(r => r.url().includes('/update') && r.request().method() === 'POST');
    await action(); await (await response).finished(); await settled(page);
}
async function count(page, total) {
    await page.getByText(`${total} pedido(s) encontrado(s).`, { exact: true }).waitFor();
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', serviceWorkers: width === 1440 ? 'allow' : 'block' });
        const page = await context.newPage(); page.setDefaultTimeout(60000); page.setDefaultNavigationTimeout(60000);
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', m => { if (/multiple instances|Não foi possível iniciar/.test(m.text())) errors.push(m.text()); });
        await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
        await page.locator('#email').fill(fixture.managerEmail);
        await page.locator('#password').fill(process.env.SWAP_HISTORY_QA_PASSWORD);
        await page.locator('button[type="submit"]').click(); await page.waitForURL('**/manager/dashboard'); await settled(page);
        const announcement = page.getByRole('button', { name: 'Entendi, continuar', exact: true });
        if (await announcement.isVisible()) await update(page, () => announcement.click());
        await page.goto(`${origin}/manager/swaps`, { waitUntil: 'networkidle' });
        await update(page, () => page.locator('#list-search').fill(fixture.search)); await count(page, fixture.pending);
        await update(page, async () => {
            await page.getByRole('button', { name: 'Histórico de trocas', exact: true }).focus();
            await page.keyboard.press('Enter');
        });
        await count(page, fixture.count);
        assert.equal(await page.locator('[data-swap-record]').count(), 10);
        const firstIds = await page.locator('[data-swap-record]').evaluateAll(nodes => nodes.map(n => n.dataset.swapRecord));
        for (const number of [2, 3]) {
            await update(page, () => page.getByRole('button', { name: 'Próxima', exact: true }).filter({ visible: true }).click());
            assert.equal(new URL(page.url()).searchParams.get('page'), String(number));
        }
        assert.equal(await page.locator('[data-swap-record]').count(), 5);
        assert.equal(await page.locator('[data-swap-record]').evaluateAll((nodes, ids) => nodes.every(n => !ids.includes(n.dataset.swapRecord)), firstIds), true);
        const third = page.url();
        await page.reload({ waitUntil: 'networkidle' }); await count(page, fixture.count);
        assert.equal(page.url(), third); assert.equal(await page.locator('[data-swap-record]').count(), 5);
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/history-old-${width}.png`, fullPage: true });
        await update(page, () => page.locator('#list-requester').fill('Solicitante'));
        assert.equal(new URL(page.url()).searchParams.get('page') ?? '1', '1');
        await update(page, () => page.locator('#list-substitute').fill('Substituto'));
        await update(page, () => page.locator('#list-sector').selectOption(String(fixture.sector)));
        await update(page, () => page.locator('#list-category').selectOption(String(fixture.category)));
        await update(page, () => page.locator('#list-from').fill('2026-10-14'));
        await update(page, () => page.locator('#list-to').fill('2026-10-14'));
        await update(page, () => page.locator('#list-status').selectOption('approved'));
        await update(page, () => page.locator('#list-sort').selectOption('oldest'));
        await count(page, fixture.futureApproved); await settled(page, true);
        if (out) { await page.locator('[data-swap-record]').first().scrollIntoViewIfNeeded(); await page.screenshot({ path: `${out}/filtered-${width}.png` }); }
        const filtered = page.url();
        await page.reload({ waitUntil: 'networkidle' }); await count(page, fixture.futureApproved);
        assert.equal(await page.locator('#list-requester').inputValue(), 'Solicitante');
        assert.equal(await page.locator('#list-sort').inputValue(), 'oldest');
        const card = page.locator(`[data-swap-record="${fixture.mutual}"]`);
        await card.waitFor(); assert.match(await card.innerText(), /24h/); assert.match(await card.innerText(), /dia seguinte, 01\/11\/2026/);
        await update(page, () => card.getByRole('button', { name: 'Desfazer troca', exact: true }).click());
        const dialog = page.getByRole('alertdialog'); await dialog.waitFor();
        assert.match(await dialog.innerText(), /Situação atual → Situação após desfazer/);
        assert.equal(await dialog.locator('dl').count(), 2);
        await update(page, () => dialog.getByRole('button', { name: 'Manter troca', exact: true }).click());
        await dialog.waitFor({ state: 'detached' });
        const details = card.getByRole('link', { name: `Detalhes do pedido #${fixture.mutual}`, exact: true });
        const href = await details.getAttribute('href'); await details.click(); await page.waitForURL(href); await settled(page);
        assert.equal(await page.locator('[data-swap-record]').count(), 1);
        await page.getByText('Consultar comprovante da situação atual', { exact: true }).focus();
        await page.keyboard.press('Enter');
        await page.getByRole('article', { name: /Comprovante TROCA-/ }).waitFor({ state: 'visible' });
        await settled(page, true);
        const pdf = await context.request.get(await page.getByRole('link', { name: `Comprovante do pedido #${fixture.mutual} em PDF` }).getAttribute('href'));
        assert.equal(pdf.status(), 200); assert.equal(pdf.headers()['content-type'], 'application/pdf');
        if (out) await page.screenshot({ path: `${out}/mutual-details-${width}.png`, fullPage: true });
        const back = page.getByRole('link', { name: '← Voltar às trocas', exact: true });
        const backHref = await back.getAttribute('href');
        await back.click(); await page.waitForURL(backHref); await settled(page); await count(page, fixture.futureApproved);
        for (const [key, value] of new URL(filtered).searchParams) assert.equal(new URL(page.url()).searchParams.get(key), value);
        await update(page, () => page.locator('#list-requester').fill('Sem resultado QA')); await count(page, 0);
        await page.getByText('Nenhuma troca corresponde aos filtros.', { exact: true }).waitFor();
        await update(page, () => page.getByRole('button', { name: 'Limpar filtros', exact: true }).click());
        await count(page, fixture.total);
        assert.equal(await page.getByRole('button', { name: 'Histórico de trocas', exact: true }).getAttribute('aria-pressed'), 'true');
        await update(page, () => page.getByRole('button', { name: 'Aguardando decisão', exact: true }).click());
        assert.equal(await page.locator('#list-status').inputValue(), 'pending_manager');
        await settled(page, true); assert.deepEqual(errors, []); await context.close();
        console.log(`history ${width}px: all pages, combined filters, refresh, mutual periods, revert preview/cancel, details/PDF, contextual return, empty state, keyboard and axe passed`);
    }
} finally { await browser.close(); }
