// Browser plugin unavailable: Playwright fallback; disposable QA accounts only.
import assert from 'node:assert/strict';
import {mkdirSync, writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert(process.env.CALENDAR_MANAGER && process.env.CALENDAR_PROFESSIONAL && process.env.CALENDAR_PASSWORD);
const origin = process.env.CALENDAR_URL ?? 'http://127.0.0.1:8797';
const out = process.env.CALENDAR_SCREENSHOTS; if (out) mkdirSync(out, {recursive: true});
const browser = await chromium.launch({channel: process.env.PLAYWRIGHT_CHANNEL});
const report = {checks: [], errors: []};
try {
 for (const role of ['manager', 'professional']) for (const width of [320, 390, 1440]) {
  const context = await browser.newContext({viewport: {width, height: 900}, reducedMotion: 'reduce', serviceWorkers: 'allow', hasTouch: width < 1024});
  const page = await context.newPage();
  page.on('pageerror', e => report.errors.push(e.message));
  page.on('console', m => {if (m.type() === 'error' || /multiple instances/.test(m.text())) report.errors.push(m.text());});
  await page.goto(origin + '/login');
  await page.locator('#email').fill(role === 'manager' ? process.env.CALENDAR_MANAGER : process.env.CALENDAR_PROFESSIONAL);
  await page.locator('#password').fill(process.env.CALENDAR_PASSWORD);
  await page.getByRole('button', {name: 'Entrar', exact: true}).click(); await page.waitForURL('**/' + role + '/dashboard');
  const announcement = page.getByRole('button', {name: 'Entendi, continuar', exact: true});
  if (await announcement.isVisible()) {await announcement.click(); await announcement.waitFor({state: 'detached'});}
  await page.goto(origin + '/' + role + '/calendar?month=2026-11&selectedDate=2026-11-30');
  await page.waitForLoadState('networkidle');
  const grid = page.getByRole('grid');
  const agenda = page.locator('section[aria-labelledby="agenda-title"]');
  if (width >= 1024) {
   assert(await grid.isVisible()); assert(!(await agenda.isVisible()));
   assert(await page.locator('section[aria-labelledby="selected-day-title"]').isVisible());
   await page.getByRole('button', {name: 'Próximo mês', exact: true}).click();
   await page.waitForURL(url => url.searchParams.get('month') === '2026-12');
  } else {
   assert(!(await grid.isVisible())); assert(await agenda.isVisible());
   const filtersButton = page.getByRole('button', {name: 'Filtrar por setor e categoria', exact: true});
   assert.equal(await filtersButton.getAttribute('aria-expanded'), 'false');
   assert(!(await page.locator('#calendar-category').isVisible()));
   const pagination = agenda.getByRole('navigation', {name: 'Paginação da agenda', exact: true});
   if (await pagination.isVisible()) {
    await pagination.getByRole('button', {name: 'Próxima', exact: true}).click();
    await page.waitForURL(url => url.searchParams.get('agendaPage') === '2');
    await page.waitForLoadState('networkidle');
    assert((await agenda.locator('article').count()) <= 25);
   }
   assert((await agenda.innerText()).includes('dia seguinte, 01/12/2026'));
   assert((await agenda.innerText()).includes('dia seguinte, 02/12/2026'));
   assert((await agenda.innerText()).includes('24h'));
   const card = agenda.locator('article').filter({has: page.getByRole('heading', {name: /19:00–07:00/})}).first();
   const details = card.getByRole('button', {name: /Ver detalhes do plantão/}); const triggerId = await details.getAttribute('id');
   await details.focus(); await page.keyboard.press('Enter');
   const dialog = page.getByRole('dialog', {name: 'Detalhes do plantão', exact: true}); await dialog.waitFor({state: 'visible'});
   assert.equal(await dialog.getAttribute('aria-modal'), 'true');
   await page.waitForFunction(() => document.getElementById('calendar-shift-detail')?.contains(document.activeElement));
   for (let i = 0; i < 6; i++) {await page.keyboard.press('Tab'); assert(await dialog.evaluate(el => el.contains(document.activeElement)));}
   if (role === 'professional') {assert.equal(await dialog.getByRole('link', {name: 'Abrir escala', exact: true}).count(), 0); assert(await dialog.getByRole('link', {name: 'Ver minha escala', exact: true}).isVisible(), await dialog.innerText());}
   await page.keyboard.press('Escape'); await dialog.waitFor({state: 'detached'}); await page.waitForFunction(id => document.activeElement?.id === id, triggerId);
   await card.getByRole('heading', {name: /19:00–07:00/}).tap();
   await dialog.waitFor({state: 'visible'});
   await dialog.getByRole('button', {name: 'Fechar', exact: true}).click();
   await dialog.waitFor({state: 'detached'});
   if (role === 'manager') {
    const uncovered = agenda.locator('article').filter({hasText: 'Precisa de cobertura'}).first();
    await uncovered.getByRole('button', {name: /Ver detalhes do plantão/}).click(); await dialog.waitFor({state: 'visible'});
    await dialog.getByRole('button', {name: 'Preencher plantão', exact: true}).click(); await dialog.waitFor({state: 'detached'});
    const reallocation = page.getByRole('alertdialog', {name: /^Preencher plantão #/}); await reallocation.waitFor({state: 'visible'});
    assert.equal(await page.getByRole('alertdialog').count(), 1); await page.keyboard.press('Escape'); await reallocation.waitFor({state: 'detached'});
   }
   const modes = page.getByRole('group', {name: 'Visualização da agenda', exact: true});
   await modes.getByRole('button', {name: 'Mês', exact: true}).click(); await page.waitForURL(url => url.searchParams.get('agendaView') === 'month'); await page.waitForFunction(() => document.getElementById('agenda-title')?.textContent === 'Agenda do mês');
   assert(!(await agenda.innerText()).includes('dia seguinte, 02/12/2026')); // December starts outside November.
   await modes.getByRole('button', {name: 'Lista', exact: true}).click(); await page.waitForURL(url => url.searchParams.get('agendaView') === 'list'); await page.waitForFunction(() => document.getElementById('agenda-title')?.textContent === 'Lista de plantões');
   assert((await agenda.innerText()).includes('29/12/2026'));
   await page.locator('#agenda-date').fill('2026-10-21');
   await page.waitForURL(url => url.searchParams.get('selectedDate') === '2026-10-21'); await page.waitForLoadState('networkidle');
   assert((await agenda.innerText()).includes('Troca realizada'));
   if (!(await page.locator('#calendar-category').isVisible())) await page.getByRole('button', {name: 'Filtrar por setor e categoria', exact: true}).click();
   await page.locator('#calendar-category').selectOption({label: 'Enfermagem QA'});
   await page.waitForURL(url => !!url.searchParams.get('categoryId')); const category = new URL(page.url()).searchParams.get('categoryId');
   await page.getByRole('button', {name: 'Próximo período', exact: true}).click();
   await page.waitForURL(url => url.searchParams.get('selectedDate') === '2026-11-20');
   assert.equal(new URL(page.url()).searchParams.get('categoryId'), category);
   await page.reload(); await page.waitForLoadState('networkidle');
   assert.equal(await page.locator('#agenda-date').inputValue(), '2026-11-20');
   assert.equal(await modes.getByRole('button', {name: 'Lista', exact: true}).getAttribute('aria-pressed'), 'true');
   const nextShift = page.getByRole('button', {name: 'Ir ao próximo plantão', exact: true});
   if (await nextShift.isVisible()) {
    await nextShift.click();
    await agenda.getByText(role === 'professional' ? 'Seu próximo plantão' : 'Próximo plantão', {exact: true}).waitFor({state: 'visible'});
    await page.waitForFunction(() => document.activeElement?.id.startsWith('calendar-agenda-shift-'));
    assert.equal(new URL(page.url()).searchParams.get('categoryId'), category);
    assert.equal(await modes.getByRole('button', {name: 'Lista', exact: true}).getAttribute('aria-pressed'), 'true');
   }
  }
  await page.locator('#nprogress').waitFor({state: 'detached'});
  await page.getByText('Atualizando agenda…', {exact: true}).waitFor({state: 'hidden'});
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  if (process.env.AXE_MODULE) {
   await page.addScriptTag({path: process.env.AXE_MODULE});
   const violations = await page.evaluate(async () => (await axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa']}})).violations);
   assert.deepEqual(violations.map(v => ({id: v.id, nodes: v.nodes.map(n => n.target)})), []);
  }
  if (out) await page.screenshot({path: `${out}/${role}-${width}.png`, fullPage: true});
  report.checks.push({role, width, passed: true}); await context.close();
 }
 assert.deepEqual(report.errors, []);
} finally {await browser.close(); if (out) writeFileSync(out + '/report.json', JSON.stringify(report, null, 2));}
console.log(JSON.stringify(report));
