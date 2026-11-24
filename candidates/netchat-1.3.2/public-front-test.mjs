import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
const ROOT=import.meta.dirname;
const SOURCE=path.join(ROOT,'netchat');
const script=fs.readFileSync(path.join(SOURCE,'assets/js/front.js'),'utf8');
let checks=0;
function check(value,label){assert.ok(value,label);checks++;}
class List {
 constructor(s=''){this.values=new Set(s.split(/\s+/).filter(Boolean));}
 add(...items){items.forEach(v=>this.values.add(v));}remove(...items){items.forEach(v=>this.values.delete(v));}contains(v){return this.values.has(v);}toggle(v,force){const yes=force??!this.values.has(v);if(yes)this.add(v);else this.remove(v);return yes;}
}
class Target {
 constructor(){this.listeners=new Map();}
 addEventListener(type,fn){if(!this.listeners.has(type))this.listeners.set(type,[]);this.listeners.get(type).push(fn);}
 dispatchEvent(event){event.target??=this;event.preventDefault??=function(){this.defaultPrevented=true;};event.stopPropagation??=function(){};for(const fn of this.listeners.get(event.type)||[])fn.call(this,event);return !event.defaultPrevented;}
}
class Element extends Target {
 constructor(tag='div',id='',classes=''){super();this.tagName=tag.toUpperCase();this.id=id;this.classList=new List(classes);this.attrs={};this.children=[];this.parentElement=null;this.hidden=false;this.nodeType=1;this.style={setProperty(k,v){this[k]=v;}};this.scrollHeight=80;this.scrollTop=0;}
 append(...elements){for(const e of elements){e.parentElement=this;this.children.push(e);}}
 getAttribute(key){return this.attrs[key]??null;}setAttribute(key,value){this.attrs[key]=String(value);}hasAttribute(key){return key in this.attrs;}
 contains(node){return node===this||this.children.some(c=>c.contains(node));}
 matches(selector){if(selector.startsWith('#'))return this.id===selector.slice(1);if(selector.startsWith('.'))return this.classList.contains(selector.slice(1));return this.tagName.toLowerCase()===selector.toLowerCase();}
 querySelectorAll(selectors){return this.children.flatMap(c=>[...(selectors.split(',').some(s=>c.matches(s.trim()))?[c]:[]),...c.querySelectorAll(selectors)]);}
 querySelector(selector){return this.querySelectorAll(selector)[0]??null;}
 closest(selectors){if(selectors.split(',').some(s=>this.matches(s.trim())))return this;return this.parentElement?.closest(selectors)??null;}
 focus(){this.document.activeElement=this;}blur(){this.document.activeElement=this.document.body;}
 getClientRects(){let n=this;while(n){if(n.hidden)return [];if(n.id==='netchat'&&(n.classList.contains('nc-hide-all')||(this.document.window.innerWidth<=768?n.classList.contains('nc-hide-mobile'):n.classList.contains('nc-hide-desktop'))))return [];n=n.parentElement;}return [{}];}
 getBoundingClientRect(){return {top:500,bottom:556};}
}
function fixture(config={},options={}) {
 const document=new Target();document.readyState='complete';const window=new Target();let clock=options.start??0,nextId=0;const timers=new Map();
 const html=new Element('html');const body=new Element('body');html.append(body);html.scrollHeight=body.scrollHeight=options.short?300:2600;
 const wrap=new Element('div','netchat','nc-wrap');const toggle=new Element('button','nc-toggle','nc-btn nc-main');toggle.setAttribute('type','button');toggle.setAttribute('aria-expanded','false');const bubble=new Element('div','nc-bubble','nc-bubble');bubble.setAttribute('aria-hidden','true');
 const hint=options.hint===false?null:new Element('div','nc-hint','nc-hint');const dismiss=new Element('button','','nc-hint-close');dismiss.setAttribute('type','button');const hintLink=new Element('a','hint-link');if(hint){hint.hidden=true;hint.append(hintLink,dismiss);}
 const native=[];for(let i=0;i<(options.native??8);i++){const item=new Element('a','link-'+i,'nc-btn nc-item');item.setAttribute('href','https://example.test/'+i);native.push(item);bubble.append(item);}
 let action=null;if(options.action){action=new Element('button','forms','nc-btn nc-item');action.setAttribute('type','button');action.setAttribute('data-netchat-action',options.action);const icon=new Element('svg','action-icon');action.append(icon);bubble.append(action);}
 wrap.append(bubble,toggle);if(hint)wrap.append(hint);body.append(wrap);const sentinel=new Element('button','sentinel');body.append(sentinel);
 document.documentElement=html;document.scrollingElement=html;document.body=body;document.activeElement=body;document.window=window;document.getElementById=id=>[html,...html.querySelectorAll('div,button,a,svg,body')].find(el=>el.id===id)??null;document.querySelector=s=>html.querySelector(s);
 for(const el of [html,...html.querySelectorAll('div,button,a,svg,body')])el.document=document;
 window.innerWidth=options.width??1440;window.innerHeight=600;window.pageYOffset=0;window.netchat={speed:300,hintDelay:0,buttonDelay:0,hintMobile:0,buttonScrollPercent:0,hintScrollPercent:0,...config};
 window.performance={now:()=>clock};window.getComputedStyle=()=>({visibility:'visible'});window.matchMedia=()=>({matches:!!options.reduced});
 const setTimeout=(fn,delay=0)=>{const id=++nextId;timers.set(id,{fn,at:clock+Math.max(0,delay)});return id;};const clearTimeout=id=>timers.delete(id);
 window.requestAnimationFrame=fn=>setTimeout(fn,1);
 const context=vm.createContext({window,document,CustomEvent:class{constructor(type,opts={}){this.type=type;this.detail=opts.detail;}},Date:{now:()=>clock},setTimeout,clearTimeout,console});
 function advance(target){let safety=0;while(true){let next=null;for(const [id,t]of timers)if(t.at<=target&&(!next||t.at<next[1].at))next=[id,t];if(!next)break;if(++safety>1000)throw Error('timer loop');clock=next[1].at;timers.delete(next[0]);next[1].fn();}clock=target;}
 function emit(target,type,props={}){return target.dispatchEvent({type,...props});}
 function click(item){emit(bubble,'click',{target:item});}
 function run(){vm.runInContext(script,context);}
 function scroll(percent){window.pageYOffset=(Math.max(html.scrollHeight,body.scrollHeight)-window.innerHeight)*percent/100;emit(window,'scroll');advance(clock+1);}
 function resize(width){window.innerWidth=width;emit(window,'resize');advance(clock+1);}
 return {document,window,wrap,toggle,bubble,hint,dismiss,hintLink,action,native,sentinel,context,run,emit,click,advance,scroll,resize,clock:()=>clock};
}
let env=fixture();env.run();check(!env.toggle.hidden,'immediate native launcher');check(env.native.length===8&&env.native.every(n=>n.getAttribute('href').startsWith('https://')),'eight normal anchors intact');check(!env.window.NetChat.isOpen(),'initial menu closed');
env.emit(env.toggle,'click');check(env.window.NetChat.isOpen()&&env.bubble.getAttribute('aria-hidden')==='false','toggle opens');check(env.native.every(n=>n.getAttribute('tabindex')==='0'),'open links focusable');
env.native[0].focus();env.emit(env.bubble,'keydown',{key:'ArrowUp'});check(env.document.activeElement===env.native[7],'keyboard arrow wrap');env.emit(env.bubble,'keydown',{key:'Home'});check(env.document.activeElement===env.native[0],'keyboard home');env.emit(env.bubble,'keydown',{key:'End'});check(env.document.activeElement===env.native[7],'keyboard end');
env.emit(env.document,'keydown',{key:'Escape'});check(!env.window.NetChat.isOpen()&&env.document.activeElement===env.toggle,'escape closes restores focus');check(env.native.every(n=>n.getAttribute('tabindex')==='-1'),'closed links not tab stops');
env.window.NetChat.open();env.click(env.native[0]);env.advance(150);check(!env.window.NetChat.isOpen(),'native link closes after activation');env.window.NetChat.open();env.emit(env.document,'click',{target:env.sentinel});check(!env.window.NetChat.isOpen(),'outside closes');
for(const kind of ['pointer','Enter','Space']){
 env=fixture({}, {native:0,action:'netchat-forms-open'});env.run();let events=0;env.document.addEventListener('netchat:action',event=>{events++;check(event.detail.action==='netchat-forms-open','action detail');check(!env.window.NetChat.isOpen()&&env.bubble.getAttribute('aria-hidden')==='true','menu closed before addon event');check(env.document.activeElement===env.toggle,'launcher focus restored before dialog owner takes focus');});
 env.emit(env.toggle,'click');check(env.window.NetChat.isOpen(),'addon-only opens');if(kind!=='pointer')env.emit(env.bubble,'keydown',{target:env.action,key:kind==='Space'?' ':kind});
 // A native button emits click for Enter/Space; no second JavaScript keyboard dispatch.
 env.click(kind==='pointer'?env.action.children[0]:env.action);check(events===1,'one event per native '+kind+' activation');check(!env.hint||env.hint.hidden,'activation dismisses hint');
 env.run();env.window.NetChat.open();env.click(env.action);check(events===2,'repeated init does not duplicate handler');
}
env=fixture({}, {native:8,action:'Bad action()'});env.run();let malformedEvents=0;env.document.addEventListener('netchat:action',()=>malformedEvents++);env.window.NetChat.open();env.click(env.action);check(malformedEvents===0,'malformed action cannot dispatch');const detached=new Element('button','','nc-item');detached.setAttribute('type','button');detached.setAttribute('data-netchat-action','valid');env.click(detached);check(malformedEvents===0,'detached item cannot dispatch');
env=fixture({}, {native:0,action:'netchat-forms-open'});env.action.tagName='A';env.run();env.document.addEventListener('netchat:action',()=>malformedEvents++);env.click(env.action);check(malformedEvents===0,'action requires native button type');
const emptyContext=vm.createContext({window:{},document:{readyState:'complete',getElementById:()=>null}});vm.runInContext(script,emptyContext);check(true,'disconnected missing widget no errors');
env=fixture({buttonDelay:5000,hintDelay:10000});env.run();check(env.toggle.hidden&&env.hint.hidden,'time waits hidden');env.advance(4999);check(env.toggle.hidden,'button before deadline');env.advance(5000);check(!env.toggle.hidden&&env.hint.hidden,'button own deadline');env.advance(10000);check(!env.hint.hidden,'hint own navigation deadline');
env=fixture({buttonDelay:5000,hintDelay:10000,buttonScrollPercent:80,hintScrollPercent:20});env.run();env.scroll(25);check(env.toggle.hidden&&env.hint.hidden,'scroll alone insufficient');env.advance(5000);check(env.toggle.hidden,'time alone insufficient');env.scroll(90);check(!env.toggle.hidden&&env.hint.hidden,'button AND gates');env.scroll(0);env.advance(10000);check(!env.toggle.hidden&&!env.hint.hidden,'latched thresholds retained when scrolling back');
for(const width of [320,390,768,769,1440])for(const mobile of [0,1]){env=fixture({hintMobile:mobile},{width});env.run();check(!env.toggle.hidden,'phone hint preference keeps button');check(env.hint.hidden===(!mobile&&width<=768),'hint mobile gate '+width+'/'+mobile);env.advance(600000);check(env.hint.hidden===(!mobile&&width<=768),'timers cannot bypass mobile gate');}
env=fixture({hintDelay:5000},{width:769});env.run();env.resize(768);env.advance(5000);check(env.hint.hidden,'resize then timer cannot reveal disabled mobile hint');env.resize(769);check(!env.hint.hidden,'return to desktop uses elapsed time');env.hintLink.focus();env.emit(env.hint,'focusin');env.resize(768);check(env.hint.hidden&&env.document.activeElement===env.toggle,'resize transfers hint focus');
for(const kind of ['dismiss','Escape','menu','API']){env=fixture({hintMobile:1},{width:390});env.run();if(kind==='dismiss')env.emit(env.dismiss,'click');else if(kind==='Escape')env.emit(env.hint,'keydown',{key:'Escape'});else if(kind==='menu'){env.emit(env.toggle,'click');env.emit(env.toggle,'click');}else{env.window.NetChat.open();env.window.NetChat.close();}check(env.hint.hidden,'dismiss or open hides hint '+kind);env.advance(600000);env.scroll(100);env.resize(1440);check(env.hint.hidden,'dismiss persists '+kind);}
env=fixture({buttonScrollPercent:100,hintScrollPercent:100},{short:true});env.run();check(!env.toggle.hidden&&!env.hint.hidden,'short page fully viewed');
env=fixture({buttonDelay:10000,hintDelay:20000},{start:30000});env.run();check(!env.toggle.hidden&&!env.hint.hidden,'late initialization uses navigation time');
env=fixture({buttonDelay:600000,hintDelay:600000});env.run();env.advance(599999);check(env.toggle.hidden&&env.hint.hidden,'maximum delay pending');env.advance(600000);check(!env.toggle.hidden&&!env.hint.hidden,'maximum delay reached');
env=fixture({hintMobile:1},{width:390});env.wrap.classList.add('nc-hide-mobile');env.run();check(env.hint.hidden,'hint requires visible launcher');
env=fixture({}, {hint:false});env.run();check(!env.toggle.hidden&&!env.hint,'master hint off no error');
env=fixture({speed:0});env.run();check(env.wrap.classList.contains('nc-no-motion'),'zero animation honored');
const css=fs.readFileSync(path.join(SOURCE,'assets/css/style.css'),'utf8');check(css.includes('prefers-reduced-motion:reduce'),'reduced motion CSS retained');check(css.includes('.nc-hint.nc-hint-no-mobile{display:none!important}'),'native CSS mobile hint guard');check(css.includes('#netchat #nc-toggle[hidden]'),'native hidden CSS guard');check(!css.includes('#netchat .nc-item{background:'),'native gradients not overridden');for(const c of ['telegram','whatsapp','messenger','max','vk','viber','email','phone'])check(css.includes('.nc-btn.nc-'+c),'native channel color '+c);
env=fixture({}, {native:1,action:'netchat-forms-open'});env.toggle.setAttribute('data-netchat-direct','netchat-forms-open');env.run();let directEvents=0;env.document.addEventListener('netchat:action',e=>{if(e.detail.action==='netchat-forms-open')directEvents++;});env.emit(env.toggle,'click');check(directEvents===1&&!env.window.NetChat.isOpen(),'direct form dispatch');
const result={status:'PASS',assertions:checks,layer:'synthetic DOM + fake navigation clock; no browser or WordPress',network_requests:0,source_sha256:Object.fromEntries(['assets/js/front.js','assets/css/style.css'].map(p=>[p,createHash('sha256').update(fs.readFileSync(path.join(SOURCE,p))).digest('hex')]))};console.log(JSON.stringify(result));

