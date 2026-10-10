// Local QA only. Use a disposable database and array mailer for AUTH_QA_EMAIL.
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = process.env.AUTH_URL ?? 'http://127.0.0.1:8767';
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
const out = process.env.AUTH_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const email = process.env.AUTH_QA_EMAIL;
const password = process.env.AUTH_QA_PASSWORD;

async function audit(page) {
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    if (!process.env.AXE_MODULE) return;
    await page.addScriptTag({ path: process.env.AXE_MODULE });
    const violations = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice'] } })).violations);
    assert.deepEqual(violations.map(({ id, nodes }) => ({ id, targets: nodes.map((node) => node.target) })), []);
}

try {
    for (const viewport of [{ width: 320, height: 568 }, { width: 390, height: 844 }, { width: 1440, height: 1000 }]) {
        const context = await browser.newContext({ viewport, serviceWorkers: 'block' });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto(origin + '/login', { waitUntil: 'networkidle' });
        assert.match(await page.title(), /Entrar.*Escala Fácil/);
        assert.equal(await page.locator('h1').innerText(), 'Entre na sua conta');
        assert.equal(await page.evaluate(() => document.activeElement.tagName), 'BODY');
        assert.equal(await page.locator('.auth-context').isVisible(), viewport.width >= 1024);
        assert.equal(await page.locator('[data-pwa-install]').isVisible(), false);
        await audit(page);
        if (out) await page.screenshot({ path: `${out}/login-${viewport.width}.png`, fullPage: true });

        const toggle = page.locator('button[aria-controls="password"]');
        assert.equal(await toggle.getAttribute('aria-label'), 'Mostrar senha');
        await page.locator('#password').fill('fixture-not-a-real-password');
        await toggle.focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('#password').getAttribute('type'), 'text');
        assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('#password').getAttribute('type'), 'password');
        assert.equal(await page.locator('#password').inputValue(), 'fixture-not-a-real-password');

        const submit = page.locator('button[type=submit]');
        const before = await submit.boundingBox();
        let requests = 0;
        await page.route('**/livewire-*/update', async (route) => {
            requests++;
            await new Promise((resolve) => setTimeout(resolve, 800));
            await route.continue();
        });
        await submit.click();
        await page.waitForFunction(() => document.querySelector('button[type=submit]').disabled
            && document.querySelector('[data-auth-form]').getAttribute('aria-busy') === 'true');
        assert.equal(await page.locator('[data-auth-form]').getAttribute('aria-busy'), 'true');
        const during = await submit.boundingBox();
        assert.equal(during.width, before.width);
        assert.equal(during.height, before.height);
        await page.locator('#email-error').waitFor({ state: 'visible' });
        await page.waitForFunction(() => document.activeElement.id === 'email');
        assert.equal(requests, 1);
        await page.unroute('**/livewire-*/update');
        await audit(page);
        if (out) await page.screenshot({ path: `${out}/validation-${viewport.width}.png`, fullPage: true });

        // Short viewport approximates the space left by an on-screen keyboard.
        if (viewport.width < 1024) {
            await page.setViewportSize({ width: viewport.width, height: 320 });
            await page.locator('#password').focus();
            await page.locator('#password').scrollIntoViewIfNeeded();
            assert.ok((await page.locator('#password').boundingBox()).y >= 0);
            await submit.scrollIntoViewIfNeeded();
            assert.ok((await submit.boundingBox()).y >= 0);
            await audit(page);
            await page.setViewportSize(viewport);
        }

        await page.getByRole('link', { name: 'Esqueci minha senha' }).click();
        await page.waitForURL('**/forgot-password');
        await page.locator('button[type=submit]').click();
        await page.locator('#email-error').waitFor({ state: 'visible' });
        await page.waitForFunction(() => document.activeElement.id === 'email');
        if (email && viewport.width === 320) {
            await page.locator('#email').fill(email);
            await page.locator('button[type=submit]').click();
            await page.waitForFunction(() => document.querySelector('.auth-session-status')
                || (document.getElementById('email-error') && !document.getElementById('email-error').textContent.includes('obrigatório')));
            if (await page.locator('#email-error').count()) {
                assert.match(await page.locator('#email-error').innerText(), /aguarde|espere/i);
            }
        }
        await audit(page);
        await page.getByRole('link', { name: 'Voltar para entrar' }).click();
        await page.waitForURL('**/login');

        await page.goto(origin + '/reset-password/invalid?email=' + encodeURIComponent(email ?? 'fixture@example.test'), { waitUntil: 'networkidle' });
        await page.locator('#password').fill('new-fixture-password');
        await page.locator('#password_confirmation').fill('mismatch');
        await page.locator('button[type=submit]').click();
        await page.locator('#password-error').waitFor({ state: 'visible' });
        await page.waitForFunction(() => document.activeElement.id === 'password');
        await audit(page);
        await page.locator('#password_confirmation').fill('new-fixture-password');
        await page.locator('button[type=submit]').click();
        await page.locator('#email-error').waitFor({ state: 'visible' });
        await page.waitForFunction(() => document.activeElement.id === 'email');

        await page.goto(origin + '/login', { waitUntil: 'networkidle' });
        const position = await page.locator('button[type=submit]').boundingBox();
        await page.evaluate(() => {
            const offer = new Event('beforeinstallprompt', { cancelable: true });
            offer.prompt = async () => {};
            offer.userChoice = new Promise((resolve) => { window.finishInstall = resolve; });
            dispatchEvent(offer);
        });
        const install = page.locator('[data-pwa-install]');
        await install.waitFor({ state: 'visible' });
        assert.equal((await page.locator('button[type=submit]').boundingBox()).y, position.y);
        await install.click();
        assert.equal(await install.isDisabled(), true);
        assert.equal(await page.locator('button[type=submit]').isEnabled(), true);
        await page.evaluate(() => { dispatchEvent(new Event('appinstalled')); window.finishInstall({ outcome: 'accepted' }); });
        await page.locator('[data-pwa-support]').waitFor({ state: 'hidden' });
        await page.reload({ waitUntil: 'networkidle' });

        await page.route('**/livewire-*/update', (route) => route.fulfill({ status: 503, body: 'Test-only unavailable' }));
        await page.locator('#email').fill('fixture@example.test');
        await page.locator('#password').fill('keep-this-fixture');
        await page.locator('button[type=submit]').click();
        await page.locator('[data-auth-request-error]').waitFor({ state: 'visible' });
        await page.waitForFunction(() => !document.querySelector('button[type=submit]').disabled);
        assert.equal(await page.locator('#password').inputValue(), 'keep-this-fixture');
        assert.equal(await page.locator('button[type=submit]').isEnabled(), true);
        assert.equal(await page.locator('#livewire-error').count(), 0);
        await page.unroute('**/livewire-*/update');
        await page.route('**/livewire-*/update', (route) => route.fulfill({ status: 419, body: 'Test-only expired' }));
        await page.locator('button[type=submit]').click();
        await page.locator('[data-auth-reload]').waitFor({ state: 'visible' });
        assert.match(await page.locator('[data-auth-request-message]').innerText(), /expirou/);
        await page.unroute('**/livewire-*/update');
        await Promise.all([page.waitForEvent('load'), page.locator('[data-auth-reload]').click()]);
        await page.waitForLoadState('networkidle');

        if (email && password) {
            // Model the autofill case that emits neither input nor change.
            await page.locator('#email').evaluate((element, value) => { element.value = value; }, email);
            await page.locator('#password').evaluate((element, value) => { element.value = value; }, password);
            await page.locator('button[type=submit]').click();
            await page.waitForURL('**/manager/dashboard');
            assert.ok(await page.locator('meta[name=app-version]').getAttribute('content'));
        }
        assert.deepEqual(errors, []);
        console.log(`PASS ${viewport.width}px: login/recovery/reset, keyboard, loading, autofill, errors, expiration, optional installation, AA audit`);
        await context.close();
    }

    const ios = await browser.newContext({ viewport: { width: 390, height: 844 }, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', serviceWorkers: 'block' });
    const iosPage = await ios.newPage();
    await iosPage.goto(origin + '/login', { waitUntil: 'networkidle' });
    assert.equal(await iosPage.locator('[data-pwa-install]').isVisible(), false);
    const summary = iosPage.locator('[data-pwa-ios] summary');
    await summary.focus();
    await iosPage.keyboard.press('Enter');
    assert.ok(await iosPage.getByText('Abra esta página no Safari.').isVisible());
    await audit(iosPage);
    if (out) await iosPage.screenshot({ path: `${out}/ios-guidance.png`, fullPage: true });
    await ios.close();

    const noJs = await browser.newContext({ viewport: { width: 320, height: 568 }, javaScriptEnabled: false });
    const staticPage = await noJs.newPage();
    for (const route of ['/login', '/reset-password/invalid']) {
        await staticPage.goto(origin + route);
        assert.equal(await staticPage.locator('#password').getAttribute('type'), 'password');
        assert.equal(await staticPage.locator('[data-pwa-support]').isVisible(), false);
        assert.equal(await staticPage.locator('.password-toggle').first().isVisible(), false);
    }
    await noJs.close();
    console.log('PASS simulated iOS guidance and password masking without JavaScript');
} finally {
    await browser.close();
}
