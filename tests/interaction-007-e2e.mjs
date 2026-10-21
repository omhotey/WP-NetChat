import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {createRequire} from 'node:module';import {fileURLToPath} from 'node:url';
const require=createRequire(import.meta.url),{chromium}=require(process.env.PLAYWRIGHT_MODULE_PATH||'C:/Users/omhotey/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..'),read=p=>fs.readFileSync(path.join(root,p),'utf8');
const browser=await chromium.launch({headless:true,channel:process.env.BROWSER_CHANNEL||'msedge'});let checks=0;const failures=[];
function check(v,msg){assert.ok(v,msg);checks++;}
const css=['assets/css/style.css','assets/css/leads.css','assets/css/contact-alternatives.css','assets/css/service-forms.css','assets/css/popup.css'].map(read).join('\n');
const docs={document_url:'https://example.test/consent',policy_url:'https://example.test/privacy',text_version:'1',document_version:'1',policy_version:'1'};
const makeForm=(id,code)=>({id,service_code:code,schema_version:1,service_label:code,title:'Синтетическая задача',intro:'Тестовый сценарий',submit_text:'Отправить задачу',success_text:'Получено',question_steps:1,fields:[{key:'task',label:'Задача',type:'short',required:false,step:1}]});
async function setup(width,presentation,side,{empty=false,reduced=false}={}){
 const context=await browser.newContext({viewport:{width,height:760},reducedMotion:reduced?'reduce':'no-preference'}),page=await context.newPage();let scenario='success',hold=null,postReady;const posted=new Promise(resolve=>postReady=resolve);
 page.on('pageerror',e=>failures.push(e.message));
 await page.route('**/*',async route=>{
  const request=route.request(),url=request.url();
  if(request.isNavigationRequest())return route.fulfill({status:200,contentType:'text/html',body:read('tests/interaction-007-fixture.html')});
  if(url.includes('/token'))return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({token:'fixture-token',expires_at:Math.floor(Date.now()/1000)+600,documents:docs})});
  if(request.method()==='POST'){
   if(scenario==='hold'){hold=route;postReady();return;}
   if(scenario==='timeout'){postReady();return;}
   return route.fulfill({status:scenario==='error'?503:201,contentType:'application/json',body:JSON.stringify(scenario==='error'?{message:'Синтетическая ошибка'}:{success:true,reference:'К7М-4826'})});
  }
  throw new Error('Unexpected fixture request '+request.method()+' '+url);
 });
 await page.goto('https://interaction-fixture.invalid/');
 await page.addStyleTag({content:css});
 await page.evaluate(({presentation,side,empty,docs,forms})=>{
  const modal=document.querySelector('#nc-leads-modal');modal.dataset.formPresentation=presentation;modal.dataset.formDrawerSide=side;
  if(empty)document.querySelector('#nc-contact-alternatives').remove();
  window.netchatLeads={endpoint:'https://interaction-fixture.invalid/leads',successText:'Принято',errorText:'Ошибка',contactText:'Укажите контакт',busyText:'Отправляем'};
  window.netchatServices={endpoint:'https://interaction-fixture.invalid/service',forms,documents:docs,page_id:1,context_token:'synthetic-context'};
 },{presentation,side,empty,docs,forms:[makeForm('fixture_a','TEST_A'),makeForm('fixture_b','TEST_B')]});
 await page.addScriptTag({content:read('assets/js/leads.js')});
 await page.addScriptTag({content:read('assets/js/service-forms.js')});
 return {page,context,setScenario:v=>scenario=v,held:()=>hold,waitPosted:()=>posted};
}
async function expandedBottom(page,service,empty=false){
 const info=await page.evaluate(({service})=>{
  const parent=document.querySelector(service?'#nc-service-root':'.nc-leads-dialog'),blocks=[...parent.children].filter(x=>x.matches('[data-contact-alternatives]')&&!x.hidden);
  const block=blocks[0];return {blocks:blocks.length,last:!!block&&parent.lastElementChild===block,details:!!block&&!!block.querySelector('details,summary'),nav:!!block&&getComputedStyle(block.querySelector('nav')).display!=='none'};
 },{service});
 if(empty)check(info.blocks===0,'empty contacts have no block');
 else{check(info.blocks===1&&info.last,'contacts at end, exactly once');check(!info.details&&info.nav,'contacts expanded with no details');}
}
async function fillService(page){
 await page.locator('#nc-service-root button[type=submit]').click();
 await page.locator('input[data-path="contact.name"]').fill('Тестовый посетитель');
 await page.locator('input[data-path="contact.channel"][value=email]').check();
 await page.locator('input[data-path="contact.value"]').fill('fixture@example.test');
 await page.locator('input[data-path="consent.accepted"]').check();
}
try{
 // Actual admin JS: side visibility and saved payload use independent settings.
 {
  const context=await browser.newContext(),page=await context.newPage();let payload=null;
  await page.route('https://interaction-fixture.invalid/settings',async route=>{payload=route.request().postDataJSON();await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({success:true,revision:2,config:payload,lang_changed:false})});});
  await page.setContent('<select id="nc-form-presentation"><option value="modal">modal</option><option value="drawer">drawer</option></select><div id="nc-drawer-side-row"><select id="nc-form-drawer-side"><option value="left">left</option><option value="right">right</option></select></div><button id="nc-save-btn">Сохранить</button>');
  await page.evaluate(()=>window.netchatAdmin={config:{leads:{enabled:0},form_presentation:'modal',form_drawer_side:'left',button_scroll_percent:31,hint_scroll_percent:72},revision:1,restUrl:'https://interaction-fixture.invalid/settings',restNonce:'fixture-nonce'});
  await page.addScriptTag({content:read('assets/js/admin.js')});await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  check(await page.locator('#nc-drawer-side-row').isHidden(),'side hidden for modal');
  await page.locator('#nc-form-presentation').selectOption('drawer');check(await page.locator('#nc-drawer-side-row').isVisible(),'side visible for drawer');
  await page.locator('#nc-form-drawer-side').selectOption('right');
  await page.locator('#nc-form-presentation').selectOption('modal');check(await page.locator('#nc-form-drawer-side').inputValue()==='right','side retained while modal hides it');
  await page.locator('#nc-form-presentation').selectOption('drawer');await page.locator('#nc-save-btn').click();
  await page.waitForFunction(()=>window.netchatAdmin.revision===2);
  check(payload.form_presentation==='drawer'&&payload.form_drawer_side==='right'&&payload.button_scroll_percent===31&&payload.hint_scroll_percent===72,'actual admin payload preserves drawer and independent percentages');
  await context.close();
 }
 for(const width of [320,390,1440])for(const [presentation,side]of [['modal','left'],['drawer','left'],['drawer','right']]){
  const {page,context}=await setup(width,presentation,side);
  await page.locator('#trigger').click();await expandedBottom(page,false);
  await page.waitForFunction(()=>document.querySelector('.nc-leads-dialog').getAnimations().every(a=>a.playState==='finished'));
  const geometry=await page.locator('.nc-leads-dialog').evaluate(el=>{const b=el.getBoundingClientRect();return {x:b.x,right:b.right,width:b.width,height:b.height,background:getComputedStyle(el).backgroundColor,scroll:getComputedStyle(el).overflowY};});
  check(geometry.x>=-1&&geometry.right<=width+1,'dialog fits viewport '+width);
  check(geometry.background==='rgb(255, 255, 255)','form background opaque');
  if(presentation==='drawer'){check(Math.abs((side==='left'?geometry.x:geometry.right-width))<=1,'drawer independent edge');check(geometry.height>=759&&geometry.scroll==='auto','drawer full height independently scrolls');}
  check(await page.locator('#page').getAttribute('inert')!==null,'background inert');
  check(await page.evaluate(()=>document.body.style.overflow==='hidden'),'body scroll locked');
  const last=page.locator('.nc-leads-dialog [data-contact-alternatives] a').last();await last.focus();await page.keyboard.press('Tab');
  check(await page.evaluate(()=>document.activeElement.matches('[data-netchat-close]')),'focus wraps from contacts');
  await page.keyboard.press('Shift+Tab');check(await last.evaluate(el=>document.activeElement===el),'reverse focus wrap');
  await page.keyboard.press('Escape');check(await page.locator('#nc-leads-modal').isHidden(),'Escape closes');
  check(await page.locator('#page').getAttribute('inert')===null,'inert restored');
  check(await page.evaluate(()=>document.activeElement.id==='trigger'&&document.body.style.overflow===''),'focus and scroll restored');
  check(await page.locator('#nc-bubble').evaluate(el=>getComputedStyle(el).backgroundColor==='rgba(0, 0, 0, 0)'&&getComputedStyle(el).boxShadow==='none'),'menu container transparent');
  await page.waitForFunction(()=>getComputedStyle(document.querySelector('.nc-menu-form')).opacity==='1');
  check(await page.locator('.nc-menu-form').evaluate(el=>getComputedStyle(el).backgroundColor==='rgb(37, 99, 235)'&&getComputedStyle(el).opacity==='1'),'button remains opaque');
  await page.evaluate(()=>NetChat.openForm({service_selector:true}));await expandedBottom(page,true);
  await page.locator('input[name=nc-service-selector][value=fixture_a]').check();await page.locator('#nc-service-root .nc-s-primary').click();await expandedBottom(page,true);
  await fillService(page);await expandedBottom(page,true);
  await page.locator('#nc-service-root button[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#nc-service-root').textContent.includes('К7М-4826'));await expandedBottom(page,true);
  await page.keyboard.press('Escape');check(await page.locator('#nc-leads-modal').isHidden(),'service success closes');
  await context.close();
 }
 for(const scenario of ['success','error','timeout']){
  const {page,context,setScenario,held,waitPosted}=await setup(390,'drawer','right');
  await page.evaluate(()=>NetChat.openForm({service_code:'TEST_A',trigger:document.querySelector('#trigger')}));
  await fillService(page);setScenario(scenario==='timeout'?'timeout':'hold');
  await page.locator('#nc-service-root button[type=submit]').click();
  await page.waitForFunction(()=>document.querySelector('#nc-service-root').textContent.includes('Отправляем'));
  await waitPosted();
  await page.keyboard.press('Escape');await expandedBottom(page,true);
  await page.getByRole('button',{name:'Закрыть',exact:true}).last().click();
  await page.evaluate(()=>NetChat.openForm({service_code:'TEST_B'}));await expandedBottom(page,true);
  check(await page.locator('input[data-path="answers.task"]').inputValue()==='','new form B no draft A');
  if(scenario!=='timeout'){
   assert.ok(held(),'real fixture POST reached interceptor');
   await held().fulfill({status:scenario==='error'?503:201,contentType:'application/json',body:JSON.stringify(scenario==='error'?{message:'Синтетическая ошибка'}:{success:true,reference:'К7М-4826'})});
  }
  await page.waitForFunction(()=>!document.querySelector('#nc-service-root button[type=submit]').disabled,{},{timeout:25000});
  check(!(await page.locator('#nc-service-root').textContent()).includes('К7М-4826'),'pending A outcome never replaces B');
  check(await page.locator('input[data-path="answers.task"]').inputValue()==='','B remains intact after '+scenario);
  await context.close();
 }

 // Contact preview: execute the actual admin script against explicit synthetic controls.
 for(const empty of [true,false]){
  const context=await browser.newContext({viewport:{width:390,height:760}}),page=await context.newPage();
  await page.setContent('<div class="nc-enquiry-editor"><span data-enquiry-status></span><button data-enquiry-field="email"><small></small><i class="nc-e-field-dot"></i></button><div data-enquiry-preview></div><template id="nc-enquiry-preview-alternatives">'+(empty?'':'<aside data-contact-alternatives><p>Или связаться напрямую</p><nav><a href="mailto:fixture@example.test">Email</a></nav></aside>')+'</template></div>');
  await page.evaluate(()=>window.netchatAdmin={config:{leads:{title:'Синтетическая форма',button_text:'Отправить',fields:{email:{enabled:1,required:1,label:'Email',placeholder:''}},consent:{enabled:0}}}});
  await page.addScriptTag({content:read('assets/js/enquiry-editor.js')});
  const info=await page.locator('[data-enquiry-preview]').evaluate(el=>({last:el.lastElementChild.hasAttribute('data-contact-alternatives'),blocks:el.querySelectorAll('[data-contact-alternatives]').length,details:el.querySelectorAll('details,summary').length,ids:el.querySelectorAll('[data-contact-alternatives] [id]').length}));
  check(info.blocks===(empty?0:1)&&info.last===!empty&&info.details===0&&info.ids===0,'contact preview expanded bottom or no contacts');
  await context.close();
 }
 // Service preview: run its actual render function with explicit editor state.
 const adminSource=read('assets/js/service-forms-admin.js'),start=adminSource.indexOf('    function render() {'),end=adminSource.indexOf('    function activate(',start),renderer=adminSource.slice(start,end);
 for(const step of [1,2])for(const empty of [true,false]){
  const context=await browser.newContext({viewport:{width:390,height:760}}),page=await context.newPage();
  await page.setContent('<section id="editor"><select data-preview-step><option value="1">1</option><option value="2">2</option></select><input data-shortcode><div data-preview></div><template id="nc-preview-alternatives">'+(empty?'':'<aside data-contact-alternatives><p>Или связаться напрямую</p><nav><a href="mailto:fixture@example.test">Email</a></nav></aside>')+'</template></section>');
  await page.evaluate(({renderer,schema,step})=>{
   const root=document.querySelector('#editor'),preview=root.querySelector('[data-preview]');root.querySelector('[data-preview-step]').value=String(step);
   function node(tag,text,cls){const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;}
   const render=new Function('root','preview','current','total','answers','contact','consent','node','matches','updateDependencies',renderer+';return render;')(root,preview,()=>schema,2,{},{},false,node,()=>true,()=>{});
   render();
  },{renderer,schema:makeForm('fixture_a','TEST_A'),step});
  const info=await page.locator('[data-preview]').evaluate(el=>({last:el.lastElementChild.hasAttribute('data-contact-alternatives'),blocks:el.querySelectorAll('[data-contact-alternatives]').length,details:el.querySelectorAll('details,summary').length}));
  check(info.blocks===(empty?0:1)&&info.last===!empty&&info.details===0,'service preview all steps alternatives bottom');
  await context.close();
 }
 for(const empty of [true,false]){
  const {page,context}=await setup(320,'drawer','left',{empty,reduced:true});
  await page.locator('#trigger').click();await expandedBottom(page,false,empty);
  check(await page.locator('.nc-leads-dialog').evaluate(el=>getComputedStyle(el).animationName==='none'),'reduced motion drawer');
  await page.keyboard.press('Escape');await page.evaluate(()=>NetChat.openForm({service_code:'TEST_A'}));await expandedBottom(page,true,empty);await context.close();
 }
 check(failures.length===0,'no browser errors '+failures.join(';'));
 console.log(JSON.stringify({status:'PASS',layer:'isolated_browser_fixture_intercepted_transport_not_WordPress',assertions:checks,widths:[320,390,1440],pending:['success','503','real_20_second_timeout']}));
}finally{await browser.close();}
