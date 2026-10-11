// Browser plugin unavailable; Playwright uses only a disposable synthetic database.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.RUNTIME_QA_EMAIL && process.env.RUNTIME_QA_PASSWORD && process.env.RUNTIME_QA_SWAP);
const origin = process.env.RUNTIME_URL ?? 'http://127.0.0.1:8794';
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const asset = manifest['resources/js/app.js'].file;
const code = readFileSync(`public/build/${asset}`, 'utf8');
const out = process.env.RUNTIME_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
async function settled(page) {
    await page.waitForLoadState('networkidle');
    await page.locator('#nprogress').waitFor({ state: 'detached' });
    await page.waitForFunction(() => window.Livewire && window.Alpine);
}
async function update(page, action) {
    const pending = page.waitForResponse(r => r.url().includes('/update') && r.request().method() === 'POST');
    await action(); await (await pending).finished(); await settled(page);
}
async function navigate(page, name, suffix) {
    const menu = page.getByRole('button', { name: 'Abrir menu principal', exact: true });
    if (await menu.isVisible()) await menu.click();
    await page.getByRole('link', { name, exact: true }).filter({ visible: true }).click();
    await page.waitForURL(`${origin}${suffix}`); await settled(page);
}
async function modalChecks(page, width) {
    await update(page, () => page.getByRole('button', { name: 'Adicionar plantão', exact: true }).click());
    const overlay = page.locator('.modal-overlay');
    await overlay.waitFor();
    await update(page, () => overlay.getByRole('button', { name: 'Criar plantão', exact: true }).click());
    assert.ok(await overlay.locator('[aria-invalid="true"]').count() > 0);
    await update(page, () => overlay.getByRole('button', { name: 'Cancelar', exact: true }).click());
    await overlay.waitFor({ state: 'detached' });
    await update(page, () => page.getByRole('button', { name: 'Adicionar plantão', exact: true }).click());
    await page.keyboard.press('Escape'); await overlay.waitFor({ state: 'detached' });
    await update(page, () => page.getByRole('button', { name: 'Editar', exact: true }).filter({ visible: true }).first().click());
    await overlay.getByRole('heading', { name: 'Editar plantão', exact: true }).waitFor();
    await update(page, () => overlay.getByRole('button', { name: 'Cancelar', exact: true }).click());
    await overlay.waitFor({ state: 'detached' });
    await navigate(page, 'Trocas', '/manager/swaps');
    // Shared context URL, then wire:navigate is exercised again on the return.
    await page.goto(`${origin}/manager/swaps?request=${process.env.RUNTIME_QA_SWAP}`, { waitUntil: 'networkidle' });
    await update(page, () => page.getByRole('button', { name: 'Aprovar e transferir', exact: true }).click());
    const dialog = page.getByRole('alertdialog'); await dialog.waitFor();
    await update(page, () => dialog.getByRole('button', { name: 'Voltar à análise', exact: true }).click());
    await update(page, () => dialog.getByRole('button', { name: 'Rejeitar…', exact: true }).click());
    await update(page, () => dialog.getByRole('button', { name: 'Confirmar rejeição', exact: true }).click());
    await dialog.locator('#rejection-reason-error').waitFor();
    assert.equal(await dialog.locator('#rejection-reason').evaluate(n => n === document.activeElement), true);
    if (process.env.AXE_MODULE) {
        await page.addScriptTag({ path: process.env.AXE_MODULE });
        const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
        assert.deepEqual(violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target) })), []);
    }
    if (out) await page.screenshot({ path: `${out}/modal-${width}.png` });
    await update(page, () => dialog.getByRole('button', { name: 'Fechar', exact: true }).click());
    await dialog.waitFor({ state: 'detached' });
    await navigate(page, 'Escalas', '/manager/shifts');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
}
try {
    for (const width of [390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', serviceWorkers: width === 1440 ? 'allow' : 'block' });
        const page = await context.newPage(); page.setDefaultTimeout(60000); page.setDefaultNavigationTimeout(60000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => {
            if (/multiple instances|Não foi possível iniciar/.test(message.text())) errors.push(message.text());
        });
        if (width === 390) {
            // An optional animation download must not prevent the login/CRUD runtime.
            await page.route('**/landing-*.js', r => r.abort());
            await page.goto(`${origin}/landing`, { waitUntil: 'networkidle' });
            assert.equal(await page.locator('.gsap-cta-box').evaluate(n => getComputedStyle(n).visibility), 'visible');
        }
        await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
        await page.locator('#email').fill(process.env.RUNTIME_QA_EMAIL);
        await page.locator('#password').fill(process.env.RUNTIME_QA_PASSWORD);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/manager/dashboard'); await settled(page);
        const announcement = page.getByRole('button', { name: 'Entendi, continuar', exact: true });
        if (await announcement.isVisible()) await update(page, () => announcement.click());
        await page.evaluate(() => { window.__qaLivewire = window.Livewire; window.__qaAlpine = window.Alpine; });
        await navigate(page, 'Relatórios', '/manager/reports');
        await navigate(page, 'Escalas', '/manager/shifts');
        assert.equal(await page.evaluate(() => window.__qaLivewire === window.Livewire && window.__qaAlpine === window.Alpine), true);
        await modalChecks(page, width);
        if (width === 390) {
            // Simulates Vite changing the hashed filename during wire:navigate.
            await page.route('**/build/assets/app-runtime-upgrade.js', r => r.fulfill({ status: 200, contentType: 'text/javascript', body: code }));
            let replaced = false;
            await page.route('**/manager/reports*', async r => {
                const response = await r.fetch(); let html = await response.text();
                if (!replaced) { html = html.replaceAll(asset, 'assets/app-runtime-upgrade.js'); replaced = true; }
                await r.fulfill({ response, body: html });
            });
            await page.evaluate(() => { window.__qaBeforeUpgrade = true; });
            await page.locator('a.sidebar-nav-link[href$="/manager/reports"]').evaluate(n => { n.href += '?tab=coverage'; });
            await navigate(page, 'Relatórios', '/manager/reports?tab=coverage');
            await page.waitForFunction(() => !window.__qaBeforeUpgrade);
            await settled(page);
            assert.equal(await page.locator('meta[name="mobile-web-app-capable"]').getAttribute('content'), 'yes');
            await navigate(page, 'Escalas', '/manager/shifts');
            await modalChecks(page, `${width}-upgrade`);
        }
        assert.deepEqual(errors, []);
        await context.close(); console.log(`runtime ${width}px: stable identities, save validation, edit, cancel, Escape, swap review/rejection validation, release recovery passed`);
    }
    if (process.env.RUNTIME_LEGACY_BUNDLE) {
        const legacy = readFileSync(process.env.RUNTIME_LEGACY_BUNDLE, 'utf8');
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
        const page = await context.newPage(); page.setDefaultTimeout(60000); page.setDefaultNavigationTimeout(60000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (/multiple instances/.test(message.text())) errors.push(message.text()); });
        let firstAsset = true;
        await page.route(`**/build/${asset}`, r => {
            if (!firstAsset) return r.continue();
            firstAsset = false; return r.fulfill({ status: 200, contentType: 'text/javascript', body: legacy });
        });
        await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
        await page.locator('#email').fill(process.env.RUNTIME_QA_EMAIL);
        await page.locator('#password').fill(process.env.RUNTIME_QA_PASSWORD);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL('**/manager/dashboard'); await settled(page);
        const announcement = page.getByRole('button', { name: 'Entendi, continuar', exact: true });
        if (await announcement.isVisible()) await update(page, () => announcement.click());
        assert.equal(await page.evaluate(() => !!window.__escalaFacilRuntime), false);
        await page.evaluate(() => { window.__qaLegacy = true; });
        await page.route('**/build/assets/app-runtime-legacy-upgrade.js', r => r.fulfill({ status: 200, contentType: 'text/javascript', body: code }));
        let replaced = false;
        await page.route('**/manager/shifts', async r => {
            const response = await r.fetch(); let html = await response.text();
            if (!replaced) { html = html.replaceAll(asset, 'assets/app-runtime-legacy-upgrade.js'); replaced = true; }
            await r.fulfill({ response, body: html });
        });
        await navigate(page, 'Escalas', '/manager/shifts');
        await page.waitForFunction(() => !window.__qaLegacy && window.__escalaFacilRuntime);
        await settled(page); await modalChecks(page, 'legacy-upgrade');
        assert.deepEqual(errors, []); await context.close();
        console.log('legacy runtime: recovery of a pre-fix open tab and modal controls passed');
    }
} finally { await browser.close(); }
