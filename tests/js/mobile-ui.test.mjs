import test from 'node:test';
import assert from 'node:assert/strict';
import { actionDialog, toastMessage, registerMobileUi, mobileNavigation } from '../../resources/js/mobile-ui.js';

function dialogHarness() {
 const events=[];
 const field={id:'name',name:'name',type:'text',value:'Original',disabled:false,focus:()=>events.push('field-focus')};
 const origin={isConnected:true,focus:()=>events.push('return-focus')};
 globalThis.document={activeElement:origin,body:{}};
 globalThis.requestAnimationFrame=fn=>fn();
 const ui=actionDialog({trackChanges:true,close:()=>events.push('close')});
 ui.$el={querySelectorAll:()=>[field],querySelector:()=>field,contains:()=>false};
 ui.$refs={keepEditing:{focus:()=>events.push('keep-focus')}};ui.$nextTick=fn=>fn();ui.init();
 return {ui,field,origin,events};
}

test('dirty forms require explicit discard and reverting changes removes the guard',()=>{
 const {ui,field,events}=dialogHarness();
 field.value='Edited';ui.changed();ui.requestClose();
 assert.equal(ui.discardRequested,true);assert(!events.includes('close'));assert(events.includes('keep-focus'));
 ui.keepEditing();assert.equal(ui.discardRequested,false);assert.equal(field.value,'Edited');
 ui.busy=true;ui.discard();assert(!events.includes('close'));
 ui.busy=false;ui.discard();assert.equal(events.at(-1),'close');
 field.value='Original';ui.changed();assert.equal(ui.dirty,false);
});

test('busy dialog ignores dismissal and restores connected origin without stealing new focus',()=>{
 const {ui,events}=dialogHarness();
 ui.busy=true;ui.requestClose();assert(!events.includes('close'));
 ui.busy=false;ui.requestClose();assert.equal(events.at(-1),'close');
 document.activeElement=document.body;ui.destroy();assert.equal(events.at(-1),'return-focus');
 document.activeElement={};ui.destroy();assert.equal(events.filter(e=>e==='return-focus').length,1);
});

test('toast pauses on interaction, cleans up timers and errors never auto-dismiss',()=>{
 const saved={setTimeout:globalThis.setTimeout,clearTimeout:globalThis.clearTimeout,now:Date.now};
 let callback,delay,time=0,cleared=0;
 globalThis.setTimeout=(fn,ms)=>{callback=fn;delay=ms;return 1;};globalThis.clearTimeout=()=>cleared++;Date.now=()=>time;
 globalThis.document={activeElement:null};
 try {
  const ui=toastMessage();ui.$el={matches:()=>false,contains:()=>false};ui.init();assert.equal(delay,5500);
  time=1500;ui.pause();assert.equal(ui.remaining,4000);ui.resume();assert.equal(delay,4000);
  ui.destroy();assert.equal(cleared,2);
  const error=toastMessage({duration:0});error.$el=ui.$el;error.init();assert.equal(error.timer,null);assert(error.visible);
  error.dismiss();assert.equal(error.visible,false);
  ui.resume();callback();assert.equal(ui.visible,false);
 } finally {globalThis.setTimeout=saved.setTimeout;globalThis.clearTimeout=saved.clearTimeout;Date.now=saved.now;}
});

test('registers once and overlapping Livewire requests keep the dialog busy until all finish',()=>{
 const events=[],hooks=[],data=[];
 let intercept;
 const livewire={interceptMessage:cb=>intercept=cb};const alpine={data:(name)=>data.push(name)};
 registerMobileUi(livewire,alpine);registerMobileUi(livewire,alpine);assert.deepEqual(data,['actionDialog','toastMessage','mobileNavigation']);
 const node={dispatchEvent:e=>events.push(e.detail)};
 const component={el:{querySelector:()=>node}};
 for(let i=0;i<2;i++) {
  const h={};intercept({message:{component},onSend:cb=>h.send=cb,onFinish:cb=>h.finish=cb,onSuccess:()=>{},onFailure:()=>{}});hooks.push(h);
 }
 hooks[0].send();hooks[1].send();hooks[0].finish();assert.equal(events.at(-1),true);hooks[1].finish();assert.equal(events.at(-1),false);
});

