// Run with disposable QA accounts only. Browser plugin unavailable: Playwright fallback.
import assert from 'node:assert/strict';
import {mkdirSync, writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert(process.env.PROFESSIONAL_HOME_EMAIL && process.env.PROFESSIONAL_HOME_PASSWORD);
const browser = await chromium.launch({channel: process.env.PLAYWRIGHT_CHANNEL});
const origin = process.env.PROFESSIONAL_HOME_URL ?? 'http://127.0.0.1:8797';
const out = process.env.PROFESSIONAL_HOME_SCREENSHOTS;
if (out) mkdirSync(out, {recursive: true});
const report = {checks: [], errors: []};
try {
 for (const width of [320, 390, 1440]) {
  const context = await browser.newContext({viewport: {width, height: 900}, reducedMotion: 'reduce', serviceWorkers: 'allow'});
  const page = await context.newPage();
  page.on('pageerror', e => report.errors.push(e.message));
  page.on('console', m => {if (m.type() === 'error' || /multiple instances/.test(m.text())) report.errors.push(m.text());});
  await page.goto(origin + '/login');
  await page.waitForLoadState('networkidle');
  await page.locator('#email').fill(process.env.PROFESSIONAL_HOME_EMAIL);
  await page.locator('#password').fill(process.env.PROFESSIONAL_HOME_PASSWORD);
  await page.getByRole('button', {name: 'Entrar', exact: true}).click();
  await page.waitForURL('**/professional/dashboard');
  const announcement = page.getByRole('button', {name: 'Entendi, continuar', exact: true});
  if (await announcement.isVisible()) {await announcement.click(); await announcement.waitFor({state: 'detached'});}
  const overview = page.locator('.professional-next');
  if (width >= 1024) {
   assert(!(await overview.isVisible()));
   assert(await page.locator('#primary-sidebar').isVisible());
   const filters = await page.locator('.professional-filters').boundingBox();
   const schedule = await page.locator('.professional-schedule').boundingBox();
   assert(filters.y < schedule.y);
  } else {
   assert(await overview.isVisible());
   const text = await overview.innerText();
   const filters = await page.locator('.professional-filters').boundingBox();
   assert((await overview.boundingBox()).y < filters.y);
   for (const label of ['Início', 'Término', 'Duração', 'Setor', 'Categoria']) assert(text.includes(label));
   const details = overview.getByRole('button', {name: 'Ver detalhes', exact: true});
   await details.focus(); await page.keyboard.press('Enter');
   const dialog = page.getByRole('dialog', {name: 'Detalhes do seu plantão', exact: true});
   await dialog.waitFor({state: 'visible'});
   assert.equal(await dialog.getAttribute('aria-modal'), 'true');
   await page.waitForFunction(() => [...document.querySelectorAll('[role=dialog]')].some(el => el.offsetParent !== null && el.contains(document.activeElement)));
   for (let i = 0; i < 6; i++) {await page.keyboard.press('Tab'); assert(await dialog.evaluate(el => el.contains(document.activeElement)));}
   await page.keyboard.press('Escape'); await dialog.waitFor({state: 'detached'});
   await page.waitForFunction(() => document.activeElement?.id === 'professional-next-details');
   const request = overview.getByRole('button', {name: 'Solicitar troca', exact: true});
   if (await request.isVisible()) {
    await request.click();
    await page.waitForFunction(() => document.activeElement?.id === 'swap-form-title');
    await page.getByRole('dialog', {name: 'Solicitar troca', exact: true}).getByRole('button', {name: 'Cancelar', exact: true}).click();
    await page.locator('#swap-form-title').waitFor({state: 'detached'});
   }
   const received = page.locator('section[aria-labelledby="incoming-title"]');
   if (await received.isVisible()) {
    assert((await received.boundingBox()).y < (await page.locator('.professional-filters').boundingBox()).y);
    const pagination = received.getByRole('navigation', {name: 'Paginação dos pedidos recebidos', exact: true});
    if (await pagination.isVisible()) {
     await pagination.getByRole('button', {name: 'Próxima', exact: true}).click();
     await page.waitForFunction(() => document.querySelector('section[aria-labelledby="incoming-title"] nav')?.textContent.includes('Página 2'));
     assert((await received.locator('article').count()) <= 10);
     await pagination.getByRole('button', {name: 'Anterior', exact: true}).click();
     await page.waitForFunction(() => document.querySelector('section[aria-labelledby="incoming-title"] nav')?.textContent.includes('Página 1'));
    }
    await received.getByRole('button', {name: 'Aceitar', exact: true}).first().click();
    const confirmation = page.getByRole('alertdialog', {name: 'Aceitar cobertura deste plantão?', exact: true});
    await confirmation.waitFor({state: 'visible'});
    await confirmation.getByRole('button', {name: 'Voltar e revisar', exact: true}).click();
    await confirmation.waitFor({state: 'detached'});
    const responseDetails = page.getByRole('dialog', {name: 'Detalhes do pedido recebido', exact: true});
    await responseDetails.waitFor({state: 'visible'});
    await responseDetails.getByRole('button', {name: 'Fechar', exact: true}).click();
    await responseDetails.waitFor({state: 'detached'});
   }
   const before = await overview.innerText();
   await page.locator('#month').fill('2026-09');
   await page.waitForURL(url => url.searchParams.get('month') === '2026-09');
   assert.equal(await overview.innerText(), before);
   await overview.getByRole('link', {name: 'Ver escala', exact: true}).click();
   await page.waitForFunction(() => document.activeElement?.id === 'schedule-title');
   await page.reload(); await page.waitForLoadState('networkidle');
   assert.equal(await page.locator('#month').inputValue(), '2026-09');
  }
  await page.locator('#nprogress').waitFor({state: 'detached'});
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  if (process.env.AXE_MODULE) {
   await page.addScriptTag({path: process.env.AXE_MODULE});
   const violations = await page.evaluate(async () => (await axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa']}})).violations);
   assert.deepEqual(violations.map(v => ({id: v.id, nodes: v.nodes.map(n => n.target)})), []);
  }
  if (out) await page.screenshot({path: `${out}/professional-${width}.png`, fullPage: true});
  report.checks.push({width, passed: true}); await context.close();
 }
 assert.deepEqual(report.errors, []);
} finally {await browser.close(); if (out) writeFileSync(out + '/report.json', JSON.stringify(report, null, 2));}
console.log(JSON.stringify(report));
