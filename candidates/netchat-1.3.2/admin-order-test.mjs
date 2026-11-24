import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { chromium } from 'file:///C:/Users/omhotey/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const root=import.meta.dirname, source=path.join(root,'netchat');
const input=JSON.parse(fs.readFileSync(path.join(root,'admin-test.json'),'utf8'));
const read=p=>fs.readFileSync(path.join(source,p),'utf8');
const browser=await chromium.launch({channel:'msedge',headless:true});
try {
 for(const width of [1440,390,320]){
  const context=await browser.newContext({viewport:{width,height:950},hasTouch:width<500});const page=await context.newPage();
  await page.route('**/*',route=>route.abort());
  await page.setContent('<!doctype html><html lang="ru"><head><meta charset="utf-8"></head><body>'+input.html+'</body></html>');
  await page.addStyleTag({content:'body{margin:0;font:14px Arial}'+read('assets/css/admin.css')+read('assets/css/admin-preview.css')});
  await page.evaluate(config=>{window.netchatAdmin=config;window.confirm=()=>true;window.fetch=async(url,opts)=>{let p=new URLSearchParams(opts.body),settings=JSON.parse(p.get('settings'));window.savedTest=settings;return{ok:true,json:async()=>({success:true,data:{revision:2,config:settings,message:'ok'}})};};},input.config);
  await page.addScriptTag({content:read('assets/js/validation.js')});await page.addScriptTag({content:read('assets/js/admin.js')});
  assert.equal(await page.locator('.nc-card-order').count(),0);assert.equal(await page.locator('.nc-messenger-card').count(),8);
  const first=await page.locator('.nc-messenger-card').first().getAttribute('data-key');const second=await page.locator('.nc-messenger-card').nth(1).getAttribute('data-key');
  await page.locator('.nc-messenger-card').nth(1).locator('[data-direction=up]').focus();await page.keyboard.press('Enter');
  assert.equal(await page.locator('.nc-messenger-card').first().getAttribute('data-key'),second);
  assert.equal((await page.locator('#nc-order').inputValue()).split(',')[0],second);
  assert.equal(await page.locator('.nc-messenger-card').nth(1).getAttribute('data-key'),first);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,'page fits mobile');
  await page.locator('#nc-save-btn').click();await page.waitForFunction(()=>window.savedTest);
  const saved=await page.evaluate(()=>window.savedTest);assert.equal(saved.order.split(',')[0],second);
  await page.evaluate(s=>NetChatAdminUI.applyState(s),saved);assert.equal(await page.locator('.nc-messenger-card').first().getAttribute('data-key'),second);
  if(width===1440){const handle=page.locator('.nc-messenger-card').first().locator('.nc-drag-handle'),target=page.locator('.nc-messenger-card').nth(2);await handle.dragTo(target);assert.notEqual((await page.locator('#nc-order').inputValue()).split(',')[0],second);}
  console.log('PASS admin order',width);await context.close();
 }
} finally {await browser.close();}
