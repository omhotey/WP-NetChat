import assert from 'node:assert/strict';import {launch,read} from './visibility-009.test.mjs';
let checks=0;const check=(v,msg)=>{assert.ok(v,msg);checks++;};
const defaults={enabled:{},links:{},position:{top:'',left:'',right:'',bottom:'30'},size:56,animation:300,desktop:1,mobile:1,hint:0,hint_mobile:0,hint_text:'',hint_delay:2,button_delay:0,button_scroll_percent:0,hint_scroll_percent:0,color:'#2563eb',lang:'ru',order:'',launcher_mode:'form',form_presentation:'modal',form_drawer_side:'left',leads:{enabled:0,title:'Форма'}};
const current={...defaults,hint:1,hint_mobile:1,hint_text:'Тест',hint_delay:37,button_delay:41,button_scroll_percent:23,hint_scroll_percent:74,leads:{enabled:1,title:'Сохранить'}};
const browser=await launch();
async function setup({conflict=false,leadsOnly=false}={}){
 const context=await browser.newContext(),page=await context.newPage(),hits=[],errors=[];page.on('pageerror',e=>errors.push(e.message));
 page.on('dialog',dialog=>dialog.accept());await page.clock.install({time:new Date('2026-10-20T00:00:00Z')});await page.clock.pauseAt(new Date('2026-10-20T00:00:01Z'));
 await page.setContent(read('tests/visibility-009-fixture.html'));
 await page.evaluate(({defaults,current,leadsOnly})=>{
  const template=document.querySelector('#visibility-admin').innerHTML;document.body.innerHTML=template+'<div id="netchat-notice-container"></div>';
  if(leadsOnly){const flag=document.createElement('div');flag.dataset.ncLeadsOnly='';document.body.append(flag);}
  window.netchatAdmin={config:structuredClone(current),defaults:structuredClone(defaults),revision:5,restUrl:'https://visibility.invalid/settings',restNonce:'synthetic-nonce',i18n_conflict:'Конфликт ревизии'};
  const vals={'nc-opt-hd':current.hint_delay,'nc-opt-bd':current.button_delay,'nc-opt-bscroll':current.button_scroll_percent,'nc-opt-hscroll':current.hint_scroll_percent,'nc-opt-ht':current.hint_text};
  Object.entries(vals).forEach(([id,v])=>document.getElementById(id).value=String(v));
  document.querySelector('#nc-opt-h').checked=!!current.hint;document.querySelector('#nc-opt-hmobile').checked=!!current.hint_mobile;
  document.querySelector('#nc-opt-d').checked=true;document.querySelector('#nc-opt-m').checked=true;
 },{defaults,current,leadsOnly});
 let postResolve;let posted=new Promise(resolve=>postResolve=resolve);
 await page.route('**/*',async route=>{
  assert.equal(route.request().url(),'https://visibility.invalid/settings','only intercepted synthetic settings URL');
  const payload=route.request().postDataJSON();hits.push(payload);
  const {revision,...config}=payload;
  await route.fulfill({status:conflict?409:200,contentType:'application/json',body:JSON.stringify(conflict?{message:'stale'}:{success:true,revision:6,config,lang_changed:false})});postResolve(payload);
 });
 await page.addScriptTag({content:read('assets/js/admin.js')});
 await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
 return {page,context,hits,errors,posted};
}
async function save(env){
 await env.page.evaluate(()=>{window.ncSaved=new Promise(resolve=>window.addEventListener('netchat-settings-saved',resolve,{once:true}));});
 await env.page.locator('#nc-save-btn').click();await env.page.evaluate(()=>window.ncSaved);
 return env.hits.at(-1);
}
try{
 {
  const e=await setup();try{
   const payload=await save(e);
   check(payload.button_delay===41&&payload.hint_delay===37&&payload.hint_mobile===1,'new controls read actual values');
   check(payload.button_scroll_percent===23&&payload.hint_scroll_percent===74,'independent percentages retained');
   check(payload.revision===5&&payload.form_presentation==='modal'&&payload.leads.enabled===1,'revision, presentation and leads retained');
   check(await e.page.locator('#nc-opt-bd').inputValue()==='41'&&await e.page.locator('#nc-opt-hd').inputValue()==='37','save does not discard values');
   check(e.errors.length===0,'save no JS errors');
  }finally{await e.context.close();}
 }
 for(const value of ['0','600']){
  const e=await setup();try{
   await e.page.locator('#nc-opt-bd').fill(value);await e.page.locator('#nc-opt-hd').fill(value);
   await e.page.locator('#nc-opt-hmobile').uncheck();const payload=await save(e);
   check(payload.button_delay===Number(value)&&payload.hint_delay===Number(value)&&payload.hint_mobile===0,'valid bound '+value+' and explicit mobile false');
  }finally{await e.context.close();}
 }
 for(const id of ['nc-opt-bd','nc-opt-hd'])for(const value of ['', '1.5','-1','601','NaN']){
  const e=await setup();try{
   if(value==='NaN')await e.page.locator('#'+id).evaluate(el=>{el.value='not-a-number';el.dispatchEvent(new Event('input',{bubbles:true}));});
   else await e.page.locator('#'+id).fill(value);
   await e.page.locator('#nc-save-btn').click();
   check(e.hits.length===0,'no settings request for '+id+'/'+value);
   check(await e.page.locator('#'+id).getAttribute('aria-invalid')==='true','invalid field marked '+id+'/'+value);
   check(await e.page.locator('#'+id).evaluate(el=>document.activeElement===el),'invalid field focused '+id+'/'+value);
  }finally{await e.context.close();}
 }
 {
  const e=await setup();try{await e.page.locator('#nc-opt-h').uncheck();const payload=await save(e);check(payload.hint===0&&payload.hint_mobile===1&&payload.hint_delay===37,'master toggle preserves phone/time preference');}finally{await e.context.close();}
 }
 {
  const e=await setup({leadsOnly:true});try{const payload=await save(e);check(payload.hint_mobile===1&&payload.button_delay===41&&payload.hint_delay===37&&payload.button_scroll_percent===23,'independent form/preview save preserves all visibility settings');}finally{await e.context.close();}
 }
 {
  const e=await setup();try{await e.page.locator('#nc-reset-btn').click();const payload=await e.posted;check(payload.hint_mobile===0&&payload.button_delay===0&&payload.hint_delay===2,'widget reset uses new defaults');check(payload.leads.title==='Сохранить'&&payload.leads.enabled===0,'widget reset preserves lead details and disables intake');}finally{await e.context.close();}
 }
 {
  const e=await setup({leadsOnly:true});try{await e.page.locator('#nc-reset-btn').click();const payload=await e.posted;check(payload.hint_mobile===1&&payload.button_delay===41&&payload.hint_delay===37&&payload.leads.enabled===1,'form reset preserves visibility and intake');}finally{await e.context.close();}
 }
 {
  const e=await setup({conflict:true});try{
   await e.page.locator('#nc-opt-bd').fill('99');await e.page.locator('#nc-save-btn').click();await e.posted;
   // Wait for actual promise continuation; polling runs in Node, not the paused browser clock.
   await e.page.locator('.netchat-notice.error').waitFor({state:'attached'});
   check(await e.page.locator('#nc-opt-bd').inputValue()==='99','CAS conflict preserves editable draft');
   check(await e.page.evaluate(()=>netchatAdmin.revision===5&&netchatAdmin.config.button_delay===41),'CAS conflict does not adopt new revision/config');
  }finally{await e.context.close();}
 }
 console.log(JSON.stringify({status:'PASS',layer:'real_admin_JS_intercepted_transport_fake_clock_no_WordPress',assertions:checks}));
}finally{await browser.close();}
