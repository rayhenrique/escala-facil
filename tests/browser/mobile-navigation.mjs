// Browser plugin unavailable; only disposable QA accounts may be used.
import assert from 'node:assert/strict';
import {mkdirSync,writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
const {chromium}=await import(process.env.PLAYWRIGHT_MODULE?pathToFileURL(process.env.PLAYWRIGHT_MODULE).href:'playwright');
const origin=process.env.MOBILE_NAV_URL??'http://127.0.0.1:8797';
assert(process.env.MOBILE_NAV_MANAGER && process.env.MOBILE_NAV_PROFESSIONAL && process.env.MOBILE_NAV_PASSWORD);
const out=process.env.MOBILE_NAV_SCREENSHOTS;if(out)mkdirSync(out,{recursive:true});
const browser=await chromium.launch({channel:process.env.PLAYWRIGHT_CHANNEL});const report={checks:[],errors:[]};
try {
 for(const role of ['manager','professional'])for(const width of [390,320,1440]) {
  const context=await browser.newContext({viewport:{width,height:900},reducedMotion:'reduce',serviceWorkers:'allow'});
  const page=await context.newPage();page.setDefaultTimeout(30000);
  page.on('pageerror',e=>report.errors.push(e.message));page.on('console',m=>{if(m.type()==='error'||/multiple instances/.test(m.text()))report.errors.push(m.text());});
  await page.goto(origin+'/login');await page.locator('#email').fill(role==='manager'?process.env.MOBILE_NAV_MANAGER:process.env.MOBILE_NAV_PROFESSIONAL);
  await page.locator('#password').fill(process.env.MOBILE_NAV_PASSWORD);await page.getByRole('button',{name:'Entrar',exact:true}).click();await page.waitForURL('**/'+role+'/dashboard');
  const announcement=page.getByRole('button',{name:'Entendi, continuar',exact:true});if(await announcement.isVisible()){await announcement.click();await announcement.waitFor({state:'detached'});}
  const bar=page.getByRole('navigation',{name:'Navegação móvel principal',exact:true});
  if(width>=1024){assert(!(await bar.isVisible()));assert(await page.locator('#primary-sidebar').isVisible());assert.equal(await page.locator('.mobile-navigation-space').evaluate(el=>getComputedStyle(el).paddingBottom),'0px');report.checks.push({role,width,phase:'desktop-preserved',passed:true});await context.close();continue;}
  assert(await bar.isVisible());assert(!(await page.locator('#primary-sidebar').isVisible()));
  assert.equal(await bar.locator(':scope > a, :scope > button').count(),4);
  for(const item of await bar.locator(':scope > a, :scope > button').all()){const box=await item.boundingBox();assert(box.height>=44&&box.width>=44);}
  assert.equal(await bar.locator('[aria-current="page"]').count(),1);
  if(role==='professional') {
   await page.locator('#month').fill('2026-11');
   await page.waitForURL(url=>url.searchParams.get('month')==='2026-11');
  }
  const secondary=role==='manager'?'Calendário':'Escala';
  await bar.getByRole('link',{name:secondary,exact:true}).click();
  if(role==='manager')await page.waitForURL('**/manager/calendar');else {await page.waitForURL('**#schedule-title');await page.waitForFunction(()=>document.activeElement?.id==='schedule-title');}
  await page.waitForLoadState('networkidle');assert.equal(await bar.getByRole('link',{name:secondary,exact:true}).getAttribute('aria-current'),'page');
  if(role==='professional') assert.equal(new URL(page.url()).searchParams.get('month'),'2026-11');
  await bar.getByRole('link',{name:/^Trocas/}).click();if(role==='manager')await page.waitForURL('**/manager/swaps');else {await page.waitForURL('**#my-swaps-title');await page.waitForFunction(()=>document.activeElement?.id==='my-swaps-title');}
  await page.waitForLoadState('networkidle');assert.equal(await bar.getByRole('link',{name:/^Trocas/}).getAttribute('aria-current'),'page');
  await page.reload();await page.waitForLoadState('networkidle');assert.equal(await bar.getByRole('link',{name:/^Trocas/}).getAttribute('aria-current'),'page');
  await page.evaluate(()=>scrollTo(0,document.documentElement.scrollHeight));
  const footer=await page.locator('.system-footer').boundingBox();const barBox=await bar.boundingBox();assert(footer.y+footer.height<=barBox.y+1);
  const menu=bar.getByRole('button',{name:'Abrir menu principal',exact:true});await menu.focus();await page.keyboard.press('Enter');
  const dialog=page.getByRole('dialog',{name:'Menu',exact:true});await dialog.waitFor({state:'visible'});
  assert(await dialog.evaluate(el=>el.contains(document.activeElement)));
  assert(await page.evaluate(()=>document.documentElement.style.overflow==='hidden'));
  for(let i=0;i<12;i++){await page.keyboard.press('Tab');assert(await dialog.evaluate(el=>el.contains(document.activeElement)));}
  assert.equal(await dialog.getByRole('link',{name:'Início',exact:true}).count(),0);assert.equal(await dialog.getByRole('link',{name:'Trocas',exact:true}).count(),0);
  if(role==='professional')for(const link of await dialog.getByRole('link').all())assert(!((await link.getAttribute('href'))??'').includes('/manager/'));
  if(process.env.AXE_MODULE){await page.addScriptTag({path:process.env.AXE_MODULE});const violations=await page.evaluate(async()=>(await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','best-practice']}})).violations);assert.deepEqual(violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.target)})),[]);}
  await dialog.evaluate(async el=>{let root=el;while(root&&!root.hasAttribute('wire:id'))root=root.parentElement;await window.Livewire.find(root.getAttribute('wire:id')).$refresh();});
  assert(await dialog.isVisible());assert(await dialog.evaluate(el=>el.contains(document.activeElement)));
  if(out)await page.screenshot({path:out+`/menu-${role}-${width}.png`});
  await page.keyboard.press('Escape');await dialog.waitFor({state:'hidden'});await page.waitForFunction(el=>el===document.activeElement,await menu.elementHandle());
  await menu.click();await dialog.getByRole('link',{name:'Relatórios',exact:true}).click();await page.waitForURL('**/'+role+'/reports');await page.waitForLoadState('networkidle');
  assert(!(await dialog.isVisible()));assert.equal(await menu.getAttribute('aria-current'),'page');
  // A profile's reports subview remains under Menu after a Livewire update.
  await page.goto(origin+'/'+role+'/reports?tab=swaps');await page.waitForLoadState('networkidle');assert.equal(await menu.getAttribute('aria-current'),'page');
  if(role==='manager') {
   await menu.click();await dialog.getByRole('link',{name:'Setores',exact:true}).click();await page.waitForURL('**/manager/sectors');
   await page.getByRole('button',{name:'Adicionar setor',exact:true}).click();const form=page.getByRole('dialog',{name:'Adicionar setor',exact:true});await form.waitFor({state:'visible'});
   await page.setViewportSize({width,height:300});await page.locator('#sector-name').focus();await page.waitForFunction(()=>document.body.classList.contains('mobile-keyboard-open'));assert(!(await bar.isVisible()));
   const footerBox=await form.locator('footer').first().boundingBox();assert(footerBox.y+footerBox.height<=301);
   await page.setViewportSize({width,height:900});await page.keyboard.press('Escape');await form.waitFor({state:'detached'});assert(await bar.isVisible());
  }
  if(out)await page.screenshot({path:out+`/bar-${role}-${width}.png`});
  await menu.click();await dialog.waitFor({state:'visible'});await page.setViewportSize({width:1440,height:900});await dialog.waitFor({state:'hidden'});assert(await page.evaluate(()=>document.documentElement.style.overflow!=='hidden'));
  await page.setViewportSize({width,height:900});await menu.click();await dialog.getByRole('button',{name:'Sair',exact:true}).click();await page.waitForURL('**/login');assert.equal(await bar.count(),0);
  await page.locator('#email').fill(role==='manager'?process.env.MOBILE_NAV_MANAGER:process.env.MOBILE_NAV_PROFESSIONAL);await page.locator('#password').fill(process.env.MOBILE_NAV_PASSWORD);await page.getByRole('button',{name:'Entrar',exact:true}).click();await page.waitForURL('**/'+role+'/dashboard');
  await context.clearCookies();await page.getByRole('navigation',{name:'Navegação móvel principal'}).getByRole('link',{name:role==='manager'?'Calendário':'Escala',exact:true}).click();
  // Same-page hash links do not consult the session; a protected module must.
  if(role==='professional')await page.goto(origin+'/professional/calendar');
  await page.waitForURL('**/login');assert.equal(await page.getByRole('navigation',{name:'Navegação móvel principal'}).count(),0);
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));report.checks.push({role,width,phase:'routes-menu-focus-keyboard-safe-space-logout-expired',passed:true});await context.close();
 }
 assert.deepEqual(report.errors,[]);
} finally{await browser.close();if(out)writeFileSync(out+'/report.json',JSON.stringify(report,null,2));}
console.log(JSON.stringify(report));
