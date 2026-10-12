// Browser plugin unavailable: use Playwright against disposable synthetic fixtures only.
// This suite validates review/cancellation and never submits a nonempty reason.
import {pathToFileURL} from 'node:url';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
import fs from 'node:fs';
import assert from 'node:assert/strict';
assert.ok(process.env.SWAP_REVERSION_FIXTURE && process.env.SWAP_REVERSION_QA_PASSWORD, 'Use fixtures aprovadas por Action em um banco de teste descartável.');
const root=process.env.SWAP_REVERSION_SCREENSHOTS;
if(root) fs.mkdirSync(root,{recursive:true});
const fixtures=JSON.parse(fs.readFileSync(process.env.SWAP_REVERSION_FIXTURE,'utf8'));
const origin=process.env.SWAP_REVERSION_URL ?? 'http://127.0.0.1:8796';
const browser=await chromium.launch({channel:process.env.PLAYWRIGHT_CHANNEL,headless:true});
const report={fallback:'Browser plugin unavailable; terminal Playwright with local synthetic SQLite only.',checks:[],errors:[]};
try {
 for(const width of [1440,390,320]) {
  const context=await browser.newContext({viewport:{width,height:900},serviceWorkers:width===1440?'allow':'block',reducedMotion:'reduce'});
  const page=await context.newPage(); page.setDefaultTimeout(30000); page.setDefaultNavigationTimeout(60000);
  page.on('pageerror',e=>report.errors.push(e.message));
  page.on('console',m=>{if(m.type()==='error'||/multiple instances/.test(m.text())) report.errors.push(m.text())});
  await page.goto(origin+'/login',{waitUntil:'networkidle'});
  await page.getByLabel('E-mail',{exact:true}).fill(fixtures.managerEmail ?? 'reversion-qa@example.test');
  await page.getByLabel('Senha',{exact:true}).fill(process.env.SWAP_REVERSION_QA_PASSWORD);
  await page.getByRole('button',{name:'Entrar',exact:true}).click(); await page.waitForURL('**/manager/dashboard');
  await page.goto(origin+'/manager/swaps?listView=history');
  await page.locator(`button[wire\\:click="requestRevert(${fixtures.mutual.id})"]`).click();
  const dialog=page.getByRole('alertdialog'); await dialog.waitFor();
  assert.match(await dialog.innerText(),/Situação atual → Situação após desfazer/);
  assert.match(await dialog.innerText(),/24h/);
  assert.equal(await dialog.locator('dl').count(),3);
  for(const item of await dialog.locator('dd').all()) assert.ok((await item.innerText()).trim());
  await dialog.getByRole('button',{name:'Confirmar desfazer',exact:true}).click();
  await dialog.locator('[aria-invalid="true"]').waitFor();
  await page.waitForFunction(()=>document.activeElement?.id==='reversion-reason',null,{timeout:15000});
  await dialog.getByLabel('Justificativa administrativa').fill('Revisão administrativa em teste de interface.');
  if(process.env.AXE_MODULE) {
   await page.addScriptTag({path:process.env.AXE_MODULE});
   const axe=await page.evaluate(()=>axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','best-practice']}}));
   const violations=axe.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)}));
   report.checks.push({width,phase:'preview',violations});
   assert.equal(violations.length,0,JSON.stringify(violations));
  }
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  if(root) await page.screenshot({path:root+`/preview-${width}.png`,fullPage:true});
  // Tab focus stays in the modal; Escape and cancellation remain usable.
  for(let n=0;n<8;n++) {await page.keyboard.press('Tab'); assert(await page.evaluate(()=>!!document.activeElement?.closest('[role="alertdialog"]')));}
  await page.keyboard.press('Escape'); await dialog.getByRole('button',{name:'Descartar alterações'}).click(); await dialog.waitFor({state:'detached'});
  await page.waitForFunction(id=>document.activeElement?.getAttribute('wire:click')===`requestRevert(${id})`,fixtures.mutual.id,{timeout:15000});
  await page.locator(`button[wire\\:click="requestRevert(${fixtures.blocked.id})"]`).click(); await dialog.waitFor();
  assert.equal(await dialog.getByRole('button',{name:'Confirmar desfazer',exact:true}).count(),0);
  await dialog.getByRole('button',{name:'Analisar correção administrativa',exact:true}).click();
  await dialog.getByText('Conferência administrativa separada',{exact:true}).waitFor();
  const link=dialog.getByRole('link',{name:'Histórico do plantão #'+fixtures.blocked.counter,exact:true});
  const destination=await link.getAttribute('href'); assert.match(destination,/entity=shift/); assert.match(destination,/from=swaps/);
  if(root) await page.screenshot({path:root+`/administrative-${width}.png`,fullPage:true});
  await dialog.getByRole('button',{name:'Manter troca',exact:true}).click(); await dialog.waitFor({state:'detached'});
  report.checks.push({width,phase:'blocked-analysis-keyboard-cancel',passed:true});
  await context.close();
 }
 assert.equal(report.errors.length,0,JSON.stringify(report.errors));
} finally { await browser.close(); if(root) fs.writeFileSync(root+'/browser-report.json',JSON.stringify(report,null,2)); }
console.log(JSON.stringify(report));
