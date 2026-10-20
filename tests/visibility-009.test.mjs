import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {createRequire} from 'node:module';import {fileURLToPath} from 'node:url';
export const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
export const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const require=createRequire(import.meta.url);
export async function launch(){const {chromium}=require(process.env.PLAYWRIGHT_MODULE_PATH||'C:/Users/omhotey/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');return chromium.launch({channel:process.env.BROWSER_CHANNEL||(process.env.GITHUB_ACTIONS==='true'?'chromium':'msedge'),headless:true});}
export async function setup(browser,cfg={},options={}){
 const context=await browser.newContext({viewport:{width:options.width||1440,height:600},reducedMotion:options.reduced?'reduce':'no-preference'}),page=await context.newPage();
 await page.route('**/*',route=>route.abort());const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.clock.install({time:new Date('2026-10-20T00:00:00Z')});
 await page.clock.pauseAt(new Date('2026-10-20T00:00:01Z'));
 await page.setContent(read('tests/visibility-009-fixture.html'));
 await page.addStyleTag({content:read('assets/css/style.css')});
 await page.addStyleTag({content:read('assets/css/leads.css')});
 const config={speed:0,buttonDelay:0,hintDelay:0,buttonScrollPercent:0,hintScrollPercent:0,hintMobile:0,...cfg};
 await page.evaluate(({config,options})=>{
  window.netchat=config;window.netchatLeads={endpoint:'https://visibility.invalid/leads',errorText:'Ошибка'};
  const t=document.querySelector('#nc-toggle'),h=document.querySelector('#nc-hint'),wrap=document.querySelector('#netchat');
  t.hidden=config.buttonDelay>0||config.buttonScrollPercent>0;wrap.classList.toggle('nc-scroll-wait',t.hidden);
  h.classList.toggle('nc-hint-no-mobile',!config.hintMobile);
  if(options.master===false)h.remove();
  if(options.short)document.querySelector('#page').style.height='10px';
  if(options.mobileOff)wrap.classList.add('nc-hide-mobile');
  if(options.desktopOff)wrap.classList.add('nc-hide-desktop');
  if(options.formFirst)t.setAttribute('data-netchat-open','form');
  if(options.fallback==='mutation')window.ResizeObserver=undefined;
  if(options.fallback==='none'){window.ResizeObserver=undefined;window.MutationObserver=undefined;}
  if(options.restore)scrollTo(0,(document.scrollingElement.scrollHeight-innerHeight)*options.restore/100);
 },{config,options});
 if(options.late)await page.clock.runFor(options.late);
 await page.addScriptTag({content:read('assets/js/leads.js')});
 if(!options.noInit)await page.addScriptTag({content:read('assets/js/front.js')});
 return {page,context,errors};
}
export async function state(page){return page.evaluate(()=>{const t=document.querySelector('#nc-toggle'),h=document.querySelector('#nc-hint');return {button:!t.hidden&&t.getClientRects().length>0,hint:!!h&&!h.hidden&&h.getClientRects().length>0,buttonHidden:t.hidden,hintHidden:!h||h.hidden,open:!!window.NetChat?.isOpen?.(),elapsed:performance.now(),active:document.activeElement.id};});}
export async function progress(page,percent){await page.evaluate(p=>new Promise(resolve=>{const y=Math.max(0,document.scrollingElement.scrollHeight-innerHeight)*p/100;if(Math.abs(scrollY-y)<1){resolve();return;}window.addEventListener('scroll',()=>resolve(),{once:true});scrollTo(0,y);}),percent);await page.clock.runFor(32);}
export async function resize(page,width){await page.evaluate(()=>{window.ncResizeDone=new Promise(resolve=>window.addEventListener('resize',resolve,{once:true}));});await page.setViewportSize({width,height:600});await page.evaluate(()=>window.ncResizeDone);await page.clock.runFor(32);}
export async function at(page,time){const now=await page.evaluate(()=>performance.now());assert.ok(time>=now,'clock moves forward');await page.clock.runFor(time-now);}
export async function run(){
 const browser=await launch();let checks=0;
 function check(v,msg){assert.ok(v,msg);checks++;}
 async function withPage(cfg,options,fn){const env=await setup(browser,cfg,options);try{await fn(env.page);check(env.errors.length===0,'no page errors '+env.errors.join(';'));}finally{await env.context.close();}}
 try{
  await withPage({buttonDelay:5000,hintDelay:10000},{},async p=>{
   check((await state(p)).buttonHidden&&(await state(p)).hintHidden,'initial hidden both');
   await p.locator('#sentinel').focus();await p.evaluate(()=>document.querySelector('#nc-toggle').focus());check((await state(p)).active==='sentinel','hidden button cannot focus');
   await p.evaluate(()=>document.querySelector('#hint-link').focus());check((await state(p)).active==='sentinel','hidden hint link cannot focus');
   await at(p,4999);check(!(await state(p)).button,'button before navigation deadline');await at(p,5000);check((await state(p)).button&&!(await state(p)).hint,'button at deadline independently');
   await at(p,9999);check(!(await state(p)).hint,'hint before navigation deadline');await at(p,10000);check((await state(p)).hint,'hint at deadline from navigation');
  });
  await withPage({buttonDelay:10000,hintDelay:5000},{late:20000},async p=>check((await state(p)).button&&(await state(p)).hint,'late JS uses elapsed navigation, no extra full delay'));
  await withPage({buttonDelay:600000,hintDelay:600000},{},async p=>{await at(p,599999);check(!(await state(p)).button&&!(await state(p)).hint,'600s before');await at(p,600000);check((await state(p)).button&&(await state(p)).hint,'600s deadline');});
  await withPage({buttonDelay:5000,hintDelay:10000,buttonScrollPercent:80,hintScrollPercent:20},{},async p=>{
   await progress(p,25);check(!(await state(p)).button&&!(await state(p)).hint,'scroll alone insufficient');await at(p,5000);check(!(await state(p)).button,'button time alone insufficient');
   await progress(p,90);check((await state(p)).button&&!(await state(p)).hint,'button AND true, hint still time-blocked');await progress(p,0);await at(p,10000);check((await state(p)).hint,'latched hint scroll and navigation time, no restart after button');
   check((await state(p)).button,'button stays after scrolling back');
  });
  await withPage({hintDelay:1000,hintScrollPercent:100},{},async p=>{await at(p,2000);check(!(await state(p)).hint,'hint time alone insufficient');await progress(p,100);check((await state(p)).hint,'end-of-page100%');await progress(p,0);check((await state(p)).hint,'hint scroll latches');});
  for(const fallback of ['normal','mutation','none'])await withPage({buttonScrollPercent:100,hintScrollPercent:100},{short:true,fallback},async p=>check((await state(p)).button&&(await state(p)).hint,'short page100 '+fallback));
  await withPage({buttonScrollPercent:80,hintScrollPercent:90},{restore:100},async p=>check((await state(p)).button&&(await state(p)).hint,'restored scroll at init'));
  for(const width of [320,390,768,769,1440])for(const hintMobile of [0,1])await withPage({hintMobile},{width},async p=>{
   check((await state(p)).button,'mobile preference does not disable button');check((await state(p)).hint===(hintMobile===1||width>768),'mobile gate '+width+'/'+hintMobile);
   await p.clock.runFor(600000);check((await state(p)).hint===(hintMobile===1||width>768),'timers cannot bypass mobile gate');
  });
  await withPage({hintDelay:5000},{width:769},async p=>{
   await resize(p,768);await at(p,5000);check(!(await state(p)).hint,'timer after resize cannot reveal phone-disabled hint');
   await resize(p,769);check((await state(p)).hint,'allowed desktop consumes elapsed time');
   await p.locator('#hint-link').focus();await resize(p,768);check(!(await state(p)).hint&&(await state(p)).active==='nc-toggle','resize hides disabled hint and transfers focus');
  });
  await withPage({hintMobile:1},{width:390,mobileOff:true},async p=>{check(!(await state(p)).button&&!(await state(p)).hint,'hint requires CSS-accessible button');await p.clock.runFor(10000);check(!(await state(p)).hint,'timer cannot bypass mobile button-off');});
  await withPage({},{width:769},async p=>{await p.locator('#hint-link').focus();await p.locator('#sentinel').focus();await resize(p,768);check((await state(p)).active==='sentinel','ordinary focus exit is not stolen on resize');});
  for(const action of ['dismiss','escape','menu','form','api'])await withPage({hintMobile:1},{width:390},async p=>{
   if(action==='dismiss')await p.locator('.nc-hint-close').click();
   if(action==='escape'){await p.locator('#hint-link').focus();await p.keyboard.press('Escape');}
   if(action==='menu'){await p.locator('#nc-toggle').click();await p.keyboard.press('Escape');}
   if(action==='form'){await p.locator('#manual').click();await p.keyboard.press('Escape');}
   if(action==='api'){await p.evaluate(()=>NetChat.open());await p.evaluate(()=>NetChat.close());}
   check((await state(p)).hintHidden,'dismiss/open hides hint '+action);
   await p.clock.runFor(600000);await progress(p,100);await resize(p,1440);check((await state(p)).hintHidden,'dismiss is permanent across timers/resize/scroll '+action);
  });
  await withPage({buttonDelay:5000,hintDelay:5000},{},async p=>{await p.locator('#manual').click();check(await p.locator('#nc-leads-modal').isVisible(),'manual CTA bypasses launcher delays');await p.keyboard.press('Escape');await at(p,5000);check((await state(p)).button&&!(await state(p)).hint,'form dismiss does not cancel button timer');});
  await withPage({hintMobile:1},{master:false},async p=>check((await state(p)).button&&(await state(p)).hintHidden,'master off removes hint only'));
  await withPage({buttonDelay:5000,hintDelay:5000},{formFirst:true},async p=>{await p.evaluate(()=>NetChat.open());check(await p.locator('#nc-leads-modal').isVisible(),'form-first API works before launcher time');await p.keyboard.press('Escape');await at(p,5000);check((await state(p)).button&&(await state(p)).hintHidden,'form-first suppresses hint permanently');});
  await withPage({speed:0},{reduced:true},async p=>check(await p.locator('#netchat').evaluate(el=>getComputedStyle(el).animationName==='none'),'reduced motion preserved'));
  await withPage({buttonDelay:10000,hintDelay:5000},{noInit:true,width:390},async p=>{
   check((await state(p)).buttonHidden&&(await state(p)).hintHidden,'server-like native hidden before JS');
   await p.evaluate(()=>{document.querySelector('#nc-hint').hidden=false;document.querySelector('#nc-hint').classList.add('nc-visible');});
   check(!(await state(p)).hint,'mobile CSS guards even erroneous unhidden DOM before JS');
  });
  console.log(JSON.stringify({status:'PASS',layer:'real_front_and_leads_JS_isolated_browser_fake_clock_no_WordPress',assertions:checks}));
 }finally{await browser.close();}
}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
 if(process.argv.includes('--php-bundle')){
  const modules=['includes/leads-settings.php','includes/helpers.php','includes/normalization.php','includes/admin.php','includes/data-transfer.php','includes/frontend.php'].map(p=>read(p).replace(/^<\?php\s*/,'').replace(/^require_once[^\n]+\n/gm,'').replaceAll('filemtime(','nc009_filemtime(')).join('\n');
  process.stdout.write(read('tests/visibility-009.test.php').replace('/* LOAD_CANDIDATE */',modules));
 }else await run();
}
