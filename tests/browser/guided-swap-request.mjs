// Browser plugin unavailable: Playwright fallback with disposable QA accounts.
import assert from 'node:assert/strict';
import {mkdirSync, writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert(process.env.PROFESSIONAL_HOME_EMAIL && process.env.PROFESSIONAL_HOME_PASSWORD);
const browser = await chromium.launch({channel: process.env.PLAYWRIGHT_CHANNEL});
const origin = process.env.PROFESSIONAL_HOME_URL ?? 'http://127.0.0.1:8797';
const out = process.env.GUIDED_SWAP_SCREENSHOTS;
if (out) mkdirSync(out, {recursive: true});
const report = {checks: [], errors: []};
try {
 for (const width of [320, 390, 1440]) {
  const context = await browser.newContext({viewport: {width, height: 800}, hasTouch: width < 1024, reducedMotion: 'reduce', serviceWorkers: 'allow'});
  const page = await context.newPage();
  page.on('pageerror', e => report.errors.push(e.message));
  page.on('console', m => {if (m.type() === 'error' || /multiple instances/.test(m.text())) report.errors.push(m.text());});
  await page.goto(origin + '/login');
  await page.locator('#email').fill(process.env.PROFESSIONAL_HOME_EMAIL);
  await page.locator('#password').fill(process.env.PROFESSIONAL_HOME_PASSWORD);
  await page.getByRole('button', {name: 'Entrar', exact: true}).click();
  await page.waitForURL('**/professional/dashboard');
  await page.waitForLoadState('networkidle');
  const announcement = page.getByRole('button', {name: 'Entendi, continuar', exact: true});
  if (await announcement.isVisible()) {await announcement.click(); await announcement.waitFor({state: 'detached'});}
  for (const mode of ['simple', 'mutual']) {
   const opener = width < 1024 ? page.locator('.professional-next').getByRole('button', {name: 'Solicitar troca', exact: true}) : page.locator('.professional-schedule').getByRole('button', {name: 'Solicitar troca', exact: true}).filter({visible: true}).first();
   await opener.click();
   const dialog = page.getByRole('dialog', {name: 'Solicitar troca', exact: true});
   await dialog.waitFor({state: 'visible'});
   await page.waitForFunction(() => document.querySelector('#swap-request')?.contains(document.activeElement));
   assert.equal(await dialog.getAttribute('aria-modal'), 'true');
   assert(await dialog.locator('input[name="chosen-shift"]:checked').count());
   for (let i = 0; i < 8; i++) {await page.keyboard.press('Tab'); assert(await dialog.evaluate(el => el.contains(document.activeElement)));}
   const next = dialog.getByRole('button', {name: 'Continuar', exact: true});
   await next.click(); await dialog.getByRole('heading', {name: 'Defina a modalidade', exact: true}).waitFor();
   if (mode === 'mutual') await dialog.getByLabel('Troca mútua', {exact: false}).check();
   await next.click(); await dialog.getByRole('heading', {name: 'Escolha o colega', exact: true}).waitFor();
   await next.click();
   await dialog.locator('#substitute-error').waitFor({state: 'visible'});
   await page.waitForFunction(() => document.activeElement?.id === 'eligible-professionals');
   const search = dialog.getByLabel('Pesquisar profissional', {exact: true});
   await search.fill('nenhum-colega-qa-0000'); await dialog.getByText('Nenhum profissional encontrado', {exact: true}).waitFor();
   await search.fill('Plantonista QA calendário 1');
   const colleague = dialog.getByLabel('Plantonista QA calendário 1', {exact: true});
   await colleague.waitFor(); await colleague.check();
   if (mode === 'mutual') {await dialog.locator('input[name="counterpart"]').first().waitFor(); await dialog.locator('input[name="counterpart"]').first().check();}
   await next.click(); await dialog.getByRole('heading', {name: 'Revise os detalhes', exact: true}).waitFor();
   assert((await dialog.innerText()).includes('Plantonista QA calendário 1'));
   assert((await dialog.textContent()).includes(mode === 'mutual' ? 'Troca mútua' : 'Troca simples'));
   if (mode === 'mutual') assert.equal(await dialog.locator('div.rounded-lg').filter({hasText: 'assumirá o plantão de'}).count(), 2);
   await dialog.getByRole('button', {name: 'Voltar', exact: true}).click(); await colleague.waitFor();
   assert(await colleague.isChecked());
   if (mode === 'mutual') assert.equal(await dialog.locator('input[name="counterpart"]:checked').count(), 1);
   await next.click(); await dialog.getByRole('heading', {name: 'Revise os detalhes', exact: true}).waitFor();
   await next.click(); await dialog.getByRole('heading', {name: 'Enviar solicitação de troca?', exact: true}).waitFor();
   assert.equal(await dialog.getByRole('button', {name: 'Confirmar solicitação', exact: true}).getAttribute('wire:loading.attr'), 'disabled');
   const box = await dialog.boundingBox(); assert(box.x >= 0 && box.x + box.width <= width + 1 && box.y >= 0 && box.y + box.height <= 801);
   assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
   await page.locator('#nprogress').waitFor({state: 'detached'});
   if (process.env.AXE_MODULE) {
    await page.addScriptTag({path: process.env.AXE_MODULE});
    const violations = await page.evaluate(async () => (await axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa']}})).violations);
    assert.deepEqual(violations.map(v => ({id: v.id, nodes: v.nodes.map(n => n.target)})), []);
   }
   if (out) await page.screenshot({path: `${out}/swap-${mode}-${width}.png`});
   await dialog.getByRole('button', {name: 'Cancelar', exact: true}).click();
   await dialog.getByText('Há alterações não salvas. Deseja descartá-las?', {exact: true}).waitFor();
   await dialog.getByRole('button', {name: 'Continuar editando', exact: true}).click();
   await dialog.getByRole('button', {name: 'Confirmar solicitação', exact: true}).waitFor();
   await page.waitForFunction(() => document.activeElement?.id === 'swap-form-title');
   await page.keyboard.press('Escape');
   await dialog.getByRole('button', {name: 'Descartar alterações', exact: true}).click();
   await dialog.waitFor({state: 'detached'});
   await page.waitForFunction(() => document.activeElement?.matches('[data-swap-return]'));
   report.checks.push({width, mode, passed: true});
  }
  // Verify wire:navigate can return to the professional module without duplicate runtimes.
  await page.goto(origin + '/professional/calendar'); await page.waitForLoadState('networkidle');
  await page.getByRole('link', {name: width < 1024 ? 'Escala' : 'Minha escala', exact: true}).filter({visible: true}).first().click();
  await page.waitForURL('**/professional/dashboard**'); await page.waitForLoadState('networkidle');
  await context.close();
 }
 assert.deepEqual(report.errors, []);
} finally {await browser.close(); if (out) writeFileSync(out + '/report.json', JSON.stringify(report, null, 2));}
console.log(JSON.stringify(report));
