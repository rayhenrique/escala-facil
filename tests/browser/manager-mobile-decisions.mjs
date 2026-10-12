// Browser plugin unavailable: Playwright fallback with disposable, synthetic fixtures only.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { pathToFileURL } from 'node:url';
assert.ok(process.env.MANAGER_MOBILE_FIXTURE && process.env.MANAGER_MOBILE_PASSWORD);
const fixture = JSON.parse(fs.readFileSync(process.env.MANAGER_MOBILE_FIXTURE, 'utf8'));
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = process.env.MANAGER_MOBILE_URL ?? 'http://127.0.0.1:8797';
const out = process.env.MANAGER_MOBILE_SCREENSHOTS;
if (out) fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
const report = [];
async function audit(page, dialog) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    const geometry = await dialog.evaluate(el => {
        const body = el.querySelector('.modal-body');
        const footer = el.querySelector('.modal-footer');
        body.scrollTop = body.scrollHeight;
        const rect = footer.getBoundingClientRect();
        return { top: rect.top, bottom: rect.bottom, height: innerHeight, scrollable: getComputedStyle(body).overflowY };
    });
    assert(geometry.top >= 0 && geometry.bottom <= geometry.height + 1);
    assert.equal(geometry.scrollable, 'auto');
    await page.waitForFunction(() => !!document.activeElement?.closest('.modal-dialog'));
    for (let i = 0; i < 8; i++) {
        await page.keyboard.press('Tab');
        assert(await page.evaluate(() => !!document.activeElement?.closest('.modal-dialog')));
    }
    if (process.env.AXE_MODULE) {
        await page.addScriptTag({ path: process.env.AXE_MODULE });
        const violations = await page.evaluate(async () => (await axe.run(document, {
            runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] },
        })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target) })));
        assert.deepEqual(violations, []);
    }
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 800 }, reducedMotion: 'reduce', serviceWorkers: width === 1440 ? 'allow' : 'block' });
        const page = await context.newPage();
        page.setDefaultTimeout(15000);
        page.setDefaultNavigationTimeout(30000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(origin + '/login', { waitUntil: 'networkidle' });
        await page.getByLabel('E-mail', { exact: true }).fill(fixture.email);
        await page.getByLabel('Senha', { exact: true }).fill(process.env.MANAGER_MOBILE_PASSWORD);
        await page.getByRole('button', { name: 'Entrar', exact: true }).click();
        await page.waitForURL('**/manager/dashboard');
        for (const [mode, item] of Object.entries(fixture.cases[width])) {
            console.log(`Checking ${width} ${mode}`);
            await page.goto(`${origin}/manager/swaps?request=${item.id}`, { waitUntil: 'networkidle' });
            const reverting = ['revert', 'blocked'].includes(mode);
            await page.getByRole('button', { name: reverting ? 'Desfazer troca' : `Analisar pedido #${item.id}`, exact: true }).click();
            const dialog = page.locator(reverting ? '#revert-swap' : '#decide-swap');
            await dialog.waitFor();
            await page.waitForFunction(() => !!document.activeElement?.closest('.modal-dialog'));
            await audit(page, dialog);
            assert.match(await dialog.innerText(), new RegExp(reverting ? 'Aprovada' : 'Aguardando gerência'));
            if (mode === 'blocked') {
                assert.equal(await dialog.getByRole('button', { name: 'Confirmar desfazer' }).count(), 0);
                await dialog.getByRole('button', { name: 'Analisar correção administrativa' }).click();
                await dialog.getByText('Conferência administrativa separada', { exact: true }).waitFor();
                await audit(page, dialog);
                await dialog.getByRole('button', { name: 'Manter troca' }).click();
            } else if (mode === 'revert') {
                assert.equal(await dialog.locator('dl').count(), 3); // participants + two assignments
                await dialog.getByRole('button', { name: 'Confirmar desfazer' }).click();
                await dialog.locator('#reversion-reason-error').waitFor();
                await dialog.getByLabel('Justificativa administrativa').fill('Correção administrativa em teste.');
                await page.keyboard.press('Escape');
                await dialog.getByRole('button', { name: 'Continuar editando' }).waitFor();
                await dialog.getByRole('button', { name: 'Continuar editando' }).click();
                await dialog.getByRole('button', { name: 'Atualizar revisão' }).click();
                assert.equal(await dialog.getByLabel('Justificativa administrativa').inputValue(), 'Correção administrativa em teste.');
                await dialog.getByRole('button', { name: 'Confirmar desfazer' }).click();
            } else if (mode === 'reject') {
                await dialog.getByRole('button', { name: 'Rejeitar…', exact: true }).click();
                await dialog.getByRole('button', { name: 'Confirmar rejeição' }).click();
                await dialog.locator('#rejection-reason-error').waitFor();
                await dialog.locator('#rejection-reason').fill('Manter a cobertura original.');
                await page.keyboard.press('Escape');
                await dialog.getByRole('button', { name: 'Continuar editando' }).click();
                await dialog.getByRole('button', { name: 'Voltar à análise' }).click();
                await dialog.getByRole('button', { name: 'Rejeitar…', exact: true }).click();
                assert.equal(await dialog.locator('#rejection-reason').inputValue(), 'Manter a cobertura original.');
                await audit(page, dialog);
                await dialog.getByRole('button', { name: 'Confirmar rejeição' }).click();
            } else {
                await dialog.getByRole('button', { name: 'Aprovar…', exact: true }).click();
                await dialog.locator('[data-approval-preview]').waitFor();
                assert.equal(await dialog.locator('[data-approval-preview] p').count(), mode === 'mutual' ? 3 : 2);
                if (width === 390) await page.setViewportSize({ width, height: 500 });
                await audit(page, dialog);
                if (out) await page.screenshot({ path: `${out}/${mode}-${width}.png` });
                // Same user double click is protected; the backend remains authoritative.
                await dialog.getByRole('button', { name: 'Confirmar aprovação' }).evaluate(el => { el.click(); el.click(); });
            }
            await dialog.waitFor({ state: 'detached' });
            if (width === 390) await page.setViewportSize({ width, height: 800 });
            if (mode !== 'blocked') {
                const expected = mode === 'reject' ? 'Rejeitada' : mode === 'revert' ? 'Desfeita' : 'Aprovada';
                await page.locator(`[data-swap-record="${item.id}"] .status-badge`).filter({ hasText: expected }).first().waitFor();
            }
            report.push({ width, mode, passed: true });
            console.log(`Passed ${width} ${mode}`);
            if (out) fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 2));
        }
        assert.deepEqual(errors, []);
        await context.close();
    }
} finally {
    await browser.close();
    if (out) fs.writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 2));
}
console.log(JSON.stringify(report));
