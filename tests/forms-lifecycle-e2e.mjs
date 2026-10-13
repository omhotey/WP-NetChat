import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {spawnSync} from 'node:child_process';
import {mkdirSync} from 'node:fs';
import {createLabSession} from './lab-session.mjs';
const require=createRequire(import.meta.url),{chromium}=require(process.env.PLAYWRIGHT_MODULE_PATH||'playwright');
const origin='http://localhost:8080',lab=process.env.NETCHAT_LAB_ROOT||'D:/Project/WordPress-Lab',url=origin+'/wp-admin/admin.php?page=netchat-service-forms';
function cli(php){const r=spawnSync('docker',['compose','--project-directory',lab,'-f',lab+'/compose.yaml','exec','-T','wordpress','php'],{input:"<?php require '/var/www/html/wp-load.php'; "+php,encoding:'utf8',timeout:60000});assert.equal(r.status,0,r.error?.message||r.stderr);return r.stdout.trim();}
const initial=cli("if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true))throw new RuntimeException('Local only');echo base64_encode(wp_json_encode(get_option('netchat_service_forms')));");
const original=JSON.parse(Buffer.from(initial,'base64').toString('utf8'));
const browser=await chromium.launch({headless:true,channel:'msedge'}),context=await browser.newContext({viewport:{width:1440,height:1000}}),page=await context.newPage(),publicPage=await context.newPage();
const errors=[];[page,publicPage].forEach(p=>p.on('pageerror',e=>errors.push(e.message)));let session,id,copyId,leadId,reference;
const field=name=>page.locator('[name="'+name+'"]'),row=formId=>page.locator('[data-form-id="'+formId+'"]');
async function saved(button){await page.evaluate(()=>{const u=new URL(location.href);u.searchParams.delete('saved');history.replaceState(null,'',u);});await button.click();await page.waitForURL(u=>u.searchParams.get('saved')==='1');}
async function toggle(formId, enabled){await page.goto(url);await row(formId).locator('summary').click();await saved(row(formId).getByRole('button',{name:enabled?'Включить':'Выключить',exact:true}));}
async function remove(formId){await page.goto(url);await row(formId).locator('summary').click();await row(formId).getByRole('link',{name:'Удалить форму',exact:true}).click();await field('confirm_delete').check();await saved(page.getByRole('button',{name:'Удалить форму',exact:true}));assert.equal(await row(formId).count(),0);}
try{
 mkdirSync('tests/results',{recursive:true});session=createLabSession(lab);const auth=session.session;await context.addCookies([{name:auth.auth_name,value:auth.auth,url:origin+'/wp-admin/',httpOnly:true,sameSite:'Lax'},{name:auth.logged_name,value:auth.logged,url:origin+'/',httpOnly:true,sameSite:'Lax'}]);
 await page.goto(url);await page.getByRole('link',{name:'Создать форму',exact:true}).click();await field('form_name').fill('Синтетическая форма жизненного цикла');await field('question_steps').selectOption('2');await page.screenshot({path:'tests/results/forms-create.png',fullPage:true});await saved(page.getByRole('button',{name:'Создать и открыть редактор',exact:true}));id=new URL(page.url()).searchParams.get('form');assert.ok(id);
 await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));assert.match(await page.locator('.nc-editor-status').textContent(),/выключена/);assert.equal(await page.locator('[data-preview-step] option').count(),3);
 const base=JSON.parse(await page.locator('#nc-editor-schema').textContent());assert.equal(base.enabled,0);await publicPage.goto(origin+'/netchat-test/');assert.ok(!await publicPage.evaluate(formId=>netchatServices.forms.some(form=>form.id===formId),id));
 await page.locator('[data-editor-tab="questions"]').click();const newKeys={};
 for(const type of ['short','text','url','radio','multi']){
   const step=type==='multi'?2:1,group=page.locator('[data-step="'+step+'"]');await group.locator('[data-new-type]').selectOption(type);await group.locator('[data-add-field]').click();
   const card=group.locator('[data-field-key]').last(),key=await card.getAttribute('data-field-key');newKeys[type]=key;await field('fields['+key+'][label]').fill('Тестовый вопрос '+type);
   if(type==='text'||type==='radio')await field('fields['+key+'][required]').check();
   if(type==='radio'){
     await card.locator('[data-add-option]').click();assert.equal(await card.locator('[data-option-key]').count(),3);
     await card.locator('[data-option-key]').first().locator('[data-delete-option]').click();assert.equal(await card.locator('[data-option-key]').count(),2);
     assert.equal(await card.locator('[data-delete-option]:disabled').count(),2);
   }
 }
 const starter=page.locator('[data-field-key="'+base.fields[0].key+'"]');await starter.locator('summary').click();page.once('dialog',dialog=>dialog.accept());await starter.locator('[data-delete-field]').click();assert.equal(await page.locator('[data-field-key]').count(),5);
 // Preview follows the public contact layout and keeps answers through back/edit.
 const preview=page.locator('[data-preview]');await page.locator('[data-preview-step]').selectOption('3');await preview.locator('#nc-preview-contact-name').fill('Имя в предпросмотре');await preview.locator('[name="preview-contact-channel"][value=email]').check();await preview.locator('#nc-preview-contact-value').fill('preview@example.test');
 let previewPosts=0;const watchPreview=request=>{if(request.method()==='POST'&&request.url().includes('admin-post.php'))previewPosts++;};page.on('request',watchPreview);await preview.locator('#nc-preview-contact-value').press('Enter');await page.waitForTimeout(150);assert.equal(previewPosts,0,'Enter in preview never saves the form');page.off('request',watchPreview);
 await preview.getByRole('button',{name:'Назад',exact:true}).click();await preview.getByRole('button',{name:'Продолжить',exact:true}).click();assert.equal(await preview.locator('#nc-preview-contact-name').inputValue(),'Имя в предпросмотре');assert.equal(await preview.locator('#nc-preview-contact-value').inputValue(),'preview@example.test');
 await field('fields['+newKeys.multi+'][helper]').fill('Подсказка для нескольких вариантов');assert.equal(await preview.locator('#nc-preview-contact-value').inputValue(),'preview@example.test','Editing leaves preview contact intact');
 assert.equal(await page.locator('[data-editor-toggle]').isDisabled(),true,'State changes wait for unsaved edits');
 // An invalid input opens its collapsed question, keeping correction possible.
 const multiCard=page.locator('[data-field-key="'+newKeys.multi+'"]');await field('fields['+newKeys.multi+'][label]').fill('');await multiCard.locator('summary').click();await page.getByRole('button',{name:'Сохранить форму',exact:true}).click();assert.equal(await multiCard.locator('details').getAttribute('open'),'');await field('fields['+newKeys.multi+'][label]').fill('Тестовый вопрос multi');
 // Mobile preview has a direct route back to the current question.
 await page.setViewportSize({width:390,height:950});await page.locator('[data-show-preview]').click();assert.equal(await preview.isVisible(),true);assert.equal(await page.locator('.nc-editor-main').isVisible(),false);await page.locator('[data-hide-preview]').click();assert.equal(await multiCard.isVisible(),true);assert.equal(await field('fields['+newKeys.multi+'][label]').inputValue(),'Тестовый вопрос multi');await page.setViewportSize({width:1440,height:1000});
 await page.screenshot({path:'tests/results/forms-builder-questions.png',fullPage:true});await saved(page.getByRole('button',{name:'Сохранить форму',exact:true}));await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));const edited=JSON.parse(await page.locator('#nc-editor-schema').textContent());assert.equal(edited.fields.length,5);assert.equal(edited.enabled,0);assert.equal(edited.schema_version,base.schema_version+1);
 assert.equal(new URL(page.url()).hash,'#nc-panel-questions');assert.equal(await multiCard.locator('details').evaluate(el=>el.open),true);assert.equal(await page.locator('[data-preview-step]').inputValue(),'3');
 const config=JSON.parse(cli("echo wp_json_encode(get_option('netchat_service_forms'));"));for(const originalId of Object.keys(original.installed))assert.deepEqual(config.installed[originalId],original.installed[originalId]);assert.deepEqual(config.documents,original.documents);
 assert.equal(await page.locator('[data-editor-toggle]').isDisabled(),false);await saved(page.getByRole('button',{name:'Включить форму',exact:true}));assert.equal(new URL(page.url()).searchParams.get('form'),id,'Enabling keeps the current editor');await publicPage.reload();const published=await publicPage.evaluate(formId=>netchatServices.forms.find(form=>form.id===formId),id);assert.ok(published);assert.equal(published.fields.length,5);
 await publicPage.evaluate(formId=>NetChat.openForm({form:formId,cta_position:'hero'}),id);const root=publicPage.locator('#nc-service-root');
 for(let step=1;step<=2;step++){
   for(const f of published.fields.filter(f=>f.step===step)){
     const input=root.locator('[data-path="answers.'+f.key+'"]');
     if(f.type==='radio'||f.type==='multi')await input.first().check();else await input.fill(f.type==='url'?'https://example.test/product':'Синтетический ответ на вопрос');
   }
   await root.locator('[type="submit"]').click();
 }
 assert.equal(await root.locator('h2').textContent(),'Как с вами связаться');await root.locator('[data-path="contact.name"]').fill('Синтетический посетитель конструктора');await root.locator('[data-path="contact.channel"][value="email"]').check();
 assert.equal(await publicPage.evaluate(()=>document.activeElement.dataset.path),'contact.value','Pointer channel selection focuses the new contact field');
 await root.locator('[data-path="contact.channel"][value=email]').focus();await publicPage.keyboard.press('ArrowDown');assert.equal(await publicPage.evaluate(()=>document.activeElement.dataset.path),'contact.channel','Keyboard selection stays in its radio group');assert.equal(await root.locator('[data-path="contact.channel"][value=phone]').isChecked(),true);await publicPage.keyboard.press('ArrowUp');assert.equal(await root.locator('[data-path="contact.channel"][value=email]').isChecked(),true);
 await root.locator('[data-path="contact.value"]').fill('builder-synthetic@example.test');await root.locator('[data-path="consent.accepted"]').check();
 await root.getByRole('button',{name:'Назад',exact:true}).click();assert.equal(await root.locator('[data-path="answers.'+newKeys.multi+'"]').first().isChecked(),true);await root.locator('[type="submit"]').click();assert.equal(await root.locator('[data-path="contact.value"]').inputValue(),'builder-synthetic@example.test');
 const response=publicPage.waitForResponse(r=>r.url().endsWith('/netchat/v1/leads')&&r.request().method()==='POST');await root.locator('[type="submit"]').click();const submitted=await response,body=await submitted.json();assert.equal(submitted.status(),201,JSON.stringify(body));leadId=body.submission_id;reference=body.reference;await root.getByRole('heading',{name:'Обращение отправлено'}).waitFor();
 // The copy is independently disabled and does not inherit a page binding.
 await page.goto(url);await row(id).locator('summary').click();await saved(row(id).getByRole('button',{name:'Дублировать',exact:true}));copyId=new URL(page.url()).searchParams.get('form');assert.ok(copyId&&copyId!==id);await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));const copy=JSON.parse(await page.locator('#nc-editor-schema').textContent());assert.equal(copy.enabled,0);assert.equal(copy.question_steps,2);assert.notEqual(copy.service_code,published.service_code);assert.deepEqual(copy.fields,published.fields);assert.equal(await field('binding[url]').inputValue(),'');await remove(copyId);
 // Disabled forms disappear from runtime and their token endpoint refuses new work.
 await toggle(id,false);await publicPage.reload();assert.equal(await publicPage.evaluate(formId=>netchatServices.forms.some(form=>form.id===formId),id),false);const disabledToken=await publicPage.request.get(origin+'/wp-json/netchat/v1/leads/token?form_id='+id);assert.equal(disabledToken.status(),403);
 await page.goto(url+'&form='+id+'&view=delete');await page.screenshot({path:'tests/results/forms-delete-confirmation.png',fullPage:true});assert.equal(await field('confirm_delete').isChecked(),false);
 // Reject an unconfirmed request even if native validation is bypassed.
 const rejected=await page.evaluate(async()=>{const form=document.querySelector('form'),data=new FormData(form),response=await fetch(form.getAttribute('action'),{method:'POST',body:data});return response.status;});assert.equal(rejected,400);
 await field('confirm_delete').check();await saved(page.getByRole('button',{name:'Удалить форму',exact:true}));assert.equal(await row(id).count(),0);
 const state=JSON.parse(cli("echo wp_json_encode(get_option('netchat_service_forms'));"));assert.ok(!state.installed[id]&&state.deleted[id]);assert.equal(await publicPage.request.get(origin+'/wp-json/netchat/v1/leads/token?form_id='+id).then(r=>r.status()),403);
 // Historic submissions remain readable and filterable after the form is deleted.
 await page.goto(origin+'/wp-admin/admin.php?page=netchat-leads&form='+id);assert.ok((await page.locator('select[name="form"] option:checked').textContent()).includes('(удалена)'));assert.ok((await page.locator('tbody').textContent()).includes(reference));await page.locator('tbody a').first().click();assert.equal(new URL(page.url()).searchParams.get('lead'),String(leadId));assert.ok((await page.locator('body').textContent()).includes('Синтетический ответ на вопрос'));
 await page.locator('details#nc-lead-delete').evaluate(el=>el.open=true);await page.getByRole('button',{name:'Удалить заявку',exact:true}).click();leadId=null;
 // A copied consultation keeps its two-step public flow.
 await page.goto(url);await row('demo_test_g_v1').locator('summary').click();await saved(row('demo_test_g_v1').getByRole('button',{name:'Дублировать',exact:true}));copyId=new URL(page.url()).searchParams.get('form');await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));assert.equal(await page.locator('[data-preview-step] option').count(),2);await remove(copyId);
 // Conditional source deletion is clearly unavailable in the editor.
 await page.goto(url+'&form=demo_test_a_v1');await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));await page.locator('[data-editor-tab="questions"]').click();const conditional=page.locator('[data-field-key="stage"]');await conditional.locator('summary').click();assert.equal(await conditional.locator('[data-delete-field]').isDisabled(),true);assert.match(await conditional.locator('[data-field-dependency]').textContent(),/зависит/);
 for(const width of [320,390,1440]){await page.setViewportSize({width,height:950});await page.goto(url);const first=page.locator('[data-form-row]').first();const dimensions=await first.getByRole('link',{name:'Редактировать',exact:true}).boundingBox();assert.ok(dimensions.x>=0&&dimensions.x+dimensions.width<=width,'Catalog actions are available without horizontal scrolling');await page.goto(url+'&view=create');assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await field('template_id').selectOption('demo_test_g_v1');assert.equal(await page.locator('[data-blank-steps]').isVisible(),false);await field('template_id').selectOption('');assert.equal(await page.locator('[data-blank-steps]').isVisible(),true);await page.screenshot({path:'tests/results/forms-create-'+width+'.png',fullPage:true});}
 assert.deepEqual(errors,[]);console.log('PASS form lifecycle browser: draft creation; five field types; question/option deletion; scoped save; publication; real custom submission; independent copy; disable/token denial; confirmed form deletion; preserved/filterable historic lead; consultation steps; dependencies; three widths.');
}finally{
 if(leadId){await page.goto(origin+'/wp-admin/admin.php?page=netchat-leads&lead='+leadId);await page.locator('details#nc-lead-delete').evaluate(el=>el.open=true);await page.getByRole('button',{name:'Удалить заявку',exact:true}).click();}
 await browser.close();cli("update_option('netchat_service_forms',json_decode(base64_decode('"+initial+"'),true),false);");if(session)session.close();
 assert.deepEqual(JSON.parse(cli("echo wp_json_encode(get_option('netchat_service_forms'));")),original,'original form configuration restored');
}
