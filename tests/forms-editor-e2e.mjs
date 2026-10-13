import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {spawnSync} from 'node:child_process';
import {mkdirSync} from 'node:fs';
import {createLabSession} from './lab-session.mjs';
const require=createRequire(import.meta.url),{chromium}=require(process.env.PLAYWRIGHT_MODULE_PATH||'playwright');
const origin='http://localhost:8080',lab=process.env.NETCHAT_LAB_ROOT||'D:/Project/WordPress-Lab';
function cli(php){const r=spawnSync('docker',['compose','--project-directory',lab,'-f',lab+'/compose.yaml','exec','-T','wordpress','php'],{input:"<?php require '/var/www/html/wp-load.php'; "+php,encoding:'utf8',timeout:60000});assert.equal(r.status,0,r.error?.message||r.stderr);return r.stdout.trim();}
const initial=cli("if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true))WP_CLI::error('Local only'); echo base64_encode(wp_json_encode(get_option('netchat_service_forms')));");
const original=JSON.parse(Buffer.from(initial,'base64').toString('utf8'));
const browser=await chromium.launch({headless:true,channel:'msedge'}),context=await browser.newContext({viewport:{width:1440,height:1000}}),page=await context.newPage();
const errors=[];page.on('pageerror',e=>errors.push(e.message));let session;
const url=origin+'/wp-admin/admin.php?page=netchat-service-forms',id='demo_test_a_v1';
const field=name=>page.locator('[name="'+name+'"]');
async function save(){await page.evaluate(()=>{const url=new URL(location.href);url.searchParams.delete('saved');history.replaceState(null,'',url);});await page.getByRole('button',{name:'Сохранить форму',exact:true}).click();try{await page.waitForURL(u=>u.searchParams.get('saved')==='1',{timeout:10000});}catch(error){throw new Error('Save failed at '+page.url()+': '+await page.locator('[data-editor-form]>[role="alert"]').textContent());}await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));}
try{
 mkdirSync('tests/results',{recursive:true});
 session=createLabSession(lab);const auth=session.session;await context.addCookies([{name:auth.auth_name,value:auth.auth,url:origin+'/wp-admin/',httpOnly:true,sameSite:'Lax'},{name:auth.logged_name,value:auth.logged,url:origin+'/',httpOnly:true,sameSite:'Lax'}]);
 await page.goto(url);assert.equal(await page.locator('[data-form-row]').count(),Object.keys(original.installed).length);assert.equal(await page.locator('[name^="texts"]').count(),0);
 await page.locator('#nc-form-search').fill('Консультация');assert.equal(await page.locator('[data-form-row]:visible').count(),1);await page.locator('#nc-form-search').fill('ничегонет');assert.equal(await page.locator('#nc-form-empty').isVisible(),true);await page.locator('#nc-form-search').fill('');
 await page.screenshot({path:'tests/results/forms-catalog.png',fullPage:true});
 await page.goto(url+'&form='+id);await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));const schema=JSON.parse(await page.locator('#nc-editor-schema').textContent());
 await field('texts[title]').fill('Тестовый заголовок редактора');assert.equal(await page.locator('[data-preview] h3').textContent(),'Тестовый заголовок редактора');
 assert.match(await page.locator('[data-dirty-status]').textContent(),/несохранённые/);
 let discarded=false;page.once('dialog',async dialog=>{discarded=true;await dialog.dismiss();});
 await page.locator('.nc-editor-back').click();await page.waitForTimeout(200);assert.equal(discarded,true);assert.equal(new URL(page.url()).searchParams.get('form'),id);
 await page.locator('[data-editor-tab="questions"]').click();const stage=page.locator('[data-field-key="stage"]');await stage.locator('summary').click();await field('fields[stage][label]').fill('Тест стадии продукта');await field('fields[stage][options][prototype]').fill('Тестовый прототип');await field('fields[stage][helper]').fill('Тестовая подсказка');await field('fields[stage][required]').uncheck();
 await page.locator('[data-preview] [name="preview-stage"][value="prototype"]').check();await page.locator('[data-preview-step]').selectOption('2');assert.ok((await page.locator('[data-preview]').textContent()).includes('Тестовый вопрос 6'));
 await page.locator('[data-preview-step]').selectOption('1');await page.locator('[data-preview] [name="preview-stage"][value="idea"]').check();await page.locator('[data-preview-step]').selectOption('2');assert.ok(!(await page.locator('[data-preview]').textContent()).includes('Тестовый вопрос 6'));
 await stage.locator('[data-move="down"]').click();assert.equal(await page.locator('[data-step="1"] [data-field-key]').first().getAttribute('data-field-key'),'idea');
 await page.locator('[data-preview-step]').selectOption('1');await page.screenshot({path:'tests/results/forms-editor-questions.png',fullPage:true});
 await page.locator('[data-editor-tab="placement"]').click();await page.getByRole('button',{name:'Копировать шорткод'}).click();await page.waitForFunction(()=>document.querySelector('[data-copy-status]').textContent.length>0);
 // Server-side error stays in the editor and retains all unsaved fields.
 await field('binding[url]').fill('https://foreign.example.test/wrong');await page.getByRole('button',{name:'Сохранить форму',exact:true}).click();await page.locator('[data-editor-form]>[role="alert"]').waitFor();assert.equal(await field('texts[title]').inputValue(),'Тестовый заголовок редактора');
 await field('binding[url]').fill(original.bindings[schema.service_code]?.url||'');await save();
 assert.equal(await field('texts[title]').inputValue(),'Тестовый заголовок редактора');
 let saved=JSON.parse(cli("echo wp_json_encode(get_option('netchat_service_forms'));"));
 assert.equal(saved.installed[id].schema_version,schema.schema_version+1);
 assert.equal(saved.installed[id].fields[0].key,'idea');
 assert.equal(saved.installed[id].fields.find(f=>f.key==='stage').required,false);
 assert.equal(saved.installed[id].fields.find(f=>f.key==='stage').options.prototype,'Тестовый прототип');
 for(const key of Object.keys(original.installed))if(key!==id)assert.deepEqual(saved.installed[key],original.installed[key]);
 assert.deepEqual(saved.documents,original.documents);assert.deepEqual(saved.bindings,original.bindings);assert.equal(saved.enabled,original.enabled);
 // Save unchanged: stable schema version.
 await save();saved=JSON.parse(cli("echo wp_json_encode(get_option('netchat_service_forms'));"));assert.equal(saved.installed[id].schema_version,schema.schema_version+1);
 // A stale tab must preserve edits and report conflict.
 const stale=await context.newPage();await stale.goto(url+'&form='+id);await stale.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));
 await page.locator('[data-editor-tab="texts"]').click();
 await field('texts[intro]').fill('Вторая правка для проверки конфликта.');await save();
 await stale.locator('[name="texts[title]"]').fill('Несохранённое название второй вкладки');await stale.getByRole('button',{name:'Сохранить форму',exact:true}).click();await stale.locator('[data-editor-form]>[role="alert"]').waitFor();assert.match(await stale.locator('[role="alert"]').textContent(),/другой вкладке/);assert.equal(await stale.locator('[name="texts[title]"]').inputValue(),'Несохранённое название второй вкладки');await stale.close({runBeforeUnload:false});
 const beforeGlobals=JSON.parse(cli("echo wp_json_encode(get_option('netchat_service_forms'));"));
 await page.goto(url+'&view=settings');await page.getByRole('button',{name:'Сохранить настройки'}).click();await page.waitForURL(u=>u.searchParams.get('saved')==='1');const afterGlobals=JSON.parse(cli("echo wp_json_encode(get_option('netchat_service_forms'));"));assert.deepEqual(afterGlobals.installed,beforeGlobals.installed);assert.deepEqual(afterGlobals.bindings,beforeGlobals.bindings);assert.deepEqual(afterGlobals.documents,original.documents);
 await page.goto(url+'&form='+id);await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));await page.locator('[data-editor-tab="reset"]').click();await field('confirm_reset').check();await page.getByRole('button',{name:'Восстановить исходную форму',exact:true}).click();await page.waitForURL(u=>u.searchParams.get('saved')==='1');const reset=JSON.parse(await page.locator('#nc-editor-schema').textContent());assert.equal(reset.title,schema.title);assert.equal(reset.fields[0].label,schema.fields[0].label);assert.equal(reset.schema_version,schema.schema_version+3);
 for(const width of [390,768,1440]){await page.setViewportSize({width,height:950});await page.goto(url+'&form='+id);await page.waitForFunction(()=>!!document.querySelector('[data-preview] h3'));assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:'tests/results/forms-editor-'+width+'.png',fullPage:true});}
 await page.goto(url+'&form=demo_test_g_v1');assert.equal(await page.locator('[data-preview-step] option').count(),2);
 const publicPage=await context.newPage();await publicPage.goto(origin+'/netchat-test/');await publicPage.waitForFunction(()=>!!window.netchatServices);assert.equal(await publicPage.evaluate(()=>netchatServices.forms.find(f=>f.id==='demo_test_a_v1').title),schema.title);await publicPage.close();
 assert.deepEqual(errors,[]);console.log('PASS forms editor: catalog/search; isolated edits; conditional preview; reorder; dirty navigation; retained server errors; save/version; conflict; global settings; reset; three widths; public form.');
}finally{
 await browser.close();
 cli("update_option('netchat_service_forms',json_decode(base64_decode('"+initial+"'),true),false);");
 if(session)session.close();
}
