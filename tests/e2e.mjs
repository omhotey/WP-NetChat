import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { runLeadsFrontendChecks } from './leads-e2e.mjs';
import { createLabSession } from './lab-session.mjs';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');
const env = Object.fromEntries(fs.readFileSync(path.join(process.env.NETCHAT_LAB_ROOT || 'D:/Project/WordPress-Lab', '.env'), 'utf8').split(/\r?\n/).filter(s => /^[A-Z_]+=/.test(s)).map(s => {
  const i = s.indexOf('='); return [s.slice(0, i), s.slice(i + 1).trim().replace(/^['"]|['"]$/g, '')];
}));
const origin = `http://localhost:${env.WORDPRESS_PORT || 8080}`;
const browser = await chromium.launch({ headless: true, channel: process.env.BROWSER_CHANNEL || 'msedge' });
const context = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1280, height: 900 } });
const page = await context.newPage();
const errors = []; page.on('pageerror', e => errors.push(e.message));
let original;
let labSession;
async function admin() { await page.goto(origin + '/wp-admin/admin.php?page=netchat'); await page.waitForFunction(() => window.netchatAdmin?.config); }
async function post(config, revision) {
  return page.evaluate(async ({ config, revision }) => {
    const r = await fetch(netchatAdmin.restUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': netchatAdmin.restNonce }, body: JSON.stringify({ ...config, revision }) });
    return { status: r.status, data: await r.json() };
  }, { config, revision });
}
async function save() {
  const pending = page.waitForResponse(r => r.url().includes('/netchat/v1/settings') && r.request().method() === 'POST');
  await page.locator('#nc-save-btn').click(); assert.equal((await pending).status(), 200);
  await page.waitForFunction(() => !document.querySelector('#nc-save-btn').disabled);
}
const config = () => page.evaluate(() => structuredClone(netchatAdmin.config));
const revision = () => page.evaluate(() => netchatAdmin.revision);
const checkbox = key => page.locator(`[data-key="${key}"] input[type=checkbox]`);
const input = key => page.locator(`[data-key="${key}"] .nc-input`);
try {
  labSession=createLabSession(process.env.NETCHAT_LAB_ROOT || 'D:/Project/WordPress-Lab');
  const auth=labSession.session;
  await context.addCookies([{name:auth.auth_name,value:auth.auth,url:origin+'/wp-admin/',httpOnly:true,sameSite:'Lax'}, {name:auth.logged_name,value:auth.logged,url:origin+'/',httpOnly:true,sameSite:'Lax'}]);
  await admin(); original = await config();
  // Channel regression fixtures must not depend on the owner's current form setting.
  await page.locator('[data-nc-lead-setting="enabled"]').uncheck();
  const raw = { telegram: 'durov', whatsapp: '+7 (900) 123-45-67', messenger: 'test.page', max: 'https://max.ru/u/test-profile', vk: 'test_profile', viber: '+79001234567', email: 'netchat-test@example.test', phone: '+7 (900) 123-45-67' };
  for (const [key, value] of Object.entries(raw)) { await checkbox(key).check({ force: true }); await input(key).fill(value); }
  for (const id of ['nc-opt-h', 'nc-opt-d', 'nc-opt-m']) await page.locator('#' + id).check({ force: true });
  for (const [id, value] of Object.entries({ 'nc-opt-sp': '0', 'nc-opt-hd': '0' })) await page.locator('#' + id).fill(value);
  await page.locator('#nc-opt-ht').fill('NetChat test greeting');
  await page.locator('.nc-pos-btn[data-value="left"]').click(); await page.locator('#nc-pos-right').fill('0');
  await save(); await admin(); const normalized = await config();
  assert.equal(normalized.animation, 0); assert.equal(normalized.hint_delay, 0); assert.equal(String(normalized.position.left), '0');
  assert.equal(normalized.links.viber, 'viber://chat?number=%2B79001234567'); assert.equal(normalized.links.email, 'mailto:netchat-test@example.test');
  await save(); await admin(); assert.deepEqual(await config(), normalized, 'repeat save');
  for (const key of Object.keys(raw)) await checkbox(key).uncheck({ force: true });
  for (const id of ['nc-opt-h', 'nc-opt-d', 'nc-opt-m']) await page.locator('#' + id).uncheck({ force: true });
  await save(); await admin(); const off = await config();
  assert.deepEqual(off.links, normalized.links); assert.ok(Object.values(off.enabled).every(v => v === 0));
  for (const key of ['desktop', 'mobile', 'hint']) assert.equal(off[key], 0);
  const disabledPage = await context.newPage(); await disabledPage.goto(origin + '/netchat-test/');
  assert.equal(await disabledPage.locator('#netchat').count(), 0, 'all channels off hides launcher'); await disabledPage.close();
  for (const key of Object.keys(raw)) { await checkbox(key).check({ force: true }); assert.equal(await input(key).inputValue(), normalized.links[key]); }
  for (const id of ['nc-opt-h', 'nc-opt-d', 'nc-opt-m']) await page.locator('#' + id).check({ force: true });
  await save(); await admin(); const rev = await revision();
  assert.equal((await post({ ...await config(), color: '#123456' }, rev)).status, 200);
  assert.equal((await post(normalized, rev)).status, 409);
  await admin(); const now = await revision();
  assert.equal((await post({ ...normalized, mobile: 'false' }, now)).status, 400);
  assert.equal((await post({ ...normalized, links: { ...normalized.links, max: 'https://example.test/profile' } }, now)).status, 400);
  const guest = await browser.newContext();
  const denied = await guest.request.post(origin + '/wp-json/netchat/v1/settings', { data: { ...normalized, revision: now } });
  assert.ok([401, 403].includes(denied.status())); await guest.close();
  const front = await context.newPage(); front.on('pageerror', e => errors.push(e.message));
  await front.goto(origin + '/netchat-test/'); await front.waitForFunction(() => window.NetChat);
  await front.locator('#nc-hint.nc-visible').waitFor();
  assert.ok(await front.locator('#nc-hint').evaluate(e=>parseInt(getComputedStyle(e).paddingLeft)>=16),'greeting padding survives reset');
  await front.locator('.nc-hint-close').click();assert.equal(await front.locator('#nc-hint').isVisible(),false,'greeting can be dismissed');
  assert.equal(await front.locator('#netchat').evaluate(e => getComputedStyle(e).left), '0px');
  await front.locator('#nc-toggle').click(); assert.equal(await front.locator('#nc-bubble a').count(), 8);
  assert.ok((await front.locator('#nc-bubble a').evaluateAll(es => es.map(e => getComputedStyle(e).opacity))).every(v => v === '1'));
  assert.equal(await front.locator('.nc-viber').getAttribute('href'), normalized.links.viber);
  await front.keyboard.press('Escape'); assert.equal(await front.locator('#nc-toggle').getAttribute('aria-expanded'), 'false');
  await front.locator('#nc-toggle').focus(); await front.keyboard.press('ArrowDown');
  assert.equal(await front.locator('#nc-bubble a').first().evaluate(e => e === document.activeElement), true);
  await front.keyboard.press('End'); assert.equal(await front.locator('#nc-bubble a').last().evaluate(e => e === document.activeElement), true);
  await front.keyboard.press('Home'); assert.equal(await front.locator('#nc-bubble a').first().evaluate(e => e === document.activeElement), true);
  await front.keyboard.press('Escape'); assert.equal(await front.locator('#nc-toggle').evaluate(e => e === document.activeElement), true);
  await front.setViewportSize({ width: 390, height: 844 }); await front.locator('#nc-toggle').click();
  assert.equal(await front.locator('#netchat').evaluate(e => getComputedStyle(e).left), '0px'); assert.ok(await front.locator('#nc-toggle').isVisible());
  const results = path.join(path.dirname(fileURLToPath(import.meta.url)), 'results'); fs.mkdirSync(results, { recursive: true });
  await front.screenshot({ path: path.join(results, 'mobile-reduced-motion.png') });
  await admin(); assert.equal((await post({ ...normalized, mobile: 0 }, await revision())).status, 200);
  await front.reload(); assert.equal(await front.locator('#nc-toggle').isVisible(), false, 'mobile flag respected');
  await front.setViewportSize({ width: 1280, height: 900 }); assert.equal(await front.locator('#nc-toggle').isVisible(), true, 'desktop flag retained');
  for (const size of [40, 80]) {
    await admin(); assert.equal((await post({ ...normalized, size }, await revision())).status, 200);
    await front.setViewportSize({ width: 390, height: 844 }); await front.reload();
    assert.equal(Math.round((await front.locator('#nc-toggle').boundingBox()).width), size, 'configured mobile size');
  }
  await front.emulateMedia({ reducedMotion: 'no-preference' }); await front.locator('#nc-toggle').click();
  assert.ok((await front.locator('#nc-bubble a').evaluateAll(es => es.map(e => getComputedStyle(e).opacity))).every(v => v === '1'), 'animation zero visible without reduced-motion');
  await front.locator('.nc-phone').scrollIntoViewIfNeeded(); assert.ok(await front.locator('.nc-phone').isVisible(), 'last of eight large channels accessible');
  await admin();
  await page.locator('[data-nc-lead-setting="enabled"]').check();
  await save();
  assert.equal(await page.locator('[data-nc-lead-setting]').count(),1,'Widget page only exposes the form toggle');
  const beforeSettings=await config();
  await page.locator('#netchat-leads-channel .nc-form-settings-link').click();await page.waitForFunction(()=>!!window.netchatAdmin?.config);
  assert.equal(await page.locator('[data-nc-lead-setting="enabled"]').count(),0,'Intake toggle belongs to widget page');
  await page.locator('[data-enquiry-tab="delivery"]').click();
  await page.locator('[data-nc-lead-setting="email.enabled"]').uncheck();
  await page.locator('[data-enquiry-tab="texts"]').click();
  await page.locator('[data-nc-lead-setting="title"]').fill('NetChat browser form');
  await page.locator('[data-enquiry-tab="fields"]').click();await page.locator('[data-enquiry-field="email"]').click();
  await page.locator('[data-nc-lead-setting="fields.email.enabled"]').check();
  await page.locator('[data-nc-lead-setting="fields.email.required"]').check();
  await page.locator('[data-enquiry-field="message"]').click();
  await page.locator('[data-nc-lead-setting="fields.message.enabled"]').check();
  await page.locator('[data-nc-lead-setting="fields.message.required"]').check();
  for (const field of ['name','phone','company']) {await page.locator(`[data-enquiry-field="${field}"]`).click();await page.locator(`[data-nc-lead-setting="fields.${field}.required"]`).uncheck();}
  await page.locator('[data-enquiry-tab="consent"]').click();
  await page.locator('[data-nc-lead-setting="consent.enabled"]').uncheck();
  await save(); await page.reload();await page.waitForFunction(()=>!!window.netchatAdmin?.config);const withForm=await config();
  await page.locator('#netchat-leads-settings').screenshot({path:'tests/results/lead-settings.png'});
  assert.equal(withForm.leads.enabled,1);assert.equal(withForm.leads.title,'NetChat browser form');
  const unchangedWidget=structuredClone(withForm),expectedWidget=structuredClone(beforeSettings);delete unchangedWidget.leads;delete expectedWidget.leads;assert.deepEqual(unchangedWidget,expectedWidget,'Dedicated form save preserves every widget/channel option');
  await admin();
  await page.locator('[data-nc-lead-setting="enabled"]').uncheck();await save();const disabledForm=await config();assert.deepEqual(disabledForm.leads,{...withForm.leads,enabled:0},'Main toggle preserves all detailed settings');await page.locator('[data-nc-lead-setting="enabled"]').check();await save();assert.deepEqual(await config(),withForm);
  page.once('dialog',dialog=>dialog.accept());await Promise.all([page.waitForNavigation({waitUntil:'load'}),page.locator('#nc-reset-btn').click()]);await page.waitForFunction(()=>!!window.netchatAdmin?.config);assert.deepEqual((await config()).leads,{...withForm.leads,enabled:0},'Widget reset preserves dedicated form settings');assert.equal((await post(withForm,await revision())).status,200);
  await page.goto(origin+'/wp-admin/admin.php?page=netchat-lead-settings');await page.waitForFunction(()=>!!window.netchatAdmin?.config);page.once('dialog',dialog=>dialog.accept());await Promise.all([page.waitForNavigation({waitUntil:'load'}),page.locator('#nc-reset-btn').click()]);await page.waitForFunction(()=>!!window.netchatAdmin?.config);const resetForm=await config(),resetWidget=structuredClone(resetForm);delete resetWidget.leads;assert.deepEqual(resetWidget,unchangedWidget,'Form reset preserves every widget/channel setting');assert.equal(resetForm.leads.enabled,withForm.leads.enabled,'Form reset preserves the intake toggle');assert.equal((await post(withForm,await revision())).status,200);await admin();
  const onlyForm={...withForm,launcher_mode:'form',enabled:Object.fromEntries(Object.keys(withForm.enabled).map(k=>[k,0])),desktop:0,mobile:0};
  assert.equal((await post(onlyForm,await revision())).status,200);
  await front.setViewportSize({width:1280,height:900});await front.reload();
  assert.equal(await front.locator('#nc-toggle').isVisible(),false,'launcher flags independent of form');
  assert.equal(await front.locator('#nc-toggle.nc-form').count(),1,'single direct form launcher without messenger channels');
  await runLeadsFrontendChecks(front);
  await admin(); assert.equal((await post({...onlyForm,desktop:1,mobile:1},await revision())).status,200);
  await front.reload();await front.locator('#nc-toggle').focus();await front.keyboard.press('ArrowDown');
  await front.locator('#nc-leads-modal').waitFor({state:'visible'});await front.keyboard.press('Escape');assert.equal(await front.locator('#nc-toggle').evaluate(e=>e===document.activeElement),true);
  await front.keyboard.press('Enter');await front.locator('#nc-leads-modal').waitFor({state:'visible'});
  await front.keyboard.press('Escape');
  assert.equal(await front.locator('#nc-toggle').evaluate(e=>e===document.activeElement),true,'form closes back to original launcher CTA');
  await front.keyboard.press('Escape');
  // Real HTTP intake followed by protected administration and deletion.
  await admin();const mailConfig=await config();mailConfig.leads.email.enabled=1;mailConfig.leads.email.recipients=['browser-mail@example.test'];
  assert.equal((await post(mailConfig,await revision())).status,200);
  await front.reload();
  const testEmail=`browser-${Date.now()}@example.test`;
  const intake=await front.evaluate(async email=>{
    const root=netchatLeads.endpoint;
    const session=await (await fetch(root+'/token',{cache:'no-store'})).json();
    const body={request_key:crypto.randomUUID(),token:session.token,form_key:'contact',email,message:'Real browser intake fixture',context:'BROWSER_INTAKE',consent:false};
    const concurrent=await Promise.all(Array.from({length:3},()=>fetch(root,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})));
    const repeat=await fetch(root,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    return {statuses:concurrent.map(r=>r.status).sort(),repeat:repeat.status,data:await concurrent[0].json()};
  },testEmail);
  assert.deepEqual(intake.statuses,[200,200,201]);assert.equal(intake.repeat,200);assert.equal(intake.data.success,true);
  await page.goto(origin+'/wp-admin/admin.php?page=netchat-leads&s='+encodeURIComponent(testEmail));
  assert.equal(await page.locator('tbody tr').count(),1);
  await page.locator('tbody a').first().click();
  assert.ok((await page.locator('.wrap').innerText()).includes('Real browser intake fixture'));
  const leadId=new URL(page.url()).searchParams.get('lead');
  assert.equal(await page.locator('#nc-lead-mail').getAttribute('open'),null,'successful mail details are secondary');
  await page.locator('#nc-lead-mail summary').click();
  await Promise.all([page.waitForURL('**notice=mail_sent**'),page.getByRole('button',{name:'Повторить уведомление',exact:true}).click()]);
  assert.ok((await page.locator('.nc-leads-page').innerText()).includes('Передано почтовому механизму'));
  const mailbox=await context.request.get(`http://localhost:${env.MAILPIT_PORT || 8025}/api/v1/messages`);
  assert.equal(mailbox.status(),200);
  assert.ok((await mailbox.json()).messages.some(m=>m.Subject.includes('Contact request '+intake.data.reference)),'notification captured by Mailpit with public reference');
  const invalidNonce=await context.request.post(origin+'/wp-admin/admin-post.php',{form:{action:'netchat_lead_action',lead_id:leadId,lead_action:'delete',_wpnonce:'invalid'}});
  assert.equal(invalidNonce.status(),403,'invalid admin nonce rejected');
  await Promise.all([page.waitForURL('**notice=saved**'),page.getByRole('button',{name:'Отметить обработанной',exact:true}).click()]);
  assert.ok((await page.locator('.nc-lead-title').innerText()).includes('Обработана'));
  await page.screenshot({path:'tests/results/lead-detail-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'mobile detail has no horizontal overflow');
  await page.screenshot({path:'tests/results/lead-detail-mobile.png',fullPage:true});
  await page.setViewportSize({width:1280,height:900});
  await page.locator('#nc-lead-delete summary').click();
  await Promise.all([page.waitForURL('**page=netchat-leads&notice=saved'),page.getByRole('button',{name:'Удалить заявку',exact:true}).click()]);
  await page.goto(origin+'/wp-admin/admin.php?page=netchat-leads&s='+encodeURIComponent(testEmail));
  assert.ok((await page.locator('tbody').innerText()).includes('Заявок нет'));
  await page.goto(origin+'/wp-admin/admin.php?page=netchat-leads');
  await page.screenshot({path:'tests/results/lead-list-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'mobile list has no horizontal overflow');
  await page.screenshot({path:'tests/results/lead-list-mobile.png',fullPage:true});
  console.log('PASS live lead: HTTP create/replay, protected list/card/status/delete and settings serializer.');
  assert.deepEqual(errors, []); console.log('PASS browser: all8, repeated save, disabled contacts/flags, zeros, left/mobile, guest/types/conflict, Viber, reduced-motion, Escape');
} finally {
  try { if (original) { await admin(); assert.equal((await post(original, await revision())).status, 200, 'restore original settings'); console.log('Original lab settings restored.'); } }
  finally { await browser.close(); if(labSession)labSession.close(); }
}
