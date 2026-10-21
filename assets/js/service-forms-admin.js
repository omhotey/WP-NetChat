(function () {
    'use strict';
    const root = document.querySelector('.nc-service-admin');
    if (!root) return;
    const tracked = root.querySelector('[data-editor-form]');
    const fromPreview = event => event.composedPath().includes(root.querySelector('.nc-editor-preview'));
    let dirty = false;
    const markDirty = () => {
        dirty = true;
        root.querySelectorAll('[data-dirty-status]').forEach(el => el.textContent = 'Есть несохранённые изменения');
        root.querySelectorAll('.nc-editor-savebar').forEach(el => el.classList.add('is-dirty'));
        const toggle=root.querySelector('[data-editor-toggle]');if(toggle){toggle.disabled=true;toggle.title='Сначала сохраните изменения формы';root.querySelector('[data-state-hint]').textContent='Сохраните изменения, чтобы включить или выключить форму.';}
    };
    if (tracked) {
        tracked.addEventListener('input', event => { if (!fromPreview(event) && !event.target.readOnly) markDirty(); });
        tracked.addEventListener('change', event => { if (!fromPreview(event)) markDirty(); });
        const saveForm = tracked.closest('form');
        saveForm.addEventListener('invalid', event => {
            const panel = event.target.closest('[data-editor-panel]');
            if (panel?.hidden) root.querySelector('[data-editor-tab="' + panel.dataset.editorPanel + '"]').click();
            const details = event.target.closest('details');if(details)details.open=true;
        }, true);
        const notice = document.createElement('p'); notice.setAttribute('role','alert'); notice.hidden = true;
        tracked.prepend(notice);
        saveForm.addEventListener('submit', async event => {
            event.preventDefault();
            const payload = new FormData(saveForm), controls = [...tracked.querySelectorAll('input,textarea,select,button')];
            const disabled = controls.map(control => control.disabled);
            controls.forEach(control => control.disabled = true); notice.hidden = true;
            root.querySelector('[data-dirty-status]').textContent = 'Сохранение…';
            try {
                const response = await fetch(saveForm.getAttribute('action'), {method:'POST',body:payload,credentials:'same-origin'});
                const html = await response.text(), doc = new DOMParser().parseFromString(html,'text/html');
                if (!response.ok || !response.redirected || !doc.querySelector('.nc-service-admin .notice-success')) {
                    const message = doc.querySelector('.wp-die-message')?.textContent.trim();
                    throw new Error(message || 'Не удалось сохранить форму. Проверьте соединение и повторите.');
                }
                const destination = new URL(response.url);
                if (root.querySelector('#nc-editor-schema')) {
                    destination.hash = location.hash;
                    try { sessionStorage.setItem('netchat-editor-position',JSON.stringify({id:new URL(location.href).searchParams.get('form'),open:[...root.querySelectorAll('[data-field-key] details[open]')].map(details=>details.closest('[data-field-key]').dataset.fieldKey),step:root.querySelector('[data-preview-step]').value,scroll:scrollY})); } catch (_) {}
                }
                dirty = false; location.assign(destination.href);
            } catch (error) {
                notice.textContent = error.message; notice.hidden = false;
                controls.forEach((control,index) => control.disabled = disabled[index]);
                markDirty(); notice.scrollIntoView({block:'nearest'});
            }
        });
        window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
        root.querySelector('[data-reset-section] form')?.addEventListener('submit', event => {
            if (dirty && !window.confirm('Несохранённые изменения будут потеряны. Восстановить исходную форму?')) event.preventDefault();
            else dirty = false;
        });
    }
    const search = root.querySelector('#nc-form-search');
    const template = root.querySelector('[name="template_id"]');
    if (template) { const update = () => root.querySelector('[data-blank-steps]').hidden = template.value !== ''; template.addEventListener('change',update); update(); }
    if (search) {
        let filter = 'all';
        const rows = [...root.querySelectorAll('[data-form-row]')];
        const update = () => {
            const query = search.value.trim().toLocaleLowerCase('ru'); let count = 0;
            rows.forEach(row => {
                const matches = filter === 'all' || (filter === 'enabled' && row.dataset.formEnabled === '1') || (filter === 'disabled' && row.dataset.formEnabled === '0') || (filter === 'unbound' && row.dataset.formBound === '0');
                row.hidden = !matches || !row.dataset.formSearch.toLocaleLowerCase('ru').includes(query);
                if (row.hidden) row.querySelector('details').open = false; else count++;
            });
            root.querySelector('#nc-form-empty').hidden = count !== 0;
            root.querySelector('#nc-form-count').textContent = 'Показано ' + count + ' из ' + rows.length;
            root.querySelectorAll('[data-form-filter]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.formFilter === filter)));
        };
        search.addEventListener('input', update);
        root.querySelectorAll('[data-form-filter]').forEach(button => button.addEventListener('click', () => {filter = button.dataset.formFilter; update();}));
        root.querySelector('[data-form-clear]').addEventListener('click', () => {filter = 'all';search.value = '';update();search.focus();});
        const actions = [...root.querySelectorAll('.nc-form-actions')];
        document.addEventListener('click', event => actions.forEach(menu => {if(!menu.contains(event.target))menu.open=false;}));
        actions.forEach(menu => menu.addEventListener('toggle', () => {if(menu.open) actions.forEach(other => {if(other !== menu) other.open = false;});}));
        root.addEventListener('keydown', event => {if(event.key === 'Escape'){const menu = event.target.closest('.nc-form-actions');if(menu?.open){menu.open=false;menu.querySelector('summary').focus();event.preventDefault();}}});
        update();
    }
    const schemaNode = root.querySelector('#nc-editor-schema');
    if (!schemaNode) return;
    const original = JSON.parse(schemaNode.textContent);
    const form = tracked.closest('form'), preview = root.querySelector('[data-preview]');
    const answers = {}, contact = {}, total = (original.question_steps || (original.service_code === 'CONSULTATION' ? 1 : 2)) + 1;
    let consent = false;
    root.classList.add('nc-preview-ready');
    let editorScroll = 0;
    root.querySelector('[data-show-preview]').addEventListener('click',() => { editorScroll=scrollY;root.classList.add('is-preview');root.querySelector('.nc-editor-preview').scrollIntoView({block:'start'});root.querySelector('[data-hide-preview]').focus({preventScroll:true}); });
    root.querySelector('[data-hide-preview]').addEventListener('click',() => { root.classList.remove('is-preview');window.scrollTo(0,editorScroll);root.querySelector('[data-show-preview]').focus({preventScroll:true}); });
    // Enter in a preview input must never submit the administrator's save form.
    preview.addEventListener('keydown',event=>{if(event.key==='Enter'&&event.target.matches('input'))event.preventDefault();});
    const val = name => form.elements.namedItem(name)?.value || '';
    function current() {
        const schema = structuredClone(original);
        ['title','intro','submit_text','success_text'].forEach(key => schema[key] = val('texts[' + key + ']'));
        schema.service_label = val('form_name');
        const byKey = Object.fromEntries(schema.fields.map(field => [field.key, field]));
        schema.fields = [...root.querySelectorAll('[data-field-key]')].map(card => {
            const field = byKey[card.dataset.fieldKey] || {key:card.dataset.fieldKey,type:card.dataset.fieldType,step:Number(card.dataset.fieldStep)}, prefix = 'fields[' + field.key + ']';
            ['label','placeholder','helper'].forEach(key => field[key] = val(prefix + '[' + key + ']'));
            field.required = !!form.elements.namedItem(prefix + '[required]')?.checked;
            if (['radio','multi'].includes(field.type)) field.options = Object.fromEntries([...card.querySelectorAll('[data-option-key]')].map(row => [row.dataset.optionKey,val(prefix + '[options][' + row.dataset.optionKey + ']')]));
            card.querySelector('[data-question-label]').textContent = field.label || 'Без названия';
            card.querySelectorAll('[data-move]').forEach(button => button.setAttribute('aria-label', 'Переместить ' + (button.dataset.move === 'up' ? 'вверх' : 'вниз') + ': ' + field.label));
            return field;
        });
        schema.fields.forEach(field => {
            if (!field.condition) return;
            const source = schema.fields.find(item => item.key === field.condition.key);
            if (!source) return;
            const card = [...root.querySelectorAll('[data-field-key]')].find(item => item.dataset.fieldKey === field.key);
            card.querySelector('.nc-editor-condition').textContent = 'Показывается, если «' + source.label + '»: ' + field.condition.values.map(value => source.options?.[value] || value).join(' / ');
        });
        return schema;
    }
    function node(tag, text, className) { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; if (className) el.className = className; return el; }
    function matches(condition) { const value = answers[condition.key]; return Array.isArray(value) ? value.some(v => condition.values.includes(v)) : condition.values.includes(value); }
    function render() {
        const schema = current(), step = Number(root.querySelector('[data-preview-step]').value);
        updateDependencies(schema);
        root.querySelector('[data-shortcode]').value = '[netchat_button service="' + schema.service_code + '" cta="hero" text="' + schema.title.replace(/["\[\]]/g, '') + '"]';
        schema.fields.forEach(field => { if (field.condition && !matches(field.condition)) delete answers[field.key]; });
        preview.replaceChildren(node('p','Шаг '+step+' из '+total,'nc-s-progress'),node('h3', step === total ? 'Как с вами связаться' : step === 1 ? schema.title : 'Уточнения'));
        preview.append(node('p', step === total ? 'Оставьте один удобный контакт, чтобы я мог обсудить с вами задачу' : step === 1 ? schema.intro : 'Можно пропустить необязательные вопросы','nc-s-intro'));
        if (step === total) {
            function contactInput(key,label,required,placeholder,type) {
                const wrap=node('div',undefined,'nc-s-field'),title=node('label',label),input=node('input');input.id='nc-preview-contact-'+key;title.htmlFor=input.id;
                title.append(node('span',required?'Обязательно':'Необязательно','nc-s-required'));input.type=type||'text';input.placeholder=placeholder||'';input.value=contact[key]||'';input.addEventListener('input',()=>contact[key]=input.value);wrap.append(title,input);preview.append(wrap);
            }
            contactInput('name','Как к вам обращаться?',true);contactInput('company','Компания или проект',false);
            const channelField=node('div',undefined,'nc-s-field'),group=node('fieldset'),title=node('legend','Где вам удобно получить ответ?');title.append(node('span','Обязательно','nc-s-required'));group.append(title);
            Object.entries({telegram:'Telegram',email:'Email',phone:'Телефон'}).forEach(([key,label])=>{const line=node('label',undefined,'nc-s-choice'),input=node('input');input.type='radio';input.name='preview-contact-channel';input.value=key;input.checked=contact.channel===key;let pointer=false;input.addEventListener('pointerdown',()=>pointer=true);input.addEventListener('keydown',()=>pointer=false);input.addEventListener('change',()=>{contact.channel=key;contact.value='';render();const target=pointer?'#nc-preview-contact-value':'[name="preview-contact-channel"][value="'+key+'"]';preview.querySelector(target).focus();});line.append(input,node('span',label));group.append(line);});channelField.append(group);preview.append(channelField);
            if(contact.channel){const info={telegram:['Ваш Telegram','@username или https://t.me/username','text'],email:['Ваш email','name@example.ru','email'],phone:['Ваш телефон','+7 999 123-45-67','tel']}[contact.channel];contactInput('value',info[0],true,info[1],info[2]);}
            contactInput('note','Когда и как удобнее связаться?',false);preview.lastElementChild.append(node('p','Например, написать сначала или звонить после 15:00 по Москве','nc-s-helper'));
            const line = node('label', undefined, 'nc-s-choice'), checkbox = node('input'); checkbox.type = 'checkbox';checkbox.checked=consent;checkbox.addEventListener('change',()=>consent=checkbox.checked);
            line.append(checkbox,node('span','Я даю согласие на обработку моих персональных данных для ответа на обращение'));
            preview.append(line,node('p','Согласие на обработку персональных данных · Политика конфиденциальности','nc-s-documents'),node('p','Предпросмотр не отправляет заявки.','nc-s-helper'));
        } else schema.fields.filter(field => field.step === step && (!field.condition || matches(field.condition))).forEach(field => {
            const wrap = node('div', undefined, 'nc-preview-field nc-s-field'), choices = ['radio','multi'].includes(field.type);
            const title = node(choices ? 'legend' : 'label', field.label);
            title.append(node('span', field.required ? 'Обязательно' : 'Необязательно', 'nc-preview-required nc-s-required'));
            if (choices) {
                const group = node('fieldset'); group.append(title);
                Object.entries(field.options).forEach(([key, text]) => {
                    const line = node('label', undefined, 'nc-preview-choice nc-s-choice'), input = node('input'); input.type = field.type === 'radio' ? 'radio' : 'checkbox'; input.name = 'preview-' + field.key; input.value = key;
                    input.checked = field.type === 'radio' ? answers[field.key] === key : (answers[field.key] || []).includes(key);
                    input.addEventListener('change', () => {
                        if (field.type === 'radio') answers[field.key] = key;
                        else { let values = (answers[field.key] || []).filter(v => v !== key); if (input.checked) values.push(key); values = key === 'unknown' && input.checked ? ['unknown'] : values.filter(v => v !== 'unknown'); answers[field.key] = values; }
                        render(); const replacement = [...preview.querySelectorAll('input')].find(el => el.name === input.name && el.value === key); replacement?.focus();
                    });
                    line.append(input, node('span', text)); group.append(line);
                }); wrap.append(group);
            } else {
                const input = node(field.type === 'text' ? 'textarea' : 'input'); input.id = 'nc-preview-' + field.key; title.htmlFor = input.id; input.placeholder = field.placeholder; input.value = answers[field.key] || '';if(field.type==='text')input.rows=4;
                if (field.type !== 'text') input.type = field.type === 'url' ? 'url' : 'text';
                input.addEventListener('input', () => { answers[field.key] = input.value; }); wrap.append(title, input);
            }
            if (field.helper) wrap.append(node('p', field.helper,'nc-s-helper')); preview.append(wrap);
        });
        (schema.notices || []).forEach(notice => { if ((notice.before_contact ? step === total : step === 2) && (!notice.condition || matches(notice.condition)) && (!notice.any || notice.any.some(matches))) preview.append(node('p', notice.text,'nc-s-notice')); });
        const actions=node('div',undefined,'nc-s-actions');
        if(step>1){const back=node('button','Назад','nc-s-secondary');back.type='button';back.addEventListener('click',()=>{root.querySelector('[data-preview-step]').value=String(step-1);render();});actions.append(back);}
        const next = node('button', step === total ? schema.submit_text : 'Продолжить', 'nc-preview-submit nc-s-primary'); next.type = 'button';
        if (step === total) next.disabled = true;
        else next.addEventListener('click', () => { root.querySelector('[data-preview-step]').value = String(step + 1); render(); });
        actions.append(next);preview.append(actions);
        if (step === total) { const success = node('div', undefined, 'nc-preview-success'); success.append(node('h4', 'После отправки'), node('p', schema.success_text)); preview.append(success); }
        const alternatives = root.querySelector('#nc-preview-alternatives');if(alternatives)preview.append(alternatives.content.cloneNode(true));
    }
    function activate(key) {
        const valid = [...root.querySelectorAll('[data-editor-tab]')].some(tab => tab.dataset.editorTab === key); if (!valid) key = 'texts';
        root.querySelectorAll('[data-editor-panel]').forEach(panel => panel.hidden = panel.dataset.editorPanel !== key);
        root.querySelectorAll('[data-editor-tab]').forEach(tab => { if (tab.dataset.editorTab === key) tab.setAttribute('aria-current','page'); else tab.removeAttribute('aria-current'); });
        root.querySelector('[data-reset-section]').hidden = key !== 'reset';
    }
    root.querySelectorAll('[data-editor-tab]').forEach(tab => tab.addEventListener('click', event => { event.preventDefault(); history.replaceState(null,'',tab.hash); activate(tab.dataset.editorTab); }));
    window.addEventListener('hashchange', () => activate(location.hash.replace('#nc-panel-','')));
    function moves() { root.querySelectorAll('[data-field-list]').forEach(list => { const cards = [...list.children]; cards.forEach((card, index) => { card.querySelector('[data-move="up"]').disabled = index === 0; card.querySelector('[data-move="down"]').disabled = index === cards.length - 1; }); }); }
    function randomKey(prefix, bytes) { return prefix + [...crypto.getRandomValues(new Uint8Array(bytes))].map(value => value.toString(16).padStart(2,'0')).join(''); }
    function hidden(name, value, parent) { const input = node('input'); input.type='hidden'; input.name=name; input.value=value; parent.append(input); }
    function editorInput(name, label, value, max, parent) {
        const wrap = node('label',label,'nc-editor-input'), input = node('input'); input.type='text'; input.name=name; input.value=value; input.maxLength=max;input.required=/\[(label|options)\]/.test(name); wrap.append(input); parent.append(wrap); return input;
    }
    function optionRow(card, key, text) {
        const row = node('div'); row.dataset.optionKey=key;
        editorInput('fields['+card.dataset.fieldKey+'][options]['+key+']','Вариант ответа',text,240,row);
        const remove=node('button','Убрать вариант','button'); remove.type='button'; remove.dataset.deleteOption=''; row.append(remove); return row;
    }
    function newQuestion(step, type) {
        const key=randomKey('q_',8), prefix='fields['+key+']', card=node('article',undefined,'nc-editor-question');
        card.dataset.fieldKey=key; card.dataset.fieldType=type; card.dataset.fieldStep=String(step);
        hidden('order['+step+'][]',key,card); hidden(prefix+'[type]',type,card); hidden(prefix+'[step]',String(step),card);
        const head=node('div',undefined,'nc-editor-question-head'), details=node('details'), summary=node('summary'), caption=node('span','Новый вопрос'); caption.dataset.questionLabel='';
        const types={short:'Короткий ответ',text:'Развёрнутый ответ',url:'Ссылка',radio:'Один вариант',multi:'Несколько вариантов'};
        summary.append(caption,node('small',types[type]));details.open=true;details.append(summary);
        const body=node('div',undefined,'nc-editor-question-body');
        editorInput(prefix+'[label]','Название вопроса','Новый вопрос',240,body);
        if(!['radio','multi'].includes(type))editorInput(prefix+'[placeholder]','Пример ответа в поле','',500,body);
        else hidden(prefix+'[placeholder]','',body);
        editorInput(prefix+'[helper]','Подсказка под вопросом','',1000,body);
        const required=node('label',undefined,'nc-service-check'), checkbox=node('input'); checkbox.type='checkbox'; checkbox.name=prefix+'[required]'; checkbox.value='1';required.append(checkbox,document.createTextNode(' Обязательный вопрос'));body.append(required);
        if(['radio','multi'].includes(type)){
            const options=node('fieldset',undefined,'nc-editor-options');options.append(node('legend','Варианты ответа'));
            options.append(optionRow(card,randomKey('o_',6),'Вариант 1'),optionRow(card,randomKey('o_',6),'Вариант 2'));
            const add=node('button','Добавить вариант','button');add.type='button';add.dataset.addOption='';options.append(add);body.append(options);
        }
        body.append(node('p','Показывается всегда','nc-editor-condition'));
        const remove=node('button','Удалить вопрос','button nc-editor-danger');remove.type='button';remove.dataset.deleteField='';
        const dependency=node('p',undefined,'nc-editor-muted');dependency.dataset.fieldDependency='';dependency.hidden=true;body.append(remove,dependency);
        details.append(body);head.append(details);
        const arrows=node('div',undefined,'nc-editor-reorder');['up','down'].forEach(direction=>{const button=node('button',direction==='up'?'↑':'↓','button');button.type='button';button.dataset.move=direction;arrows.append(button);});head.append(arrows);card.append(head);return card;
    }
    function dependentConditions(schema) {
        const conditions=schema.fields.filter(field=>field.condition).map(field=>({condition:field.condition,label:field.label}));
        (schema.notices||[]).forEach(notice=>{if(notice.condition)conditions.push({condition:notice.condition,label:'сообщение формы'});(notice.any||[]).forEach(condition=>conditions.push({condition,label:'сообщение формы'}));});
        return conditions;
    }
    function updateDependencies(schema) {
        const conditions=dependentConditions(schema);
        root.querySelectorAll('[data-field-key]').forEach(card=>{
            const key=card.dataset.fieldKey, dependents=conditions.filter(item=>item.condition.key===key), remove=card.querySelector('[data-delete-field]'), note=card.querySelector('[data-field-dependency]');
            remove.disabled=schema.fields.length<=1||dependents.length>0;
            note.hidden=!remove.disabled;note.textContent=dependents.length?'От этого вопроса зависит: '+dependents.map(item=>item.label).join(', '):'В форме должен остаться хотя бы один вопрос.';
            const count=card.querySelectorAll('[data-option-key]').length;
            card.querySelectorAll('[data-option-key]').forEach(row=>{
                const dependent=dependents.some(item=>item.condition.values.includes(row.dataset.optionKey)), button=row.querySelector('[data-delete-option]');
                button.disabled=count<=2||dependent;button.title=dependent?'Вариант используется условным вопросом':count<=2?'Нужно хотя бы два варианта ответа':'';
            });
            const add=card.querySelector('[data-add-option]');if(add)add.disabled=count>=12;
        });
        root.querySelectorAll('[data-add-field]').forEach(button=>button.disabled=schema.fields.length>=20);
    }
    root.addEventListener('click',event=>{
        const add=event.target.closest('[data-add-field]');
        if(add){const group=add.closest('[data-step]'),card=newQuestion(Number(add.dataset.addField),group.querySelector('[data-new-type]').value);root.querySelectorAll('[data-field-key] details').forEach(details=>details.open=false);group.querySelector('[data-field-list]').append(card);markDirty();moves();render();card.querySelector('[name$="[label]"]').focus();return;}
        const remove=event.target.closest('[data-delete-field]');
        if(remove){const card=remove.closest('[data-field-key]');if(!window.confirm('Удалить вопрос «'+card.querySelector('[data-question-label]').textContent+'»? Изменение применится после сохранения формы.'))return;if(original.fields.some(field=>field.key===card.dataset.fieldKey))hidden('deleted[]',card.dataset.fieldKey,tracked);card.remove();markDirty();moves();render();return;}
        const addOption=event.target.closest('[data-add-option]');
        if(addOption){const card=addOption.closest('[data-field-key]'),row=optionRow(card,randomKey('o_',6),'Новый вариант');addOption.parentNode.insertBefore(row,addOption);markDirty();render();row.querySelector('input').focus();return;}
        const removeOption=event.target.closest('[data-delete-option]');
        if(removeOption){const row=removeOption.closest('[data-option-key]'),card=row.closest('[data-field-key]'),old=original.fields.find(field=>field.key===card.dataset.fieldKey);if(old?.options&&Object.hasOwn(old.options,row.dataset.optionKey))hidden('fields['+card.dataset.fieldKey+'][deleted_options][]',row.dataset.optionKey,card);row.remove();markDirty();render();}
    });
    root.addEventListener('click', event => {
        const button = event.target.closest('[data-move]'); if (!button) return;
        const card = button.closest('[data-field-key]'), list = card.parentNode;
        if (button.dataset.move === 'up' && card.previousElementSibling) list.insertBefore(card,card.previousElementSibling);
        else if (button.dataset.move === 'down' && card.nextElementSibling) list.insertBefore(card.nextElementSibling,card);
        markDirty(); moves(); render(); card.querySelector('summary').focus();
    });
    tracked.addEventListener('input', event => { if (!fromPreview(event)) render(); });
    tracked.addEventListener('change', event => { if (!fromPreview(event)) render(); });
    root.querySelector('[data-preview-step]').addEventListener('change',render);
    root.querySelector('[data-copy]').addEventListener('click', async () => {
        const text = root.querySelector('[data-shortcode]');
        try { await navigator.clipboard.writeText(text.value); root.querySelector('[data-copy-status]').textContent = 'Шорткод скопирован'; }
        catch (_) { text.focus(); text.select(); root.querySelector('[data-copy-status]').textContent = 'Выделено — нажмите Ctrl+C'; }
    });
    activate(location.hash.replace('#nc-panel-','')); moves(); render();
    try {
        const position=JSON.parse(sessionStorage.getItem('netchat-editor-position')||'null');sessionStorage.removeItem('netchat-editor-position');
        if(position&&position.id===original.id){
            root.querySelectorAll('[data-field-key] details').forEach(details=>details.open=position.open.includes(details.closest('[data-field-key]').dataset.fieldKey));
            root.querySelector('[data-preview-step]').value=position.step;render();requestAnimationFrame(()=>window.scrollTo(0,position.scroll));
        }
    } catch (_) {}
})();
