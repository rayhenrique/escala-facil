// Browser plugin unavailable. Uses Playwright and a disposable local database.
import assert from 'node:assert/strict';
import { mkdirSync,writeFileSync } from 'node:fs';
import {pathToFileURL} from 'node:url';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
const origin=process.env.MOBILE_UI_URL ?? 'http://127.0.0.1:8797';
assert(process.env.MOBILE_UI_EMAIL && process.env.MOBILE_UI_PASSWORD);
const out=process.env.MOBILE_UI_SCREENSHOTS;if(out)mkdirSync(out,{recursive:true});
const browser=await chromium.launch({channel:process.env.PLAYWRIGHT_CHANNEL});
const report={checks:[],errors:[]};
try {
 for(const width of [1440,390,320]) {
  const context=await browser.newContext({viewport:{width,height:900},reducedMotion:width===1440?'no-preference':'reduce',serviceWorkers:width===1440?'allow':'block'});
  const page=await context.newPage();page.setDefaultTimeout(30000);
  page.on('pageerror',e=>report.errors.push(e.message));page.on('console',m=>{if(m.type()==='error'||/multiple instances/.test(m.text()))report.errors.push(m.text());});
  await page.goto(origin+'/login');await page.locator('#email').fill(process.env.MOBILE_UI_EMAIL);await page.locator('#password').fill(process.env.MOBILE_UI_PASSWORD);
  await page.getByRole('button',{name:'Entrar',exact:true}).click();await page.waitForURL('**/manager/dashboard');
  const announcement=page.getByRole('button',{name:'Entendi, continuar',exact:true});
  if(await announcement.isVisible())await announcement.click();
  await page.goto(origin+'/manager/sectors');await page.waitForLoadState('networkidle');
  const open=page.getByRole('button',{name:'Adicionar setor',exact:true});await open.click();
  const dialog=page.getByRole('dialog');await dialog.waitFor();
  const field=page.locator('#sector-name');assert(await field.evaluate(el=>el===document.activeElement));
  assert.equal(await dialog.getAttribute('aria-modal'),'true');
  assert(await page.evaluate(()=>document.documentElement.style.overflow==='hidden'));
  for(let i=0;i<12;i++){await page.keyboard.press(i%2?'Shift+Tab':'Tab');assert(await dialog.evaluate(el=>el.contains(document.activeElement)));}
  await page.keyboard.press('Escape');await dialog.waitFor({state:'detached'});await page.waitForFunction(el=>el===document.activeElement,await open.elementHandle());
  assert(await page.evaluate(()=>document.documentElement.style.overflow!=='hidden'));
  await open.click();await field.waitFor();await field.fill('Não descartar silenciosamente');await field.press('Escape');
  await page.getByText('Há alterações não salvas. Deseja descartá-las?',{exact:true}).waitFor({state:'visible'});
  assert(await page.getByRole('button',{name:'Continuar editando',exact:true}).evaluate(el=>el===document.activeElement));
  await page.getByRole('button',{name:'Continuar editando',exact:true}).click();assert.equal(await field.inputValue(),'Não descartar silenciosamente');
  await page.locator('.modal-backdrop').click({position:{x:1,y:1}});
  await page.getByRole('button',{name:'Descartar alterações',exact:true}).click();await dialog.waitFor({state:'detached'});
  await open.click();await field.waitFor();await field.fill('');await page.getByRole('button',{name:'Criar setor',exact:true}).click();
  await page.locator('#sector-name[aria-invalid="true"]').waitFor({state:'visible'});
  await page.locator('#sector-name-error').waitFor({state:'visible'});
  assert(await field.evaluate(el=>el===document.activeElement));
  if(width<1024) {
   await page.setViewportSize({width,height:300});
   const body=dialog.locator('.modal-body');const footer=dialog.locator('footer').first();
   const box=await footer.boundingBox();assert(box.y>=0&&box.y+box.height<=301);
   assert.equal(await body.evaluate(el=>getComputedStyle(el).overflowY),'auto');
   assert(await body.evaluate(el=>el.scrollHeight>el.clientHeight));
   await body.evaluate(el=>el.scrollTop=el.scrollHeight);
   assert(await page.getByRole('button',{name:'Criar setor',exact:true}).isVisible());
   await page.setViewportSize({width,height:900});
  }
  if(process.env.AXE_MODULE){await page.addScriptTag({path:process.env.AXE_MODULE});const violations=await page.evaluate(async()=>(await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','best-practice']}})).violations);assert.deepEqual(violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[]);}
  if(out)await page.screenshot({path:out+`/form-${width}.png`});
  const name=`Componente móvel QA ${width} ${Date.now()}`;
  await field.fill(name);await page.getByRole('button',{name:'Criar setor',exact:true}).click();await dialog.waitFor({state:'detached'});
  const toast=page.getByRole('status').filter({hasText:'Setor criado com sucesso.'});await toast.waitFor({state:'visible'});
  const dismiss=page.getByRole('button',{name:'Fechar mensagem de sucesso',exact:true});await dismiss.focus();
  await page.waitForTimeout(5800);assert(await toast.isVisible());await dismiss.click();await toast.waitFor({state:'hidden'});
  await page.locator('#sector-search').fill(name);await page.waitForTimeout(600);
  const record=width>=768?page.locator('tr').filter({hasText:name}):page.locator('.record-card').filter({hasText:name});
  await record.getByRole('button',{name:'Excluir',exact:true}).click();
  const confirm=page.getByRole('alertdialog');await confirm.waitFor();assert(await page.getByRole('button',{name:'Manter setor',exact:true}).evaluate(el=>el===document.activeElement));
  await page.keyboard.press('Escape');await confirm.waitFor({state:'detached'});
  await record.getByRole('button',{name:'Excluir',exact:true}).click();await confirm.waitFor();
  await page.getByRole('button',{name:'Excluir setor',exact:true}).click();await confirm.waitFor({state:'detached'});
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  // A Livewire navigation must reuse the registered interaction library.
  await page.goto(origin+'/manager/dashboard');if(width<1024)await page.getByRole('button',{name:'Abrir menu principal',exact:true}).click();
  await page.locator('a[href="'+origin+'/manager/sectors"][wire\\:navigate]').filter({visible:true}).first().click();await page.waitForURL('**/manager/sectors');
  await page.getByRole('button',{name:'Adicionar setor',exact:true}).click();await dialog.waitFor();await page.keyboard.press('Escape');await dialog.waitFor({state:'detached'});
  report.checks.push({width,passed:true});await context.close();
 }
 assert.deepEqual(report.errors,[]);
} finally {await browser.close();if(out)writeFileSync(out+'/report.json',JSON.stringify(report,null,2));}
console.log(JSON.stringify(report));
