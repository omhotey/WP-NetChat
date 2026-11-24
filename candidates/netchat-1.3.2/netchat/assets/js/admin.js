/** Canonical MD3 controls with fresh settings adapter and safe live preview. */
(function(){
 'use strict';
 function init(){
  var root=document.querySelector('.netchat-standalone-page');if(!root||root.dataset.ncAdminReady)return;root.dataset.ncAdminReady='1';
  var cfg=window.netchatAdmin;if(!cfg||!window.NetChatValidation)return;
  var copy=function(v){return JSON.parse(JSON.stringify(v));},saved=copy(cfg.config),pending=false,dirty=false,device='desktop',previewOpen=false;
  var $=function(id){return document.getElementById(id);},all=function(s){return Array.prototype.slice.call(root.querySelectorAll(s));};
  var names={size:'nc-opt-sz',animation:'nc-opt-sp',color:'nc-opt-cl',hint:'nc-opt-h',hint_text:'nc-opt-ht',hint_delay:'nc-opt-hd',hint_mobile:'nc-opt-hmobile',button_delay:'nc-opt-bd',button_scroll_percent:'nc-opt-bscroll',hint_scroll_percent:'nc-opt-hscroll',desktop:'nc-opt-d',mobile:'nc-opt-m',order:'nc-order',launcher_mode:'nc-launcher-mode'};
  var ranges={size:[40,80],animation:[0,1000],hint_delay:[0,600],button_delay:[0,600],button_scroll_percent:[0,100],hint_scroll_percent:[0,100]};
  var flag=['hint','hint_mobile','desktop','mobile'];
  function stable(v){if(Array.isArray(v))return '['+v.map(stable).join(',')+']';if(v&&typeof v==='object')return '{'+Object.keys(v).sort().map(function(k){return JSON.stringify(k)+':'+stable(v[k]);}).join(',')+'}';return JSON.stringify(v);}
  function text(key){return cfg.i18n[key]||key;}
  function currentLanguage(){var b=root.querySelector('.nc-lang-opt.active');return b?b.dataset.lang:'ru';}
  function collectState(){
   var state=copy(saved);state.enabled={};state.links={};state.position={};
   all('#netchat-channels .nc-messenger-card').forEach(function(card){var key=card.dataset.key;state.enabled[key]=card.querySelector('input[type="checkbox"]').checked?1:0;state.links[key]=card.querySelector('.nc-input').value;});
   Object.keys(names).forEach(function(key){var el=$(names[key]);state[key]=flag.indexOf(key)>=0?(el.checked?1:0):el.value;});
   ['top','right','bottom','left'].forEach(function(key){state.position[key]=$('nc-pos-'+key).value;});state.lang=currentLanguage();return state;
  }
  function invalid(el){if(el){el.setAttribute('aria-invalid','true');el.focus();}throw new Error(text('invalid'));}
  function buildPayload(state,validate){
   var data=copy(state);var first=null;
   all('input,textarea').forEach(function(el){el.removeAttribute('aria-invalid');});
   Object.keys(ranges).forEach(function(key){var val=String(data[key]),range=ranges[key];if(!/^\d+$/.test(val)||Number(val)<range[0]||Number(val)>range[1]){first=first||$(names[key]);}else data[key]=Number(val);});
   ['top','right','bottom','left'].forEach(function(key){var val=String(data.position[key]);if(val!==''&&(!/^\d+$/.test(val)||Number(val)>10000)){first=first||$('nc-pos-'+key);}else data.position[key]=val===''?'':String(Number(val));});
   Object.keys(data.links).forEach(function(key){var res=NetChatValidation.normalize(key,data.links[key]),input=root.querySelector('.nc-messenger-card[data-key="'+key+'"] .nc-input');if(!res.ok){if(input)input.setAttribute('aria-invalid','true');first=first||input;}else data.links[key]=res.url;});
   var order=String(data.order).split(',').map(function(v){return v.trim();}),keys=Object.keys(cfg.defaults.enabled);
   if(order.length!==keys.length||new Set(order).size!==keys.length||order.some(function(k){return keys.indexOf(k)<0;}))first=first||$('nc-order');else data.order=order.join(',');
   if(!/^#[a-f0-9]{6}$/i.test(data.color))first=first||$('nc-opt-cl');
   if(first){if(validate!==false)invalid(first);throw new Error(text('invalid'));}return data;
  }
  function showNotice(message,type){var container=$('netchat-notice-container');while(container.firstChild)container.removeChild(container.firstChild);var notice=document.createElement('div');notice.className='netchat-notice '+type;notice.textContent=message;notice.setAttribute('role',type==='error'?'alert':'status');container.appendChild(notice);}
  function markDirty(){try{dirty=stable(buildPayload(collectState(),false))!==stable(saved);}catch(e){dirty=true;}var status=$('nc-draft-status');status.dataset.dirty=String(dirty);status.textContent=text(dirty?'dirty':'clean');}
  function applyState(state){
   all('#netchat-channels .nc-messenger-card').forEach(function(card){var key=card.dataset.key;card.querySelector('input[type="checkbox"]').checked=!!state.enabled[key];card.querySelector('.nc-input').value=state.links[key]||'';});
   Object.keys(names).forEach(function(key){var el=$(names[key]);if(flag.indexOf(key)>=0)el.checked=!!state[key];else el.value=state[key];});
   ['top','right','bottom','left'].forEach(function(key){$('nc-pos-'+key).value=state.position[key];});
   all('.nc-lang-opt').forEach(function(b){b.classList.toggle('active',b.dataset.lang===state.lang);});update();
  }
  function decode(value){return value.replace(/&(?:amp|lt|gt|quot|apos|#39|#x[0-9a-f]+|#\d+);/ig,function(entity){var fixed={'&amp;':'&','&lt;':'<','&gt;':'>','&quot;':'"','&apos;':"'",'&#39;':"'"};if(fixed[entity.toLowerCase()])return fixed[entity.toLowerCase()];var hex=/^&#x/i.test(entity),code=parseInt(entity.slice(hex?3:2,-1),hex?16:10);return code>0&&code<=0x10ffff?String.fromCodePoint(code):'\ufffd';});}
  function safeHref(value){value=decode(value).trim();if(/[\x00-\x20\x7f]|%(?:00|0a|0d)/i.test(value))return '';return /^(?:https?:\/\/|mailto:|tel:|#)/i.test(value)?value:'';}
  // Build allowlisted nodes directly. Untrusted markup never reaches innerHTML/DOMParser.
  function safeHint(target,html){
   while(target.firstChild)target.removeChild(target.firstChild);var stack=[target];String(html).split(/(<[^>]*>)/g).forEach(function(token){
    if(!token)return;var tag=token.match(/^<\s*(\/?)\s*(b|strong|i|em|br|a)\b([^>]*)>$/i);
    if(!tag){if(token[0]!=='<')stack[stack.length-1].appendChild(document.createTextNode(decode(token)));return;}
    var name=tag[2].toLowerCase();if(tag[1]){for(var j=stack.length-1;j>0;j--)if(stack[j].tagName.toLowerCase()===name){stack.length=j;break;}return;}
    var node=document.createElement(name);if(name==='a'){var href=tag[3].match(/\bhref\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i),safe=href?safeHref(href[1]||href[2]||href[3]||''):'';if(safe)node.setAttribute('href',safe);node.addEventListener('click',function(e){e.preventDefault();});}
    stack[stack.length-1].appendChild(node);if(name!=='br')stack.push(node);
   });
  }
  function updatePreview(){
   var state=collectState(),stage=$('nc-preview-stage'),widget=$('nc-preview-widget'),empty=$('nc-preview-empty'),menu=$('nc-preview-menu'),hint=$('nc-preview-hint');
   stage.dataset.device=device;var enabled=[];
   all('#netchat-channels .nc-messenger-card').forEach(function(card){var k=card.dataset.key,r=NetChatValidation.normalize(k,state.links[k]);if(state.enabled[k]&&r.ok&&r.url)enabled.push({key:k,label:card.dataset.name,color:card.dataset.color,icon:card.querySelector('.nc-messenger-icon svg')});});
   var order=String(state.order).split(',').map(function(k){return k.trim();});enabled.sort(function(a,b){return order.indexOf(a.key)-order.indexOf(b.key);});
   while(menu.firstChild)menu.removeChild(menu.firstChild);enabled.forEach(function(ch){var item=document.createElement('button');item.type='button';item.setAttribute('aria-label',ch.label);item.title=ch.label;item.style.background=ch.color;item.dataset.channel=ch.key;if(ch.icon)item.appendChild(ch.icon.cloneNode(true));menu.appendChild(item);});
   var elapsed=Number($('nc-preview-time').value),scroll=Number($('nc-preview-scroll').value);$('nc-preview-time-value').textContent=String(elapsed);$('nc-preview-scroll-value').textContent=String(scroll);
   var direct=(state.launcher_mode==='form'||enabled.length===0)&&cfg.formAvailable===true;menu.hidden=direct;
   var available=(enabled.length>0||direct)&&(device==='mobile'?state.mobile:state.desktop)&&elapsed>=Number(state.button_delay)&&scroll>=Number(state.button_scroll_percent);
   widget.hidden=!available;empty.hidden=!!available;empty.textContent=text(enabled.length||direct?'hidden':'empty');$('nc-preview-status').textContent=text(available?'ready':'hidden');
   var size=Math.max(40,Math.min(80,Number(state.size)||56)),color=/^#[a-f0-9]{6}$/i.test(state.color)?state.color:'#2563eb';
   widget.style.setProperty('--nc-preview-size',size+'px');widget.style.setProperty('--nc-preview-color',color);widget.style.transitionDuration=Math.max(0,Math.min(1000,Number(state.animation)||0))+'ms';
   var left=state.position.left!=='',top=state.position.top!=='';widget.dataset.left=String(left);widget.dataset.top=String(top);widget.style.left=left?Math.min(48,Number(state.position.left)||0)+'px':'auto';widget.style.right=left?'auto':Math.min(48,state.position.right===''?20:(Number(state.position.right)||0))+'px';widget.style.top=top?Math.min(48,Number(state.position.top)||0)+'px':'auto';widget.style.bottom=top?'auto':Math.min(48,state.position.bottom===''?30:(Number(state.position.bottom)||0))+'px';
   var offset=Math.min(48,Number(top?state.position.top:state.position.bottom)||0);menu.style.maxHeight=Math.max(40,stage.clientHeight-offset-size-20)+'px';hint.style.maxWidth=Math.max(40,Math.min(180,stage.clientWidth-Math.min(48,Number(left?state.position.left:state.position.right)||0)-12))+'px';hint.style.maxHeight=Math.max(40,stage.clientHeight-offset-size-28)+'px';
   menu.hidden=direct||!previewOpen;$('nc-preview-toggle').setAttribute('aria-expanded',String(!direct&&previewOpen));
   safeHint(hint,state.hint_text);hint.hidden=!available||!state.hint||!state.hint_text||previewOpen||(device==='mobile'&&!state.hint_mobile)||elapsed<Number(state.hint_delay)||scroll<Number(state.hint_scroll_percent);
  }
  function updateOrder(){
   var list=$('netchat-channels'),order=$('nc-order').value.split(',').map(function(k){return k.trim();});
   order.forEach(function(key,index){var row=list.querySelector('.nc-messenger-card[data-key="'+key+'"]');if(!row)return;if(list.children[index]!==row)list.insertBefore(row,list.children[index]||null);row.querySelector('[data-direction="up"]').disabled=index===0;row.querySelector('[data-direction="down"]').disabled=index===order.length-1;});
  }
  function syncOrder(){ $('nc-order').value=all('#netchat-channels .nc-messenger-card').map(function(card){return card.dataset.key;}).join(',');update(); }
  var dragged=null,list=$('netchat-channels');
  list.addEventListener('dragstart',function(e){var handle=e.target.closest('.nc-drag-handle');if(!handle){e.preventDefault();return;}dragged=handle.closest('.nc-messenger-card');e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',dragged.dataset.key);dragged.classList.add('nc-dragging');});
  list.addEventListener('dragover',function(e){if(!dragged)return;e.preventDefault();e.dataTransfer.dropEffect='move';var target=e.target.closest('.nc-messenger-card');if(target&&target!==dragged){var rect=target.getBoundingClientRect();list.insertBefore(dragged,e.clientY<rect.top+rect.height/2?target:target.nextSibling);}});
  list.addEventListener('drop',function(e){if(dragged){e.preventDefault();syncOrder();}});
  list.addEventListener('dragend',function(){if(dragged){dragged.classList.remove('nc-dragging');syncOrder();dragged=null;}});
  function update(){
   all('#netchat-channels .nc-messenger-card').forEach(function(card){var cb=card.querySelector('input[type="checkbox"]'),input=card.querySelector('.nc-input'),valid=NetChatValidation.normalize(card.dataset.key,input.value).ok;card.querySelector('.nc-messenger-input').classList.toggle('visible',cb.checked||!valid);input.setAttribute('aria-invalid',String(!valid));card.querySelector('.nc-input-status').textContent=valid?'':'⚠';});
   $('nc-greeting-fields').classList.toggle('hidden',!$('nc-opt-h').checked);$('nc-greeting-fields').classList.toggle('visible',$('nc-opt-h').checked);
   all('.nc-pos-btn').forEach(function(b){var active=b.dataset.value===($('nc-pos-left').value!==''?'left':'right');b.classList.toggle('nc-pos-active',active);b.setAttribute('aria-pressed',String(active));});
   updateOrder();updatePreview();markDirty();
  }
  async function saveSettings(){
   if(pending)return;var state=collectState(),payload;try{payload=buildPayload(state);}catch(error){showNotice(error.message,'error');return;}
   var snapshot=JSON.stringify(state),langBefore=saved.lang;pending=true;$('nc-save-btn').disabled=true;$('nc-reset-btn').disabled=true;$('nc-save-btn').textContent=text('saving');
   try{
    var body=new URLSearchParams();body.set('action','netchat_save_settings');body.set('_wpnonce',cfg.nonce);body.set('revision',String(cfg.revision));body.set('settings',JSON.stringify(payload));
    var response=await fetch(cfg.ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()});var result=await response.json();
    if(!response.ok||!result.success){var fail=new Error(result.data&&result.data.message||text('error'));fail.conflict=response.status===409;throw fail;}
    if(!result.data||!Number.isInteger(result.data.revision)||!result.data.config)throw new Error(text('error'));
    cfg.revision=result.data.revision;$('nc-revision').value=String(cfg.revision);saved=copy(result.data.config);cfg.config=copy(saved);
    if(JSON.stringify(collectState())===snapshot)applyState(saved);else update();
    showNotice((result.data.message||text('saved'))+(langBefore!==saved.lang?' '+text('reload_language'):''),'success');
   }catch(error){showNotice(error.conflict?text('conflict'):(error.message||text('error')),'error');markDirty();}
   finally{pending=false;$('nc-save-btn').disabled=false;$('nc-reset-btn').disabled=false;$('nc-save-btn').textContent=text('save');}
  }
  function resetDraft(){if(pending||!window.confirm(text('reset')))return;applyState(copy(cfg.defaults));showNotice(text('reset_done'),'success');}
  function toolbar(action){var ta=$('nc-opt-ht'),start=ta.selectionStart,end=ta.selectionEnd,selected=ta.value.slice(start,end),insert='';
   if(action==='bold')insert='<b>'+selected+'</b>';if(action==='italic')insert='<i>'+selected+'</i>';if(action==='break')insert='<br>';if(action==='link'){var href=window.prompt(text('link'),'https://');if(href===null)return;href=safeHref(href);if(!href){showNotice(text('link'),'error');return;}insert='<a href="'+href.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')+'">'+(selected||'текст')+'</a>';}
   ta.value=ta.value.slice(0,start)+insert+ta.value.slice(end);ta.focus();ta.setSelectionRange(start+insert.length,start+insert.length);update();
  }
  root.addEventListener('input',function(e){if(e.target.closest('.nc-preview-slider'))updatePreview();else update();});root.addEventListener('change',update);
  root.addEventListener('click',function(e){
   var b=e.target.closest('button');if(!b)return;
   if(b.classList.contains('nc-card-move')){var card=b.closest('.nc-messenger-card'),other=b.dataset.direction==='up'?card.previousElementSibling:card.nextElementSibling;if(other){if(b.dataset.direction==='up')list.insertBefore(card,other);else list.insertBefore(other,card);syncOrder();if(b.disabled)b=card.querySelector('[data-direction="'+(b.dataset.direction==='up'?'down':'up')+'"]');b.focus();}}
   else if(b.classList.contains('nc-pos-btn')){var left=b.dataset.value==='left',from=$('nc-pos-'+(left?'right':'left')),to=$('nc-pos-'+(left?'left':'right'));if(to.value==='')to.value=from.value||'20';from.value='';update();}
   else if(b.classList.contains('nc-lang-opt')){all('.nc-lang-opt').forEach(function(other){other.classList.toggle('active',other===b);});update();}
   else if(b.classList.contains('nc-editor-btn')){e.preventDefault();toolbar(b.dataset.action);}
   else if(b.classList.contains('nc-preview-device')){device=b.dataset.device;all('.nc-preview-device').forEach(function(other){other.classList.toggle('active',other===b);});updatePreview();}
  });
  $('nc-save-btn').addEventListener('click',saveSettings);$('nc-reset-btn').addEventListener('click',resetDraft);$('nc-preview-toggle').addEventListener('click',function(){previewOpen=!previewOpen;updatePreview();});
  window.addEventListener('resize',updatePreview);
  window.addEventListener('beforeunload',function(e){if(dirty){e.preventDefault();e.returnValue='';}});
  window.NetChatAdminUI={collectState:collectState,buildPayload:buildPayload,applyState:applyState,updatePreview:updatePreview,safeHint:safeHint,safeHref:safeHref,save:saveSettings,reset:resetDraft,isDirty:function(){return dirty;}};
  update();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