test('transport failure keeps the dialog visible and validation focuses the invalid field',()=>{
 let intercept;const livewire={interceptMessage:cb=>intercept=cb};registerMobileUi(livewire,{data:()=>{}});
 const alert={hidden:true,focused:false,focus(){this.focused=true;}};
 const field={focused:false,scrolled:false,focus(){this.focused=true;},scrollIntoView(){this.scrolled=true;}};
 const node={querySelector:selector=>selector==='[data-ui-request-error]'?alert:field};
 const component={el:{querySelector:()=>node}};const hooks={};globalThis.requestAnimationFrame=fn=>fn();
 intercept({message:{component},onSend:()=>{},onFinish:()=>{},onSuccess:cb=>hooks.success=cb,onFailure:cb=>hooks.failure=cb});
 hooks.failure();assert.equal(alert.hidden,false);assert(alert.focused);
 hooks.success({onRender:cb=>cb()});assert(field.focused&&field.scrolled);
});

test('client controlled dialogs stay closed initially and restore focus after local closure',()=>{
 const {events,field,origin}=dialogHarness();let active=false,watch;
 document.activeElement=origin;
 const ui=actionDialog({active:()=>active,close:()=>{active=false;watch(false);}});
 ui.$el={querySelectorAll:()=>[field],querySelector:()=>field,contains:()=>false};ui.$nextTick=fn=>fn();ui.$watch=(getter,cb)=>watch=cb;
 ui.init();assert.equal(ui.opened,false);
 active=true;watch(true);assert.equal(ui.opened,true);assert.equal(events.at(-1),'field-focus');
 document.activeElement=document.body;ui.requestClose();assert.equal(events.at(-1),'return-focus');ui.destroy();
});

test('professional anchors select one destination and keyboard listeners are removed on navigation',()=>{
 const listeners=[];const removed=[];const classes=new Set();
 const surface={addEventListener:(name,cb)=>listeners.push([name,cb]),removeEventListener:(name)=>removed.push(name)};
 const viewport={...surface,height:900};
 globalThis.window={...surface,innerHeight:900,innerWidth:390,visualViewport:viewport,location:{hash:'#schedule-title'}};
 globalThis.document={...surface,activeElement:{matches:()=>false},querySelector:()=>null,body:{classList:{toggle:(key,value)=>value?classes.add(key):classes.delete(key),remove:key=>classes.delete(key)}}};
 globalThis.requestAnimationFrame=fn=>fn();
 const ui=mobileNavigation({group:'home',professional:true});ui.$nextTick=fn=>fn();ui.init();assert(ui.isActive('schedule'));assert(!ui.isActive('home'));
 window.location.hash='#my-swaps-title';listeners.find(([name])=>name==='hashchange')[1]();assert(ui.isActive('swaps'));
 document.activeElement={matches:()=>true};viewport.height=550;listeners.find(([name])=>name==='resize')[1]();assert(ui.keyboardOpen);assert(classes.has('mobile-keyboard-open'));
 document.activeElement={matches:()=>false};viewport.height=900;listeners.find(([name])=>name==='resize')[1]();assert(!ui.keyboardOpen);
 ui.menuOpen=true;window.innerWidth=1440;listeners.find(([name])=>name==='resize')[1]();assert(!ui.menuOpen);
 ui.destroy();assert.equal(removed.length,listeners.length);assert.equal(classes.size,0);
});


test('dialog returns to explicit trigger when loading disabled it before initialization',()=>{
 const {field,events}=dialogHarness();
 const trigger={focus:()=>events.push('fallback-focus')};
 document.body.isConnected=true;
 document.activeElement=document.body;
 document.querySelector=()=>trigger;
 const ui=actionDialog({returnFocus:'#trigger'});
 ui.$el={querySelectorAll:()=>[field],querySelector:()=>field,contains:()=>false};
 ui.$nextTick=fn=>fn();ui.init();ui.destroy();
 assert.equal(events.at(-1),'fallback-focus');
});


test('child button handlers retain the dialog root for focus and dirty checks', () => {
 const {ui, field, events} = dialogHarness();
 ui.$el = {querySelector: () => null, querySelectorAll: () => [], contains: () => false};
 field.value = 'Edited'; ui.changed(); ui.requestClose();
 assert(ui.discardRequested);
 ui.keepEditing();
 assert.equal(events.at(-1), 'field-focus');
 assert(ui.dirty);
 assert.equal(ui.discardRequested, false);
});
