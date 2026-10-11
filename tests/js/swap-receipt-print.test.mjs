import assert from 'node:assert/strict';
import test from 'node:test';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const blade=readFileSync(new URL('../../resources/views/components/swap-receipt.blade.php',import.meta.url),'utf8');
const code='window.printSwapReceipt = function'+blade.split('window.printSwapReceipt = function')[1].split('</script>')[0];
function harness(kind='historical',status='Aguardando gerência') {
 const callbacks=[]; const frames=[];
 const receipt={dataset:{receiptKind:kind},cloneNode(){return {outerHTML:`<article><p>${status}</p></article>`,querySelectorAll:()=>[]};}};
 const state={fallback:0};
 const document={querySelector:()=>receipt,querySelectorAll:()=>[],getElementById:()=>frames.find(frame=>frame.isConnected),
  createElement:()=>({style:{},isConnected:false,contentWindow:{document:{open(){},close(){},write(html){state.html=html;}},focus(){},print(){state.printed=(state.printed??0)+1;}},remove(){this.isConnected=false;this.contentWindow=null;}}),
  body:{appendChild(frame){frame.isConnected=true;frames.push(frame);}}};
 const window={print(){state.fallback++;}};
 vm.runInNewContext(code,{document,window,Date,console,setTimeout:callback=>callbacks.push(callback)});
 return {state,frames,callbacks,click:()=>window.printSwapReceipt({closest:()=>receipt})};
}
test('historical printing identifies the original event and never claims an updated official receipt',()=>{
 const h=harness(); h.click();
 assert.match(h.state.html,/<title>Comprovante histórico da notificação/);
 assert.match(h.state.html,/Aguardando gerência/);
 assert.doesNotMatch(h.state.html,/Comprovante Oficial de Troca de Plantão/);
 h.callbacks[0](); assert.equal(h.state.printed,1); assert.equal(h.state.fallback,0);
});
test('current receipt printing retains the official title and current state',()=>{
 const h=harness('current','Aprovada'); h.click();
 assert.match(h.state.html,/Comprovante Oficial de Troca de Plantão/);
 assert.match(h.state.html,/Aprovada/);
 h.callbacks[0](); assert.equal(h.state.printed,1);
});
test('rapid replacement or navigation cannot print a stale frame or the full notification page',()=>{
 const h=harness(); h.click(); h.click();
 h.callbacks[0](); assert.equal(h.state.printed??0,0); assert.equal(h.state.fallback,0);
 h.callbacks[1](); assert.equal(h.state.printed,1);
 const closed=harness(); closed.click(); closed.frames[0].remove(); closed.callbacks[0]();
 assert.equal(closed.state.fallback,0); assert.equal(closed.state.printed??0,0);
});
