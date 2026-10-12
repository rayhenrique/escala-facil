// Browser plugin unavailable: Playwright fallback. Mutates disposable QA requests only.
import assert from 'node:assert/strict';
import {readFileSync, mkdirSync, writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert(process.env.INCOMING_RESPONSE_FIXTURE && process.env.INCOMING_RESPONSE_PASSWORD);
const fixture = JSON.parse(readFileSync(process.env.INCOMING_RESPONSE_FIXTURE, 'utf8'));
const origin = process.env.INCOMING_RESPONSE_URL ?? 'http://127.0.0.1:8797';
const out = process.env.INCOMING_RESPONSE_SCREENSHOTS;
if (out) mkdirSync(out, {recursive: true});
const browser = await chromium.launch({channel: process.env.PLAYWRIGHT_CHANNEL});
const report = {checks: [], errors: []};
try {
 for (const width of [320, 390, 1440]) {
  const context = await browser.newContext({viewport: {width, height: 850}, hasTouch: width < 1024, reducedMotion: 'reduce', serviceWorkers: 'allow'});
  const page = await context.newPage();
  let expectedTransportError = false, navigations = 0;
  page.on('pageerror', e => {if (!expectedTransportError) report.errors.push(e.message);});
  page.on('console', m => {if (!expectedTransportError && (m.type() === 'error' || /multiple instances/.test(m.text()))) report.errors.push(m.text());});
  page.on('framenavigated', frame => {if (frame === page.mainFrame()) navigations++;});
  await page.goto(origin + '/login');
  await page.locator('#email').fill(fixture.email); await page.locator('#password').fill(process.env.INCOMING_RESPONSE_PASSWORD);
  await page.getByRole('button', {name: 'Entrar', exact: true}).click(); await page.waitForURL('**/professional/dashboard');
  await page.waitForLoadState('networkidle');
  const announcement = page.getByRole('button', {name: 'Entendi, continuar', exact: true});
  if (await announcement.isVisible()) {await announcement.click(); await announcement.waitFor({state: 'detached'});}
  const heading = width < 1024 ? 'incoming-title' : 'incoming-desktop-title';
  const open = async id => {
   const button = page.locator(`#${heading}-details-${id}`);
   await button.click(); const dialog = page.getByRole('dialog', {name: 'Detalhes do pedido recebido', exact: true});
   await dialog.waitFor({state: 'visible'});
   await page.waitForFunction(() => document.activeElement?.id === 'incoming-response-title');
   return dialog;
  };
  const audit = async () => {
   await page.locator('#nprogress').waitFor({state: 'detached'});
   assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
   if (process.env.AXE_MODULE) {
    await page.addScriptTag({path: process.env.AXE_MODULE});
    const violations = await page.evaluate(async () => (await axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa']}})).violations);
    assert.deepEqual(violations.map(v => ({id: v.id, nodes: v.nodes.map(n => n.target)})), []);
   }
  };
  for (const mode of ['simple', 'mutual']) {
   const id = fixture.cases[width][mode], beforeNav = navigations;
   let dialog = await open(id);
   assert((await dialog.textContent()).includes(mode === 'mutual' ? 'Troca mútua' : 'Troca simples'));
   assert((await dialog.textContent()).includes('12h'));
   if (mode === 'mutual') assert((await dialog.textContent()).includes('24h'));
   for (let i = 0; i < 8; i++) {await page.keyboard.press('Tab'); assert(await dialog.evaluate(el => el.contains(document.activeElement)));}
   await audit(); if (out) await page.screenshot({path: `${out}/details-${mode}-${width}.png`});
   await dialog.getByRole('button', {name: mode === 'simple' ? 'Aceitar e enviar à gerência' : 'Recusar', exact: true}).click();
   const confirmation = page.getByRole('alertdialog', {name: mode === 'simple' ? 'Aceitar cobertura deste plantão?' : 'Recusar este pedido?', exact: true});
   await confirmation.waitFor({state: 'visible'});
   assert.equal(await page.getByRole('dialog').count(), 0);
   await page.waitForFunction(() => document.activeElement?.hasAttribute('data-confirm-cancel'));
   await confirmation.getByRole('button', {name: 'Voltar e revisar', exact: true}).click();
   dialog = page.getByRole('dialog', {name: 'Detalhes do pedido recebido', exact: true}); await dialog.waitFor();
   await dialog.getByRole('button', {name: mode === 'simple' ? 'Aceitar e enviar à gerência' : 'Recusar', exact: true}).click();
   await confirmation.waitFor();
   if (mode === 'mutual') await confirmation.getByLabel('Motivo da recusa', {exact: false}).fill('Indisponível neste dia (QA).');
   await audit(); if (out) await page.screenshot({path: `${out}/confirmation-${mode}-${width}.png`});
   const confirm = confirmation.getByRole('button', {name: mode === 'simple' ? 'Confirmar aceite' : 'Confirmar recusa', exact: true});
   // Delay only this confirmation, so loading/close protection can be observed.
   let delay = true;
   const delayHandler = async route => {
    if (delay && /confirmAcceptSwap|confirmDeclineSwap/.test(route.request().postData() ?? '')) {delay = false; await new Promise(resolve => setTimeout(resolve, 350));}
    await route.continue();
   };
   await page.route(/\/livewire[^/]*\/update(?:\?|$)/, delayHandler);
   await confirm.click(); await page.waitForFunction(() => document.querySelector('#professional-action [data-confirm-cancel]')?.disabled);
   assert(await confirm.isDisabled());
   await confirmation.waitFor({state: 'detached'}); await page.unroute(/\/livewire[^/]*\/update(?:\?|$)/, delayHandler);
   await page.locator(`#${heading}-details-${id}`).waitFor({state: 'detached'});
   assert.equal(navigations, beforeNav);
   assert(await page.locator('.swap-receipt').filter({hasText: `TROCA-${String(id).padStart(6, '0')}`}).count());
   report.checks.push({width, mode, passed: true});
  }
  if (width === 390) {
   for (const [kind, id] of [['before', fixture.offline], ['after', fixture.lostResponse]]) {
    let dialog = await open(id); await dialog.getByRole('button', {name: 'Aceitar e enviar à gerência', exact: true}).click();
    const confirmation = page.getByRole('alertdialog', {name: 'Aceitar cobertura deste plantão?', exact: true}); await confirmation.waitFor();
    expectedTransportError = true;
    const handler = async route => {
     if (/confirmAcceptSwap/.test(route.request().postData() ?? '')) {
      if (kind === 'after') await route.fetch(); // Server commits, browser never receives the successful response.
      await route.abort('failed');
     } else await route.continue();
    };
    await page.route(/\/livewire[^/]*\/update(?:\?|$)/, handler);
    await confirmation.getByRole('button', {name: 'Confirmar aceite', exact: true}).click();
    await confirmation.locator('[data-ui-request-error]').waitFor({state: 'visible'});
    await page.waitForFunction(() => !document.querySelector('#professional-action [data-confirm-cancel]')?.disabled);
    await page.unroute(/\/livewire[^/]*\/update(?:\?|$)/, handler);
    await confirmation.getByRole('button', {name: 'Atualizar detalhes', exact: true}).click();
    dialog = page.getByRole('dialog', {name: 'Detalhes do pedido recebido', exact: true}); await dialog.waitFor();
    if (kind === 'after') {
     assert((await dialog.textContent()).includes('Aguardando gerência'));
     assert(await dialog.getByRole('button', {name: 'Aceitar e enviar à gerência', exact: true}).isDisabled());
     await dialog.getByRole('button', {name: 'Fechar', exact: true}).click();
    } else {
     assert((await dialog.textContent()).includes('Aguardando profissional'));
     await dialog.getByRole('button', {name: 'Aceitar e enviar à gerência', exact: true}).click();
     await confirmation.waitFor(); await confirmation.getByRole('button', {name: 'Confirmar aceite', exact: true}).click();
     await confirmation.waitFor({state: 'detached'});
    }
    await page.waitForLoadState('networkidle'); expectedTransportError = false;
    report.checks.push({width, interruption: kind, passed: true});
   }
  }
  await context.close();
 }
 assert.deepEqual(report.errors, []);
} finally {await browser.close(); if (out) writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 2));}
console.log(JSON.stringify(report));
