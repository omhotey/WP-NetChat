import assert from 'node:assert/strict';
import {mkdirSync,writeFileSync,readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {createRequire} from 'node:module';
if(process.env.GITHUB_ACTIONS!=='true')throw Error('Hosted synthetic worker required.');
const require=createRequire(import.meta.url),{chromium}=require(process.env.PLAYWRIGHT_MODULE_PATH||'playwright');
const origin=process.env.NETCHAT_TEST_ORIGIN||'http://localhost:8080',output=resolve('dist/release-1.2.2/transfer-browser');mkdirSync(output,{recursive:true});
const browser=await chromium.launch({headless:true}),context=await browser.newContext(),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
let before,originalForms;
async function api(payload,nonce){return page.evaluate(async args=>{const r=await fetch(netchatTransfer.url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'netchat_transfer',_nonce:args.nonce||netchatTransfer.nonce,payload:JSON.stringify(args.payload)})});return{status:r.status,data:await r.json()};},{payload,nonce});}
async function importFile(data){await page.locator('#nc-import-file').setInputFiles({name:'synthetic.json',mimeType:'application/json',buffer:Buffer.from(JSON.stringify(data))});await page.getByText('Файл загружен.',{exact:false}).waitFor();}
try{
 const guest=await context.request.post(origin+'/wp-admin/admin-ajax.php',{form:{action:'netchat_transfer',_nonce:'invalid',payload:JSON.stringify({operation:'export',type:'bundle'})}});assert.equal(guest.status(),403);
 await page.goto(origin+'/wp-login.php');await page.locator('#user_login').fill('netchat_ci');await page.locator('#user_pass').fill('ci-disposable-only');await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');
 await page.goto(origin+'/wp-admin/admin.php?page=netchat-transfer');await page.waitForFunction(()=>window.netchatTransfer);
 const exported=await api({operation:'export',type:'bundle'});assert.equal(exported.status,200);before=exported.data.data;originalForms=await page.locator('#nc-local-forms').textContent();
 const downloading=page.waitForEvent('download');await page.locator('#nc-export').click();const download=await downloading;await download.saveAs(output+'/synthetic-bundle.json');const downloaded=JSON.parse(readFileSync(output+'/synthetic-bundle.json','utf8'));assert.equal(downloaded.product,'netchat');assert.equal(downloaded.schema_version,1);assert.deepEqual(downloaded.settings,before.settings);assert.deepEqual(downloaded.forms,before.forms);
 const formsOnly=await api({operation:'export',type:'forms',ids:[Object.keys(before.forms.installed)[0]]});assert.equal(Object.keys(formsOnly.data.data.forms.installed).length,1);assert.equal(formsOnly.data.data.settings,undefined);
 const settingsOnly=await api({operation:'export',type:'settings'});assert.deepEqual(settingsOnly.data.data.settings,before.settings);assert.equal(settingsOnly.data.data.forms,undefined);
 assert.equal((await api({operation:'export',type:'bundle'},'invalid')).status,403);
 const subscriber=await browser.newContext();const sub=await subscriber.newPage();await sub.goto(origin+'/wp-login.php');await sub.locator('#user_login').fill('netchat_subscriber');await sub.locator('#user_pass').fill('ci-disposable-only');await sub.locator('#wp-submit').click();await sub.waitForURL('**/wp-admin/**');const forbidden=await subscriber.request.post(origin+'/wp-admin/admin-ajax.php',{form:{action:'netchat_transfer',_nonce:'invalid',payload:JSON.stringify({operation:'export',type:'bundle'})}});assert.equal(forbidden.status(),403);await subscriber.close();
 for(const width of [320,390,1440]){await page.setViewportSize({width,height:950});await page.reload();await page.waitForFunction(()=>window.netchatTransfer);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:output+'/transfer-'+width+'.png',fullPage:true});}
 const malformed=await api({operation:'preview',json:'{broken',choices:{}});assert.equal(malformed.status,400);
 assert.equal((await api({operation:'preview',json:'x'.repeat(1048577),choices:{}})).status,400);
 const unsupported={...before,schema_version:99};assert.equal((await api({operation:'preview',json:JSON.stringify(unsupported),choices:{}})).status,400);
 await importFile(downloaded);assert.equal(await page.locator('#nc-settings').isChecked(),false);assert.equal(await page.locator('#nc-documents').isChecked(),false);assert.equal(await page.locator('#nc-apply').isDisabled(),true);
 await page.locator('#nc-preview').focus();await page.keyboard.press('Enter');await page.getByText('Preview готов.',{exact:false}).waitFor();assert.ok((await page.locator('#nc-transfer-preview').textContent()).includes('Создать копию'));assert.equal(await page.locator('#nc-local-forms').textContent(),originalForms,'Preview no writes');
 await page.locator('#nc-apply').focus();await page.keyboard.press('Enter');await page.getByText('Изменения сохранены.',{exact:false}).waitFor();
 await page.reload();const copied=await api({operation:'export',type:'forms'});assert.equal(Object.keys(copied.data.data.forms.installed).length,Object.keys(before.forms.installed).length*2,'Default clone preserves originals');
 const id=Object.keys(before.forms.installed)[0];await importFile(before);for(const checkbox of await page.locator('[data-source] input').all())await checkbox.uncheck();await page.locator(`[data-source="${id}"] input`).check();await page.locator(`[data-source="${id}"] select`).selectOption(id);await page.locator('#nc-preview').click();await page.getByText('Preview готов.',{exact:false}).waitFor();await page.locator('#nc-apply').click();await page.getByText('Изменения сохранены.',{exact:false}).waitFor();
 const updated=await api({operation:'export',type:'forms'});assert.equal(updated.data.data.forms.installed[id].schema_version,before.forms.installed[id].schema_version+1);
 const choices={selected:[id],replace:{[id]:id},apply_settings:false,apply_documents:false,set_primary:false},json=JSON.stringify(before),p=await api({operation:'preview',json,choices}),proof={...p.data.data};delete proof.preview;
 const changedChoices={...choices,apply_settings:true};assert.equal((await api({operation:'apply',json,choices:changedChoices,proof})).status,409,'Changed choices rejected');
 assert.equal((await api({operation:'apply',json:json+' ',choices,proof})).status,409,'Changed source digest rejected');
 assert.equal((await api({operation:'apply',json,choices,proof})).status,200);assert.equal((await api({operation:'apply',json,choices,proof})).status,409,'Stale second apply');
 const settingsChoices={selected:[],replace:{},apply_settings:true,apply_documents:false,set_primary:false};
 async function applySettings(doc){const json=JSON.stringify(doc),p=await api({operation:'preview',json,choices:settingsChoices});assert.equal(p.status,200);const proof={...p.data.data};delete proof.preview;const r=await api({operation:'apply',json,choices:settingsChoices,proof});assert.equal(r.status,200);}
 const settingsDoc=(await api({operation:'export',type:'settings'})).data.data;const percentages=structuredClone(settingsDoc);percentages.settings.button_scroll_percent=25;percentages.settings.hint_scroll_percent=50;await applySettings(percentages);let effective=(await api({operation:'export',type:'settings'})).data.data;assert.equal(effective.settings.button_scroll_percent,25);assert.equal(effective.settings.hint_scroll_percent,50);
 const legacy=structuredClone(percentages);delete legacy.settings.button_scroll_percent;delete legacy.settings.hint_scroll_percent;await applySettings(legacy);effective=(await api({operation:'export',type:'settings'})).data.data;assert.equal(effective.settings.button_scroll_percent,0);assert.equal(effective.settings.hint_scroll_percent,0);assert.deepEqual(effective.settings,settingsDoc.settings);
 for(const key of ['button_scroll_percent','hint_scroll_percent'])for(const value of [null,true,25.5,-1,101,'bad']){const invalid=structuredClone(percentages);invalid.settings[key]=value;assert.equal((await api({operation:'preview',json:JSON.stringify(invalid),choices:settingsChoices})).status,400);}
 await applySettings(settingsDoc);
 assert.deepEqual(errors,[]);writeFileSync(output+'/verification.json',JSON.stringify({download_readback:true,settings_export:true,single_form_export:true,guest:true,non_admin:true,bad_nonce:true,malformed:true,oversize:true,unsupported:true,preview_no_write:true,clone:true,replace:true,stale:true,digest:true,keyboard:true,scroll_roundtrip:true,legacy_scroll_defaults:true,invalid_scroll:true,widths:[320,390,1440]},null,2));
 console.log('PASS transfer browser: auth/nonce, preview, clone/replace, stale/digest, keyboard and mobile.');
}finally{
 // Entire synthetic worker/core/database is removed by compatibility-ci.sh EXIT cleanup.
 await context.clearCookies();await browser.close();
}
