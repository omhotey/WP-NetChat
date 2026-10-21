/* Service scenarios use the existing NetChat dialog and lead endpoint. Drafts stay in page memory. */
(function(){
 'use strict';
 var cfg=window.netchatServices,api=window.NetChat;if(!cfg||!api)return;
 var forms=cfg.forms||[],drafts={},active=null,origin=null,cta='other',entry='service_cta',opened=false,confirm=null,pending=false,selected='',selectorError='',switchContact=null,modal,dialog,root;
 function el(tag,text,cls){var n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;}
 function button(text,fn,primary){var b=el('button',text,primary?'nc-s-primary':'nc-s-secondary');b.type='button';b.addEventListener('click',fn);return b;}
 function schema(id){return forms.find(function(f){return f.id===id;});}
 function byCode(code){return forms.find(function(f){return f.service_code===code;});}
 function docSignature(){return JSON.stringify(cfg.documents);}
 function fresh(){return {answers:{},contact:{},consent:false,step:1,errors:{},status:'',key:'',signature:'',token:null,tokenPromise:null,dirty:false,docs:docSignature()};}
 function state(){return active?drafts[active]:null;}
 function matches(condition,answers){if(!condition)return true;var v=answers[condition.key];return Array.isArray(v)?v.some(function(x){return condition.values.includes(x);}):condition.values.includes(v);}
 function fields(f,s){return f.fields.filter(function(x){return matches(x.condition,s.answers);});}
 function purge(f,s){f.fields.forEach(function(x){if(!matches(x.condition,s.answers)){delete s.answers[x.key];delete s.errors['answers.'+x.key];}});}
 function steps(f){return (f.question_steps||(f.service_code==='CONSULTATION'?1:2))+1;}
 function targetStep(f,path){if(path.startsWith('answers.')){var field=f.fields.find(function(x){return 'answers.'+x.key===path;});return field?field.step:1;}return steps(f);}
 function uuid(){if(crypto.randomUUID)return crypto.randomUUID();var a=crypto.getRandomValues(new Uint8Array(16));a[6]=(a[6]&15)|64;a[8]=(a[8]&63)|128;var h=Array.from(a,function(v){return v.toString(16).padStart(2,'0');}).join('');return h.slice(0,8)+'-'+h.slice(8,12)+'-'+h.slice(12,16)+'-'+h.slice(16,20)+'-'+h.slice(20);}
 function length(v){return Array.from(v.trim()).length;}
 function value(s,path){if(path==='consent.accepted')return s.consent;var parts=path.split('.');return s[parts[0]][parts[1]];}
 function set(s,path,v){if(path==='consent.accepted')s.consent=v;else {var p=path.split('.');s[p[0]][p[1]]=v;}s.dirty=true;s.status='';delete s.errors[path];}
 function validateField(field,v){
  if(field.type==='multi'){if(field.required&&(!v||!v.length))return 'Выберите хотя бы один вариант';if(v&&v.includes('unknown')&&v.length>1)return 'Выберите вариант из списка';return '';}
  if(field.type==='radio')return field.required&&!v?'Выберите один вариант':'';
  v=typeof v==='string'?v.trim():'';var max=field.max||({text:2000,url:500,short:120}[field.type]||120);
  if(field.required&&!v)return 'Заполните это поле';if(length(v)>max)return 'Не больше '+max+' символов';
  if(field.type==='text'&&field.required&&length(v)<10)return 'Добавьте немного контекста: не менее 10 символов';
  if(field.type==='url'&&v){try{var u=new URL(v);if(!['http:','https:'].includes(u.protocol)||u.username||u.password||/[\s]/.test(v))throw Error();}catch(ignore){return 'Укажите полную ссылку, начинающуюся с http:// или https://';}}
  return '';
 }
 function contactError(s){var c=s.contact,v=(c.value||'').trim();if(!v)return 'Заполните это поле';if(length(v)>254)return 'Не больше 254 символов';
  if(c.channel==='telegram'&&!/^(?:@?|https:\/\/t\.me\/)[A-Za-z][A-Za-z0-9_]{4,31}$/.test(v))return 'Укажите Telegram в формате @username или https://t.me/username';
  if(c.channel==='email'&&!/^[A-Za-z0-9!#$%&'*+\/=?^_`{|}~.-]+@[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?\.[A-Za-z]{2,}$/.test(v))return 'Проверьте адрес email';
  if(c.channel==='phone'&&!/^\+[1-9][0-9]{7,14}$/.test(v.replace(/[ ()-]/g,'')))return 'Укажите телефон с кодом страны, например +7 999 123-45-67';return '';
 }
 function validate(s,f,all){purge(f,s);var errs={};fields(f,s).forEach(function(x){if(!all&&x.step!==s.step)return;var e=validateField(x,s.answers[x.key]);if(e)errs['answers.'+x.key]=e;});
  if(all||s.step===steps(f)){
   [{key:'name',required:true,max:80},{key:'company',max:120},{key:'note',max:200}].forEach(function(x){var e=validateField({type:'short',required:x.required,max:x.max},s.contact[x.key]);if(e)errs['contact.'+x.key]=e;});
   if(!['telegram','email','phone'].includes(s.contact.channel))errs['contact.channel']='Выберите один вариант';else {var e=contactError(s);if(e)errs['contact.value']=e;}
   if(!s.consent)errs['consent.accepted']='Подтвердите согласие на обработку персональных данных';
  }s.errors=errs;return Object.keys(errs).length===0;
 }
 function errorNode(path,s){if(!s.errors[path])return null;var p=el('p',s.errors[path],'nc-s-error');p.id='nc-s-error-'+path.replaceAll('.','-');return p;}
 function fieldNode(field,path,s){var group=el('div',undefined,'nc-s-field'),choices=field.type==='radio'||field.type==='multi',label=el(choices?'legend':'label',field.label),id='nc-s-'+path.replaceAll('.','-');
  label.appendChild(el('span',field.required?'Обязательно':'Необязательно','nc-s-required'));group.appendChild(label);var v=value(s,path);
  if(choices){var box=el('fieldset');box.appendChild(label);Object.entries(field.options).forEach(function(pair){var line=el('label',undefined,'nc-s-choice'),input=el('input');input.type=field.type==='radio'?'radio':'checkbox';input.name=path;input.value=pair[0];input.dataset.path=path;input.checked=field.type==='radio'?v===pair[0]:Array.isArray(v)&&v.includes(pair[0]);var pointerSelection=false;input.addEventListener('pointerdown',function(){pointerSelection=true;});input.addEventListener('keydown',function(){pointerSelection=false;});
    input.addEventListener('change',function(){var next;if(field.type==='radio')next=pair[0];else {next=Array.isArray(value(s,path))?value(s,path).slice():[];next=input.checked?next.concat(pair[0]):next.filter(function(x){return x!==pair[0];});next=pair[0]==='unknown'&&input.checked?['unknown']:next.filter(function(x){return x!=='unknown';});}var oldChannel=s.contact.channel;set(s,path,next);if(path==='contact.channel'&&oldChannel!==next){s.contact.value='';delete s.errors['contact.value'];}purge(schema(active),s);render(false);var replacement=root.querySelector('[data-path="'+path+'"][value="'+pair[0]+'"]');if(replacement)replacement.focus();if(path==='contact.channel'&&pointerSelection){var contactInput=root.querySelector('[data-path="contact.value"]');if(contactInput){contactInput.focus();contactInput.scrollIntoView({block:'center'});}}pointerSelection=false;});
    line.append(input,el('span',pair[1]));box.appendChild(line);});group.appendChild(box);
  }else {var input=el(field.type==='text'?'textarea':'input');input.id=id;input.dataset.path=path;input.name=path;input.value=v||'';label.htmlFor=id;
   if(field.type==='text')input.rows=4;else input.type=field.inputType|| (field.type==='url'?'url':'text');
   input.placeholder=field.placeholder||'';input.autocomplete=field.autocomplete||'off';input.setAttribute('autocapitalize','off');if(field.inputmode)input.inputMode=field.inputmode;
   input.addEventListener('input',function(){set(s,path,input.value);var err=group.querySelector('.nc-s-error');if(err)err.remove();input.removeAttribute('aria-invalid');});group.appendChild(input);
  }
  if(field.helper)group.appendChild(el('p',field.helper,'nc-s-helper'));var error=errorNode(path,s);if(error){group.appendChild(error);group.querySelectorAll('input,textarea').forEach(function(i){i.setAttribute('aria-invalid','true');i.setAttribute('aria-describedby',error.id);});}return group;
 }
 function focusError(){var node=root.querySelector('[aria-invalid="true"]');if(node){node.focus();node.scrollIntoView({block:'center'});}else root.querySelector('h2').focus();}
 function heading(text){var h=el('h2',text);h.id='nc-service-title';h.tabIndex=-1;root.appendChild(h);dialog.setAttribute('aria-labelledby',h.id);return h;}
 function alternatives(){var source=document.getElementById('nc-contact-alternatives');if(!source)return;var clone=source.cloneNode(true);clone.removeAttribute('id');clone.hidden=false;root.appendChild(clone);}
 function render(focus){root.replaceChildren();var h;var originalAlternatives=document.getElementById('nc-contact-alternatives');if(originalAlternatives)originalAlternatives.hidden=true;
  if(confirm){h=heading(confirm==='close'?'Закрыть форму?':'Сменить направление?');root.appendChild(el('p',confirm==='close'?(pending?'Отправка уже началась и продолжится после закрытия. Закрытие не отменяет запрос.':'Введённые данные не будут отправлены'):'При смене направления ответы о задаче будут очищены. Контакты сохранятся до закрытия страницы'));var actions=el('div',undefined,'nc-s-actions');actions.append(button(confirm==='close'?'Продолжить заполнение':'Остаться',function(){confirm=null;render(true);}),button(confirm==='close'?'Закрыть':'Сменить направление',function(){var kind=confirm;confirm=null;if(kind==='close')finishClose();else {var s=state();if(s){s.answers={};s.errors={};s.step=1;s.dirty=Object.values(s.contact).some(Boolean);}active=null;selected='';render(true);}},true));root.appendChild(actions);alternatives();h.focus();return;}
  if(!active){
   h=heading('Какую задачу хотите обсудить?');
   var box=el('fieldset');box.className='nc-s-selector-grid';var legend=el('legend','Выберите направление');legend.className='nc-s-sr-only';box.appendChild(legend);
   forms.forEach(function(f){var l=el('label',undefined,'nc-s-choice nc-s-direction'),i=el('input');i.type='radio';i.name='nc-service-selector';i.value=f.id;i.checked=selected===f.id;i.addEventListener('change',function(){selected=f.id;selectorError='';});var text=el('span');text.append(el('strong',f.service_label),el('small',f.title));l.append(i,text);box.appendChild(l);});root.appendChild(box);
   if(selectorError)root.appendChild(el('p',selectorError,'nc-s-error'));var actions=el('div',undefined,'nc-s-actions');actions.appendChild(button('Продолжить',function(){if(!selected){selectorError='Выберите направление';render(true);return;}selectForm(selected);},true));root.appendChild(actions);alternatives();if(focus!==false)h.focus();return;
  }
  var f=schema(active),s=state();if(s.docs!==docSignature()){s.consent=false;s.docs=docSignature();}
  if(s.success){h=heading('Обращение отправлено');root.append(el('div','✓','nc-s-success-icon'),el('p','Я получил ваше обращение. Свяжусь с вами по указанному контакту, чтобы уточнить задачу и обсудить подходящий формат'),el('p',f.success_text),el('p','Номер обращения: '+s.success),button('Закрыть',finishClose,true));root.setAttribute('role','status');alternatives();if(focus!==false)h.focus();return;}
  root.removeAttribute('role');root.appendChild(el('p','Шаг '+s.step+' из '+steps(f),'nc-s-progress'));h=heading(s.step===steps(f)?'Как с вами связаться':s.step===1?f.title:'Уточнения');
  root.appendChild(el('p',s.step===steps(f)?'Оставьте один удобный контакт, чтобы я мог обсудить с вами задачу':s.step===1?f.intro:'Можно пропустить необязательные вопросы','nc-s-intro'));
  var form=el('form');form.noValidate=true;
  fields(f,s).filter(function(x){return x.step===s.step&&s.step!==steps(f);}).forEach(function(x){form.appendChild(fieldNode(x,'answers.'+x.key,s));});
  (f.notices||[]).forEach(function(n){if((n.before_contact&&s.step!==steps(f))||(!n.before_contact&&s.step!==2))return;if(n.condition&&!matches(n.condition,s.answers))return;if(n.any&&!n.any.some(function(c){return matches(c,s.answers);}))return;var p=el('p',n.text,'nc-s-notice');if(n.channel_link&&cfg.channel_url){var a=el('a','Связаться напрямую');a.href=cfg.channel_url;a.rel='noopener noreferrer';p.append(' ',a);}form.appendChild(p);});
  if(s.step===steps(f)){
   [{key:'name',label:'Как к вам обращаться?',required:true,max:80,autocomplete:'name'},{key:'company',label:'Компания или проект',autocomplete:'organization'},{key:'channel',label:'Где вам удобно получить ответ?',required:true,type:'radio',options:{telegram:'Telegram',email:'Email',phone:'Телефон'}}].forEach(function(x){form.appendChild(fieldNode(Object.assign({type:'short'},x),'contact.'+x.key,s));});
   var channel=s.contact.channel;if(channel){var info={telegram:['Ваш Telegram','@username или https://t.me/username','text','off'],email:['Ваш email','name@example.ru','email','email'],phone:['Ваш телефон','+7 999 123-45-67','tel','tel']}[channel];form.appendChild(fieldNode({type:'short',label:info[0],placeholder:info[1],inputType:info[2],inputmode:channel==='telegram'?'text':info[2],autocomplete:info[3],required:true},'contact.value',s));}
   form.appendChild(fieldNode({type:'short',label:'Когда и как удобнее связаться?',helper:'Например, написать сначала или звонить после 15:00 по Москве'},'contact.note',s));
   var consent=el('label',undefined,'nc-s-choice'),check=el('input');check.type='checkbox';check.checked=s.consent;check.dataset.path='consent.accepted';check.addEventListener('change',function(){set(s,'consent.accepted',check.checked);});consent.append(check,el('span','Я даю согласие на обработку моих персональных данных для ответа на обращение'));form.appendChild(consent);var ce=errorNode('consent.accepted',s);if(ce){check.setAttribute('aria-invalid','true');check.setAttribute('aria-describedby',ce.id);form.appendChild(ce);}
   var docs=el('div',undefined,'nc-s-documents');[['document_url','Согласие на обработку персональных данных'],['policy_url','Политика конфиденциальности']].forEach(function(x){var a=el('a',x[1]);a.href=cfg.documents[x[0]];a.target='_blank';a.rel='noopener noreferrer';docs.appendChild(a);});form.appendChild(docs);
   var trap=el('input');trap.type='text';trap.name='website';trap.tabIndex=-1;trap.autocomplete='off';trap.className='nc-s-trap';trap.setAttribute('aria-hidden','true');trap.setAttribute('aria-label','Website');form.appendChild(trap);
  }
  if(s.status){var status=el('p',s.status,'nc-s-error');status.setAttribute('role','alert');form.appendChild(status);}
  var actions=el('div',undefined,'nc-s-actions');if(s.step>1)actions.appendChild(button('Назад',function(){if(pending)return;s.step--;s.errors={};render(true);}));
  var next=el('button',pending?'Отправляем…':s.step===steps(f)?(s.retry?'Повторить отправку':f.submit_text):'Продолжить','nc-s-primary');next.type='submit';actions.appendChild(next);form.appendChild(actions);
  form.addEventListener('submit',function(e){e.preventDefault();if(pending)return;if(!validate(s,f,s.step===steps(f))){var path=Object.keys(s.errors)[0];s.step=targetStep(f,path);render(false);focusError();return;}if(s.step<steps(f)){s.step++;render(true);}else submit(f,s,trap?trap.value:'');});
  if(pending)form.querySelectorAll('input,textarea,button').forEach(function(n){n.disabled=true;});root.appendChild(form);var change=button('Сменить направление',function(){if(pending)return;switchContact=Object.assign({},s.contact);confirm='switch';render(true);});change.className='nc-s-switch';root.appendChild(change);alternatives();if(focus!==false)h.focus();
 }
 async function request(url,opts){var controller=new AbortController(),timer=setTimeout(function(){controller.abort();},20000);try{var r=await fetch(url,Object.assign({credentials:'same-origin',cache:'no-store',signal:controller.signal},opts)),body=await r.json();if(!r.ok){var e=new Error(body.message);e.status=r.status;e.code=body.code;e.data=body.data||{};throw e;}return body;}finally{clearTimeout(timer);}}
 function token(f,s,force){if(!force&&s.token&&s.token.expires_at*1000>Date.now()+5000)return Promise.resolve(s.token);if(!force&&s.tokenPromise)return s.tokenPromise;s.tokenPromise=request(cfg.endpoint+'/token?form_id='+encodeURIComponent(f.id),{headers:{Accept:'application/json'}}).then(function(t){t.received=performance.now();s.token=t;s.tokenPromise=null;return t;},function(e){s.tokenPromise=null;throw e;});return s.tokenPromise;}
 function context(){var u=new URL(location.href),utm={},ref=null;['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(function(k){var v=u.searchParams.get(k);if(v)utm[k]=Array.from(v).slice(0,100).join('');});try{if(document.referrer)ref=new URL(document.referrer).origin;}catch(ignore){}var pageId=Number(cfg.page_id);return {page_id:Number.isInteger(pageId)&&pageId>0?pageId:null,page_url:u.origin+u.pathname,origin_service:origin,cta_position:cta,entrypoint:entry,referrer_origin:ref,utm:utm,context_token:cfg.context_token};}
 async function submit(f,s,website){var data={service_code:f.service_code,form_id:f.id,schema_version:f.schema_version,answers:JSON.parse(JSON.stringify(s.answers)),contact:Object.assign({},s.contact),consent:{accepted:s.consent},context:context(),website:website};var signature=JSON.stringify(data);if(!s.key||s.signature!==signature){s.key=uuid();s.signature=signature;}data.request_id=s.key;pending=true;render(false);
  try{var result;for(var attempt=0;attempt<2;attempt++){var t=await token(f,s,attempt>0);if(t.documents&&JSON.stringify(t.documents)!==docSignature()){cfg.documents=t.documents;s.docs=docSignature();s.consent=false;var docsError=new Error('Документы обновились. Ознакомьтесь с ними и подтвердите согласие.');docsError.status=400;throw docsError;}
    var wait=2000-(performance.now()-t.received);if(wait>0)await new Promise(function(r){setTimeout(r,wait);});data.token=t.token;try{result=await request(cfg.endpoint,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(data)});break;}catch(e){if(attempt===0&&e.status===403&&e.code==='netchat_leads_token')continue;throw e;}}
   if(!result||!result.success||typeof result.reference!=='string'||!result.reference)throw Error('Missing saved identifier');var saved=result.reference;drafts[f.id]=fresh();drafts[f.id].success=saved;if(opened&&active===f.id)render(true);
  }catch(e){s.retry=true;if(e.status===409){s.key='';s.signature='';}if(e.data&&e.data.fields){s.errors=e.data.fields;s.step=targetStep(f,Object.keys(s.errors)[0]);}s.status=e.status===429?'Слишком много попыток. Попробуйте через '+Math.max(1,Math.ceil((e.data.retry_after||60)/60))+' минут':e.status===403?'Эта форма сейчас недоступна. Используйте другой способ связи':e.status>=500?'Не удалось сохранить обращение. Попробуйте ещё раз позже':e.status?e.message:'Не удалось подтвердить отправку. Проверьте соединение и попробуйте ещё раз';if(opened&&active===f.id){render(false);focusError();}}
  finally{pending=false;if(opened){if(active===f.id&&!confirm){root.querySelectorAll('input,textarea,button').forEach(function(n){n.disabled=false;});var next=root.querySelector('[type="submit"]');if(next)next.textContent=s.retry?'Повторить отправку':f.submit_text;}else render(false);}}
 }
 function selectForm(id){var old=state(),switching=!old&&switchContact!==null,contact=old?Object.assign({},old.contact):switchContact;switchContact=null;active=id;if(!drafts[id]||drafts[id].success||switching)drafts[id]=fresh();if(contact)drafts[id].contact=contact;entry=entry==='widget'?'widget':selected?'service_selector':entry;render(true);token(schema(id),state(),false).catch(function(){});}
 function finishClose(){if(!opened)return;opened=false;confirm=null;switchContact=null;root.hidden=true;dialog.classList.remove('nc-s-dialog');modal.classList.remove('nc-s-overlay');document.getElementById('nc-leads-form').hidden=false;document.getElementById('nc-leads-title').hidden=false;var alt=document.getElementById('nc-contact-alternatives');if(alt)alt.hidden=false;dialog.setAttribute('aria-labelledby','nc-leads-title');api.__closeContactForm();active=null;}
 function open(options){
  options=options||{};modal=document.getElementById('nc-leads-modal');if(!modal)return false;if(opened)return true;
  var explicit=options.service_code||'',requested=options.form&&options.form!=='contact',f=explicit?byCode(explicit):requested?schema(options.form):byCode(cfg.page_service);
  if(!f&&!explicit&&!requested&&!cfg.page_service)f=schema(cfg.primary_form);
  dialog=modal.querySelector('.nc-leads-dialog');root=document.getElementById('nc-service-root');if(!root){root=el('div');root.id='nc-service-root';dialog.appendChild(root);}
  api.__openContactForm({form:'contact',trigger:options.trigger,suppressAutofocus:true});document.getElementById('nc-leads-form').hidden=true;document.getElementById('nc-leads-title').hidden=true;document.getElementById('nc-leads-success').hidden=true;
  dialog.classList.add('nc-s-dialog');modal.classList.add('nc-s-overlay');root.hidden=false;opened=true;confirm=null;selected='';selectorError='';cta=['hero','middle','bottom','widget','other'].includes(options.cta_position)?options.cta_position:'other';entry=cta==='widget'?'widget':'service_cta';origin=f?f.service_code:null;active=null;if(f)selectForm(f.id);else render(true);return true;
 }
 window.NetChatServiceForms={open:open,close:function(){if(!opened)return;if(confirm){confirm=null;render(true);return;}var s=state();if(s&&s.dirty&&!s.success){confirm='close';render(true);}else finishClose();},isOpen:function(){return opened;}};
 function resize(){if(modal)modal.style.setProperty('--nc-s-vh',(window.visualViewport?window.visualViewport.height:window.innerHeight)+'px');}if(window.visualViewport)window.visualViewport.addEventListener('resize',resize);window.addEventListener('resize',resize);api.ready.then(function(){modal=document.getElementById('nc-leads-modal');resize();});
})();
