// Browser plugin unavailable; Playwright fallback with a disposable synthetic database and mail transport.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.INVITATION_FIXTURE && process.env.INVITATION_QA_PASSWORD, 'Use a disposable invitation fixture.');
const fixture = JSON.parse(readFileSync(process.env.INVITATION_FIXTURE, 'utf8').replace(/^\uFEFF/, ''));
const origin = process.env.INVITATION_URL ?? 'http://127.0.0.1:8791';
const out = process.env.INVITATION_SCREENSHOTS;
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
async function login(page, email, password, role) {
    await page.goto(`${origin}/login`, { waitUntil: 'networkidle' });
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(`**/${role}/dashboard`);
    await settled(page);
}
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', serviceWorkers: 'block' });
        const page = await context.newPage();
        page.setDefaultTimeout(30000);
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', e => { if (e.type() === 'error') errors.push(e.text()); });
        await login(page, 'invites-manager@example.test', process.env.INVITATION_QA_PASSWORD, 'manager');
        await page.goto(`${origin}/manager/users`, { waitUntil: 'networkidle' });
        await update(page, () => page.getByRole('button', { name: 'Convidar profissional', exact: true }).click());
        const dialog = page.getByRole('dialog');
        await page.waitForFunction(() => document.activeElement?.id === 'user-name');
        assert.equal(await dialog.locator('input[type="password"]').count(), 0);
        assert.equal(await page.locator('#user-role').isDisabled(), true);
        await page.locator('#user-name').fill(`Novo Convite QA ${width}`);
        await page.locator('#user-email').fill(`new-${width}@example.test`);
        await page.locator('#user-category').selectOption(String(fixture.category));
        await page.locator('#user-sector').selectOption(String(fixture.sector));
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/invite-${width}.png` });
        await update(page, () => dialog.getByRole('button', { name: 'Enviar convite', exact: true }).click());
        await dialog.waitFor({ state: 'detached' });
        await update(page, () => page.locator('#user-search').fill(`Novo Convite QA ${width}`));
        const record = page.locator(width < 768 ? '.record-card' : 'tbody tr').filter({ hasText: `new-${width}@example.test` });
        await record.getByText('Convite: Pendente', { exact: true }).waitFor();
        await update(page, () => record.getByRole('button', { name: 'Reenviar convite', exact: true }).click());
        await update(page, () => record.getByRole('button', { name: 'Revogar convite', exact: true }).click());
        const revoke = page.getByRole('alertdialog');
        await revoke.getByRole('heading', { name: 'Revogar convite?' }).waitFor();
        await settled(page, true);
        await update(page, () => revoke.getByRole('button', { name: 'Revogar convite', exact: true }).click());
        await revoke.waitFor({ state: 'detached' });
        await record.getByText('Convite: Revogado', { exact: true }).waitFor();
        await update(page, () => record.getByRole('button', { name: 'Reenviar convite', exact: true }).click());
        await record.getByText('Convite: Pendente', { exact: true }).waitFor();
        await page.reload({ waitUntil: 'networkidle' });
        await settled(page, true);
        if (out) await page.screenshot({ path: `${out}/status-${width}.png` });
        const activationContext = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block' });
        const activation = await activationContext.newPage();
        activation.setDefaultTimeout(30000);
        activation.on('pageerror', e => errors.push(e.message));
        await activation.goto(fixture.teams[width].url, { waitUntil: 'networkidle' });
        assert.equal(new URL(activation.url()).searchParams.has('token'), false);
        assert.equal(new URL(activation.url()).searchParams.has('grant'), true);
        await activation.reload({ waitUntil: 'networkidle' });
        await activation.getByRole('heading', { name: 'Crie sua senha', exact: true }).waitFor();
        await update(activation, () => activation.getByRole('button', { name: 'Ativar minha conta', exact: true }).click());
        await activation.locator('#password-error').waitFor();
        await settled(activation, true);
        await activation.locator('#password').fill('Own-QA-password-2026');
        await activation.locator('#password_confirmation').fill('Own-QA-password-2026');
        if (out) await activation.screenshot({ path: `${out}/activation-${width}.png` });
        await activation.getByRole('button', { name: 'Ativar minha conta', exact: true }).click();
        await activation.waitForURL('**/login');
        await activation.getByText('Sua conta foi ativada.', { exact: false }).waitFor();
        await activation.goto(fixture.teams[width].url, { waitUntil: 'networkidle' });
        await activation.locator('#password').fill('Other-QA-password-2026');
        await activation.locator('#password_confirmation').fill('Other-QA-password-2026');
        await update(activation, () => activation.getByRole('button', { name: 'Ativar minha conta', exact: true }).click());
        await activation.getByText('Não foi possível usar este convite. Solicite um novo link à gerência.', { exact: true }).waitFor();
        await login(activation, fixture.teams[width].email, 'Own-QA-password-2026', 'professional');
        await activationContext.close();
        await update(page, () => page.locator('#user-search').fill(fixture.teams[width].email));
        await page.getByText('Convite: Aceito', { exact: true }).filter({ visible: true }).waitFor();
        assert.deepEqual(errors, []);
        await context.close();
        console.log(`Invitations ${width}px: creation, resend, revocation, status, activation, refresh, reuse, login and accessibility passed.`);
    }
} finally {
    await browser.close();
}
