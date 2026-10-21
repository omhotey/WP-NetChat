import assert from 'node:assert/strict';
import {mkdirSync,writeFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {resolve,dirname,join} from 'node:path';
import {fileURLToPath} from 'node:url';
const releaseRoot=resolve(dirname(fileURLToPath(import.meta.url)),'../dist/release-1.2.2');
const output=resolve(process.env.NETCHAT_TEST_OUTPUT||join(releaseRoot,'browser'));
const rel=(await import('node:path')).relative(releaseRoot,output);
if(rel.startsWith('..')||(await import('node:path')).isAbsolute(rel))throw Error('Browser outputs must stay within dist/release-1.2.2');
mkdirSync(output,{recursive:true});
const pendingIds=[],releases=new Set(),evidence={regression:[],cleanup:false};
const canonical=value=>Array.isArray(value)?value.map(canonical):value&&typeof value==='object'?Object.fromEntries(Object.keys(value).sort().map(key=>[key,canonical(value[key])])):value;
const hash=value=>createHash('sha256').update(JSON.stringify(canonical(value))).digest('hex');
import {createRequire} from 'node:module';
import {createLabSession} from './lab-session.mjs';
const require=createRequire(import.meta.url),{chromium}=require(process.env.PLAYWRIGHT_MODULE_PATH||'playwright');
const hosted=process.env.GITHUB_ACTIONS==='true',origin=process.env.NETCHAT_TEST_ORIGIN||'http://localhost:8080',lab=process.env.NETCHAT_LAB_ROOT||'D:/Project/WordPress-Lab';
const browser=await chromium.launch({headless:true,...(hosted?{}:{channel:'msedge'})}),context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),page=await context.newPage();
const errors=[];page.on('pageerror',e=>errors.push(e.message));let session,id,reference,original,admin;
const root=page.locator('#nc-service-root'),input=path=>root.locator(`[data-path="${path}"]`);
async function next(){await root.locator('[type="submit"]').click();}
async function required(f,step){for(const field of f.fields){if(field.step!==step||!field.required)continue;const node=input('answers.'+field.key);if(!await node.count())continue;if(field.type==='radio'||field.type==='multi'){const choice=field.options.unknown?'unknown':Object.keys(field.options)[0];await root.locator(`[data-path="answers.${field.key}"][value="${choice}"]`).check();}else await node.fill('Пока не знаю');}}
async function saveConfig(config){return admin.evaluate(async c=>{const r=await fetch(netchatAdmin.restUrl,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':netchatAdmin.restNonce},body:JSON.stringify({...c,revision:netchatAdmin.revision})});const data=await r.json();if(!r.ok)throw Error('Fixture settings rejected');netchatAdmin.revision=data.revision||netchatAdmin.revision;return data;},config);}
try{
 if(hosted){await page.goto(origin+'/wp-login.php');await page.locator('#user_login').fill('netchat_ci');await page.locator('#user_pass').fill('ci-disposable-only');await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');}
 else{session=createLabSession(lab);const auth=session.session;await context.addCookies([{name:auth.auth_name,value:auth.auth,url:origin+'/wp-admin/',httpOnly:true,sameSite:'Lax'},{name:auth.logged_name,value:auth.logged,url:origin+'/',httpOnly:true,sameSite:'Lax'}]);}
 admin=await context.newPage();await admin.goto(origin+'/wp-admin/admin.php?page=netchat');await admin.waitForFunction(()=>window.netchatAdmin?.config);original=await admin.evaluate(()=>structuredClone(netchatAdmin.config));evidence.before_config_sha256=hash(original);await saveConfig({...original,leads:{...original.leads,email:{...original.leads.email,enabled:0}}});
 await page.goto(origin+'/netchat-test/');await page.waitForFunction(()=>window.NetChatServiceForms&&window.netchatServices.forms.length>=7);
 const forms=await page.evaluate(()=>netchatServices.forms.filter(f=>f.service_code.startsWith('TEST_')));
 for(const f of forms){await page.locator(`[data-netchat-service="${f.service_code}"]`).click();await root.locator('h2').waitFor();assert.equal(await root.locator('h2').textContent(),f.title);assert.equal(await input('contact.name').count(),0);
  for(let step=1;step<(f.service_code==='TEST_G'?2:3);step++){await required(f,step);await next();}
  assert.equal(await root.locator('h2').textContent(),'Как с вами связаться');await input('contact.name').fill('Синтетический посетитель');await root.locator('[data-path="contact.channel"][value="email"]').check();await input('contact.value').fill('synthetic@example.test');await input('consent.accepted').check();
  if(f.service_code==='TEST_G'){
   let committed=null,firstKey=null;
   await page.route('**/netchat/v1/leads',async route=>{firstKey=route.request().postDataJSON().request_id;const saved=await route.fetch();assert.equal(saved.status(),201);committed=await saved.json();id=committed.submission_id;await route.abort('failed');});
   await next();await root.getByRole('button',{name:'Повторить отправку',exact:true}).waitFor();assert.equal(await input('contact.name').inputValue(),'Синтетический посетитель');assert.ok((await root.textContent()).includes('Не удалось подтвердить отправку'));await page.screenshot({path:output+'/service-network-retry.png'});await page.unroute('**/netchat/v1/leads');
   const response=page.waitForResponse(r=>r.url().endsWith('/netchat/v1/leads')&&r.request().method()==='POST');await next();const r=await response,body=await r.json();assert.equal(r.status(),200,JSON.stringify(body));assert.equal(r.request().postDataJSON().request_id,firstKey);assert.equal(body.submission_id,committed.submission_id);assert.equal(body.reference,committed.reference);assert.match(body.reference,/^[БГДЖЗКЛМНПРСТФЧШ][2-9][БГДЖЗКЛМНПРСТФЧШ]-[1-9][0-9]{3}$/u);assert.equal(body.created,false);id=body.submission_id;reference=body.reference;await root.getByRole('heading',{name:'Обращение отправлено'}).waitFor();assert.ok(id);assert.ok((await root.textContent()).includes('Номер обращения: '+body.reference));await page.screenshot({path:output+'/service-success-desktop.png'});await root.getByRole('button',{name:'Закрыть',exact:true}).click();
   await page.locator('[data-netchat-service="TEST_G"]').click();assert.equal(await input('answers.question').inputValue(),'');await page.locator('.nc-leads-close').click();
  }else{await page.keyboard.press('Escape');assert.equal(await root.locator('h2').textContent(),'Закрыть форму?');await root.getByRole('button',{name:'Закрыть',exact:true}).click();assert.equal(await page.locator('#nc-leads-modal').isVisible(),false);}
 }
 // Pending close/reopen regression: a delayed A request must not strand B.
 if(hosted)await saveConfig({...original,button_scroll_percent:25,hint_scroll_percent:50,hint:1,hint_text:'Synthetic pending greeting',hint_delay:0,leads:{...original.leads,email:{...original.leads.email,enabled:0}}});
 for(const outcome of ['success','error','timeout']){
  if(hosted){
   // The previous case crosses the threshold. Reset before the new page view;
   // resetting after reload cannot undo legitimate sticky restored eligibility.
   const previousScroll=await page.evaluate(()=>{const before=scrollY;history.scrollRestoration='manual';scrollTo(0,0);return before;});
   await page.waitForFunction(()=>scrollY===0);
   (evidence.regression_initial_scroll||(evidence.regression_initial_scroll=[])).push({outcome,previousScroll});
  }
  await page.reload();await page.waitForFunction(()=>window.NetChatServiceForms);
  if(hosted){await page.waitForTimeout(80);assert.equal(await page.evaluate(()=>scrollY),0,'new pending page view starts at zero before eligibility');assert.equal(await page.locator('#nc-toggle').isVisible(),false);}
  await page.evaluate(()=>NetChat.openForm({service_code:'TEST_G',cta_position:'hero'}));
  await required(forms.find(f=>f.service_code==='TEST_G'),1);await next();
  await input('contact.name').fill('Синтетический посетитель');await root.locator('[data-path="contact.channel"][value="email"]').check();await input('contact.value').fill('synthetic@example.test');await input('consent.accepted').check();
  let release,enteredResolve;const held=new Promise(r=>release=r),entered=new Promise(r=>enteredResolve=r);releases.add(release);
  await page.route('**/netchat/v1/leads',async route=>{
   enteredResolve();await held;
   try{
    if(outcome==='success'){const saved=await route.fetch();const body=await saved.json();assert.equal(saved.status(),201);pendingIds.push(body.submission_id);await route.fulfill({response:saved});}
    else if(outcome==='error')await route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({code:'netchat_leads_storage',message:'Synthetic failure',data:{status:503}})});
    else await route.abort('timedout');
   }catch(error){if(outcome!=='timeout')throw error;}
  });
  await next();let enteredTimer;try{await Promise.race([entered,new Promise((_,reject)=>enteredTimer=setTimeout(()=>reject(Error('POST did not start: validation/token/rate may have rejected it')),10000))]);}finally{clearTimeout(enteredTimer);}await page.locator('.nc-leads-close').click();
  assert.ok((await root.textContent()).includes('Закрытие не отменяет запрос'));
  await root.getByRole('button',{name:'Закрыть',exact:true}).click();
  await page.evaluate(()=>NetChat.openForm({service_code:'TEST_A',cta_position:'hero'}));
  assert.equal(await input('answers.idea').isDisabled(),true);
  if(hosted){await page.evaluate(()=>scrollTo(0,document.scrollingElement.scrollHeight));await page.waitForTimeout(80);assert.equal(await input('answers.idea').isDisabled(),true,'threshold keeps pending B disabled until A settles');assert.equal(await page.locator('#nc-hint').isVisible(),false,'pending opened form suppresses greeting');}
  if(outcome!=='timeout')release();
  await page.waitForFunction(()=>{const el=document.querySelector('#nc-service-root [data-path="answers.idea"]');return el&&!el.disabled;},{},{timeout:25000});
  if(outcome==='timeout')release();
  await input('answers.idea').fill('После запроса A форма B доступна');
  assert.equal(await root.locator('[type="submit"]').isDisabled(),false);
  assert.equal(await root.locator('[type="submit"]').textContent(),'Продолжить');
  evidence.regression.push({outcome,pass:true});console.log('PASS pending A→close→B: '+outcome);await page.screenshot({path:join(output,'pending-'+outcome+'.png')});
  await page.unroute('**/netchat/v1/leads');
  await page.locator('.nc-leads-close').click();await root.getByRole('button',{name:'Закрыть',exact:true}).click();
 }
 // Restore the existing scenario drafts after the independent regression pages.
 if(hosted)await saveConfig({...original,leads:{...original.leads,email:{...original.leads.email,enabled:0}}});
 await page.reload();await page.waitForFunction(()=>window.NetChatServiceForms);
 for(const f of forms.filter(f=>f.service_code!=='TEST_G')){
  await page.evaluate(code=>NetChat.openForm({service_code:code}),f.service_code);
  for(let step=1;step<3;step++){await required(f,step);await next();}
  await input('contact.name').fill('Синтетический посетитель');
  await page.locator('.nc-leads-close').click();await root.getByRole('button',{name:'Закрыть',exact:true}).click();
 }
 await page.evaluate(()=>NetChat.openForm({service_code:'BOGUS',cta_position:'hero'}));assert.equal(await root.locator('h2').textContent(),'Какую задачу хотите обсудить?');await root.getByRole('button',{name:'Продолжить',exact:true}).click();assert.ok((await root.textContent()).includes('Выберите направление'));await page.locator('.nc-leads-close').click();
 for(const width of [320,360,390,768,1024,1440]){await page.setViewportSize({width,height:900});await page.evaluate(()=>NetChat.openForm({service_code:'TEST_G',cta_position:'bottom'}));assert.equal(await input('contact.name').count(),0);assert.equal(await page.evaluate(()=>document.activeElement.tagName),'H2');assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));const box=await page.locator('.nc-leads-dialog').boundingBox();assert.ok(box.width<=width);if(width>=768)assert.ok(box.width<=640&&box.x>=24);await page.screenshot({path:join(output,`service-form-${width}.png`)});await page.locator('.nc-leads-close').click();}
 await page.locator('[data-netchat-service="TEST_D"]').click();await root.getByRole('button',{name:'Назад',exact:true}).click();await root.getByRole('button',{name:'Назад',exact:true}).click();await root.locator('[data-path="answers.urgency"][value="critical"]').check();await next();await next();assert.ok((await root.textContent()).includes('Заполните это поле'));await page.screenshot({path:output+'/service-conditional-error.png'});await root.getByRole('button',{name:'Назад',exact:true}).click();await root.locator('[data-path="answers.urgency"][value="planned"]').check();await next();assert.equal(await input('answers.critical_impact').count(),0);assert.equal(await root.locator('.nc-s-error').count(),0);await page.keyboard.press('Escape');await root.getByRole('button',{name:'Закрыть',exact:true}).click();
 await page.locator('[data-netchat-service="TEST_A"]').click();assert.equal(await input('contact.name').inputValue(),'Синтетический посетитель');await root.getByRole('button',{name:'Сменить направление',exact:true}).click();await root.getByRole('button',{name:'Сменить направление',exact:true}).click();await root.locator('[name="nc-service-selector"][value="demo_test_b_v1"]').check();await root.getByRole('button',{name:'Продолжить',exact:true}).click();assert.equal(await input('answers.product').inputValue(),'');const growth=forms.find(f=>f.service_code==='TEST_B');await required(growth,1);await next();await required(growth,2);await next();assert.equal(await input('contact.name').inputValue(),'Синтетический посетитель');await page.locator('.nc-leads-close').click();await root.getByRole('button',{name:'Закрыть',exact:true}).click();
 const storage=await page.evaluate(()=>[...Object.values(localStorage),...Object.values(sessionStorage)].join('\n'));assert.ok(!storage.includes('Синтетический посетитель')&&!storage.includes('synthetic@example.test'));assert.deepEqual(errors,[]);
 await admin.goto(origin+`/wp-admin/admin.php?page=netchat-leads&s=${encodeURIComponent(reference)}`);assert.equal(await admin.locator('tbody tr').count(),1);assert.ok((await admin.locator('tbody').textContent()).includes(reference));await admin.locator('tbody a').first().click();assert.equal(new URL(admin.url()).searchParams.get('lead'),String(id));await admin.waitForLoadState('domcontentloaded');await admin.getByText('Синтетический посетитель',{exact:true}).first().waitFor({state:'visible',timeout:10000});assert.ok((await admin.locator('body').textContent()).includes('Синтетический посетитель'));await admin.screenshot({path:output+'/service-admin-snapshot.png',fullPage:true});await admin.locator('details#nc-lead-delete').evaluate(e=>e.open=true);await admin.getByRole('button',{name:'Удалить заявку',exact:true}).click();id=null;
 console.log('PASS service browser: seven synthetic contact-last paths; live save/ID; dirty close; selector; conditional errors; six widths; empty reopen; private page-memory drafts; protected snapshot.');
}finally{
 releases.forEach(release=>release());
 const cleanupErrors=[];
 try{
  for(const fixtureId of [...pendingIds,...(id?[id]:[])]){
   try{await admin.goto(origin+`/wp-admin/admin.php?page=netchat-leads&lead=${fixtureId}`);await admin.locator('#nc-lead-delete').evaluate(e=>e.open=true);await admin.getByRole('button',{name:'Удалить заявку',exact:true}).click();}catch(error){cleanupErrors.push('Synthetic lead cleanup failed: '+fixtureId);}
  }
  if(admin&&original){
   await admin.goto(origin+'/wp-admin/admin.php?page=netchat');await admin.waitForFunction(()=>window.netchatAdmin?.config);await saveConfig(original);
   await admin.reload();await admin.waitForFunction(()=>window.netchatAdmin?.config);
   evidence.after_config_sha256=hash(await admin.evaluate(()=>netchatAdmin.config));
   assert.equal(evidence.after_config_sha256,evidence.before_config_sha256,'Configuration readback must equal original (revision excluded)');
  }
 }catch(error){cleanupErrors.push(error.message);}
 try{await browser.close();}catch(error){cleanupErrors.push(error.message);}
 if(session){try{
  session.close();
  const result=spawnSync('docker',['compose','--project-directory',lab,'-f',lab+'/compose.yaml','run','--pull','never','--rm','--no-deps','wpcli','eval',`if(WP_Session_Tokens::get_instance(${session.session.user})->verify('${session.session.token}'))WP_CLI::error('Session remains');else echo 'Session revoked';`],{encoding:'utf8',timeout:60000});
  assert.equal(result.status,0,'Session revocation readback failed');evidence.session_revoked=true;
 }catch(error){cleanupErrors.push(error.message);}}
 evidence.cleanup=cleanupErrors.length===0;evidence.synthetic_leads_removed=[...pendingIds,...(id?[id]:[])];
 writeFileSync(join(output,'verification.json'),JSON.stringify(evidence,null,2));
 if(cleanupErrors.length)throw Error(cleanupErrors.join('; '));
}
