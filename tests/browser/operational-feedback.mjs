// Browser plugin unavailable: Playwright using a supplied disposable fixture only.
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
assert(process.env.FEEDBACK_FIXTURE && process.env.FEEDBACK_PASSWORD);
const fixture = JSON.parse(readFileSync(process.env.FEEDBACK_FIXTURE, 'utf8'));
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin = process.env.FEEDBACK_URL ?? 'http://127.0.0.1:8799';
const out = process.env.FEEDBACK_SCREENSHOTS;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL });
const report = [];
try {
    for (const width of [320, 390, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', serviceWorkers: width === 1440 ? 'allow' : 'block' });
        const page = await context.newPage(); page.setDefaultTimeout(30000);
        const runtimeErrors = []; page.on('pageerror', error => runtimeErrors.push(error.message));
        await page.goto(origin + '/login'); await page.locator('#email').fill(fixture.email); await page.locator('#password').fill(process.env.FEEDBACK_PASSWORD);
        await page.getByRole('button', { name: 'Entrar', exact: true }).click(); await page.waitForURL('**/manager/dashboard'); await page.waitForLoadState('networkidle');
        const announcement = page.getByRole('button', { name: 'Entendi, continuar', exact: true }); if (await announcement.isVisible()) await announcement.click();
        await page.goto(origin + '/manager/sectors');
        await page.getByRole('button', { name: 'Adicionar setor', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'Adicionar setor', exact: true }); await dialog.waitFor();
        const field = page.locator('#sector-name'), submit = dialog.locator('button[type="submit"]');
        await submit.click(); await page.locator('#sector-name[aria-invalid="true"]').waitFor();
        assert.equal(await page.locator('.toast-success:visible').count(), 0, 'Validation never announces success');
        const name = `Feedback QA ${width} ${Date.now()}`; await field.fill(name);
        let mode = 'delay', requests = 0, release, reached;
        const held = new Promise(resolve => release = resolve), started = new Promise(resolve => reached = resolve);
        await page.route('**/livewire-*/update', async route => {
            const actions = JSON.parse(route.request().postData()).components?.flatMap(component => component.calls.map(call => call.method)) ?? [];
            if (!actions.includes('save')) return route.continue();
            requests++;
            if (mode === 'delay') { const response = await route.fetch(); reached(); await held; return route.fulfill({ response }); }
            if (mode === 'abort') return route.abort('failed');
            return route.fulfill({ status: Number(mode), contentType: 'text/html', body: 'private SQL token stack details' });
        });
        await submit.click(); await started;
        assert(await submit.isDisabled()); assert(await dialog.getByRole('status').filter({ hasText: 'Processando solicitação' }).isVisible());
        assert.equal(await page.locator('.toast-success:visible').count(), 0, 'No success before the backend response');
        await page.evaluate(() => Livewire.find(document.querySelector('[data-ui-dialog-component]').getAttribute('data-ui-dialog-component')).save().catch(() => {}));
        assert.equal(requests, 1, 'Repeated activation is cancelled before sending');
        release(); await dialog.waitFor({ state: 'hidden' });
        const success = page.locator('.toast-success:visible'); await success.waitFor();
        assert.equal(await success.count(), 1); await success.getByRole('button', { name: 'Fechar mensagem de sucesso' }).focus();
        await page.evaluate(() => { const state = Alpine.store('operationalFeedback'); state.notify({ ...state.messages[0] }); });
        assert.equal(await success.count(), 1, 'A duplicate confirmed response is shown once');
        await success.getByRole('button', { name: 'Fechar mensagem de sucesso' }).click();
        mode = 'abort'; await page.getByRole('button', { name: 'Adicionar setor', exact: true }).click(); await field.fill('Preservar entrada'); await submit.click();
        const error = dialog.locator('[data-operation-error]'); await error.waitFor({ state: 'visible' });
        assert.match(await error.textContent(), /pode ter sido recebida/); assert.equal(await field.inputValue(), 'Preservar entrada');
        assert.equal(await page.locator('.toast-success:visible').count(), 0); assert(await dialog.isVisible());
        assert.equal(await dialog.getByRole('button', { name: 'Tentar atualizar consulta' }).isVisible(), false, 'A failed mutation is never offered for replay');
        await context.setOffline(true); await error.filter({ hasText: 'conexão está indisponível' }).waitFor();
        await context.setOffline(false); const count = requests;
        await dialog.getByRole('button', { name: 'Verificar conexão', exact: true }).click();
        await dialog.getByText('A conexão respondeu.', { exact: false }).waitFor(); assert.equal(requests, count);
        mode = '500'; await submit.click(); await error.filter({ hasText: 'pode ter sido recebida' }).waitFor();
        assert(!await page.locator('body').innerText().then(text => text.includes('private SQL')));
        mode = '419'; await submit.click(); await error.filter({ hasText: 'sessão expirou' }).waitFor();
        assert.equal(await field.inputValue(), 'Preservar entrada'); assert(page.url().endsWith('/manager/sectors'));
        assert(await dialog.getByRole('button', { name: 'Atualizar página para entrar' }).isVisible());
        if (out) await page.screenshot({ path: `${out}/expired-${width}.png`, fullPage: false });
        await page.unroute('**/livewire-*/update'); await dialog.getByRole('button', { name: 'Atualizar página para entrar' }).click(); await page.waitForLoadState('networkidle');
        await page.getByRole('button', { name: 'Adicionar setor', exact: true }).click(); await field.fill('Conflito não gravado');
        await page.route('**/livewire-*/update', route => {
            const body = route.request().postData();
            return body.includes('"method":"save"') ? route.fulfill({ status: 409, contentType: 'text/html', body: 'private stack' }) : route.continue();
        });
        await submit.click(); await error.filter({ hasText: 'Atualize e revise' }).waitFor(); assert.equal(await field.inputValue(), 'Conflito não gravado');
        if (process.env.AXE_MODULE) {
            await page.addScriptTag({ path: process.env.AXE_MODULE });
            const issues = await page.evaluate(async () => (await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target) })));
            assert.deepEqual(issues, []);
        }
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        assert.deepEqual(runtimeErrors, []);
        await page.unroute('**/livewire-*/update');
        await page.goto(origin + '/manager/swaps?request=1');
        await page.getByRole('button', { name: 'Analisar pedido #1', exact: true }).click();
        const decision = page.locator('#decide-swap'); await decision.waitFor();
        await decision.getByRole('button', { name: 'Rejeitar…', exact: true }).click();
        await decision.getByLabel('Justificativa da rejeição', { exact: false }).fill('Conservar revisão administrativa');
        let decisions = 0;
        await page.route('**/livewire-*/update', route => {
            if (route.request().postData().includes('"method":"confirmDecision"')) { decisions++; return route.abort('failed'); }
            return route.continue();
        });
        await decision.getByRole('button', { name: 'Confirmar rejeição', exact: true }).click();
        await decision.locator('[data-operational-inline] [data-operation-error]').filter({ hasText: 'pode ter sido recebida' }).waitFor();
        await context.setOffline(true); await context.setOffline(false);
        await decision.getByRole('button', { name: 'Verificar conexão', exact: true }).click();
        await decision.getByText('A conexão respondeu.', { exact: false }).waitFor();
        assert.equal(decisions, 1, 'Recovery never replays a managerial decision');
        assert.equal(await decision.getByLabel('Justificativa da rejeição', { exact: false }).inputValue(), 'Conservar revisão administrativa');
        await decision.getByRole('button', { name: 'Voltar à análise', exact: true }).click();
        await decision.getByRole('button', { name: 'Fechar', exact: true }).click();
        await page.unroute('**/livewire-*/update');
        const guest = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block' });
        const login = await guest.newPage(); await login.goto(origin + '/login');
        await login.route('**/livewire-*/update', route => route.fulfill({ status: 419, contentType: 'text/html', body: 'private session stack' }));
        await login.locator('#email').fill('preserved@example.test'); await login.locator('#password').fill('Preserve-QA-Only');
        await login.getByRole('button', { name: 'Entrar', exact: true }).click();
        await login.locator('[data-operational-inline] [data-operation-error]').filter({ hasText: 'sessão expirou' }).waitFor();
        assert.equal(await login.locator('#email').inputValue(), 'preserved@example.test');
        assert.equal(await login.locator('#password').inputValue(), 'Preserve-QA-Only');
        assert.equal(await login.getByRole('button', { name: 'Atualizar página para entrar' }).count(), 1);
        assert(login.url().endsWith('/login')); await guest.close();
        await page.goto(origin + '/manager/shifts');
        await page.getByRole('button', { name: 'Importar', exact: true }).click();
        const importing = page.getByRole('dialog', { name: 'Importar plantões', exact: true }); await importing.waitFor();
        await page.waitForFunction(() => document.activeElement?.id === 'import-file');
        const importSubmit = importing.locator('button[type="submit"]');
        await importing.locator('#import-file').evaluate(el => el.dispatchEvent(new CustomEvent('livewire-upload-start', { bubbles: true, detail: { id: el.closest('[wire\\:id]').getAttribute('wire:id'), property: 'importFile' } })));
        await page.waitForFunction(() => document.querySelector('form[wire\\:submit="confirmImport"] button[type="submit"]').disabled);
        await importing.locator('#import-file').evaluate(el => el.dispatchEvent(new CustomEvent('livewire-upload-error', { bubbles: true, detail: { id: el.closest('[wire\\:id]').getAttribute('wire:id'), property: 'importFile' } })));
        assert(await importSubmit.isEnabled(), 'Failed upload releases submit without losing the form');
        await page.route('**/livewire-*/update', route => route.request().postData().includes('"method":"confirmImport"') ? route.abort('failed') : route.continue());
        await importSubmit.click();
        await importing.locator('[data-operational-inline] [data-operation-error]').filter({ hasText: 'pode ter sido recebida' }).waitFor();
        await page.waitForFunction(() => !document.querySelector('form[wire\\:submit="confirmImport"] button[type="submit"]').disabled);
        assert(await importing.isVisible());
        assert.deepEqual(runtimeErrors, []);
        report.push({ width, latencyDuplicateValidationConnectionExpiredConflict: 'passed', axeAA: 0 });
        await context.close();
    }
    console.log(JSON.stringify(report));
} finally { await browser.close(); }
