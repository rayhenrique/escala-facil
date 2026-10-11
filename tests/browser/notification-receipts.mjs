// Browser plugin unavailable; Playwright QA uses disposable synthetic notifications only.
import assert from 'node:assert/strict';
import {readFileSync,mkdirSync,writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
assert.ok(process.env.NOTIFICATION_RECEIPTS_FIXTURE && process.env.NOTIFICATION_RECEIPTS_QA_PASSWORD);
const fixture=JSON.parse(readFileSync(process.env.NOTIFICATION_RECEIPTS_FIXTURE,'utf8').replace(/^\uFEFF/,''));
const origin=process.env.NOTIFICATION_RECEIPTS_URL ?? 'http://127.0.0.1:8797';
const out=process.env.NOTIFICATION_RECEIPTS_SCREENSHOTS;
if(out) mkdirSync(out,{recursive:true});
const browser=await chromium.launch({channel:process.env.PLAYWRIGHT_CHANNEL});
const report={checks:[],errors:[]};
try {
 for(const width of [1440,390,320]) {
  const context=await browser.newContext({viewport:{width,height:900},reducedMotion:'reduce',serviceWorkers:width===1440?'allow':'block'});
  const page=await context.newPage(); page.setDefaultTimeout(30000); page.setDefaultNavigationTimeout(60000);
  page.on('pageerror',e=>report.errors.push(e.message));
  page.on('console',m=>{if(m.type()==='error'||/multiple instances/.test(m.text())) report.errors.push(m.text());});
  await page.goto(origin+'/login'); await page.locator('#email').fill(fixture.managerEmail);
  await page.locator('#password').fill(process.env.NOTIFICATION_RECEIPTS_QA_PASSWORD);
  await page.getByRole('button',{name:'Entrar',exact:true}).click(); await page.waitForURL('**/manager/dashboard');
  await page.goto(origin+'/notifications'); await page.waitForLoadState('networkidle');
  const announcement=page.getByRole('button',{name:'Entendi, continuar',exact:true});
  if(await announcement.isVisible()) {await announcement.click(); await announcement.waitFor({state:'detached'});}
  // Consult both pages without marking notifications or performing a decision.
  const seen=new Set(); let card;
  for(const number of [1,2]) {
   if(number===2) {await page.goto(origin+'/notifications?page=2'); await page.waitForLoadState('networkidle');}
   for(const item of await page.locator('[data-notification-swap]').all()) {
    const id=await item.getAttribute('data-notification-swap'); seen.add(id);
    assert.match(await item.locator('[data-current-swap-status]').innerText(),new RegExp('Situação atual da troca: '+fixture.statuses[id]));
    assert.equal(await item.locator('[data-receipt-kind="historical"]').count(),1);
    assert.equal(await item.locator('[data-recorded-swap-status]').innerText(),'Aguardando gerência');
    assert.equal(await item.locator('.swap-receipt .status-pending_manager').count(),0);
   }
   if(await page.locator('[data-notification-swap="106"]').count()) {
    card=page.locator('[data-notification-swap="106"]');
    assert.match(await card.innerText(),/Situação atual da troca: Aprovada/);
    assert.match(await card.innerText(),/Evento histórico desta notificação/i);
    assert.match(await card.innerText(),/Situação no momento deste registro/i);
    assert.match(await card.innerText(),/Solicitação aceita/);
    const pdf=card.getByRole('link',{name:'PDF oficial atualizado',exact:true});
    await pdf.focus(); assert(await pdf.evaluate(el=>el===document.activeElement));
    const response=await page.request.get(await pdf.getAttribute('href'));
    assert.equal(response.status(),200); assert.match(response.headers()['content-type'],/application\/pdf/);
    if(out) {writeFileSync(out+'/official-106.pdf',await response.body()); await card.screenshot({path:out+`/notification-106-${width}.png`});}
    if(process.env.AXE_MODULE) {
     await page.addScriptTag({path:process.env.AXE_MODULE});
     const violations=await page.evaluate(async()=> (await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','best-practice']}})).violations);
     assert.deepEqual(violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[]);
    }
    // Suppress the native print dialog, inspect the exact iframe sent to printing.
    await page.evaluate(()=> {const print=window.printSwapReceipt; window.printSwapReceipt=el=> {print(el); document.getElementById('receipt-print-iframe').contentWindow.print=()=>{};};});
    await card.getByRole('button',{name:'Imprimir registro histórico',exact:true}).click();
    const frame=page.frameLocator('#receipt-print-iframe');
    await frame.getByText('Comprovante histórico da notificação',{exact:true}).first().waitFor({state:'attached'});
    assert.match(await frame.locator('body').innerText(),/Situação no momento deste registro/i);
    assert.match(await frame.locator('body').innerText(),/Aguardando gerência/);
    assert.equal(await frame.getByText('Situação atual da troca: Aprovada',{exact:true}).count(),0);
    assert.equal(await frame.locator('button,a').count(),0);
    assert.equal(await frame.locator('script').count(),0);
    const html=await page.locator('#receipt-print-iframe').evaluate(el=>el.contentDocument.documentElement.outerHTML);
    if(out) writeFileSync(out+'/historical-print.html',html);
    await page.locator('#receipt-print-iframe').evaluate(el=>el.remove());
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    report.checks.push({width,phase:'106-current-history-pdf-print-keyboard-axe',passed:true});
   }
  }
  assert.equal(seen.size,Object.keys(fixture.statuses).length);
  assert(seen.has('106')); assert(seen.has('107')); assert(seen.has('108'));
  await page.reload(); await page.waitForLoadState('networkidle');
  report.checks.push({width,phase:'pagination-refresh',passed:true});
  await context.close();
 }
 assert.deepEqual(report.errors,[]);
} finally {await browser.close(); if(out) writeFileSync(out+'/browser-report.json',JSON.stringify(report,null,2));}
console.log(JSON.stringify(report));
