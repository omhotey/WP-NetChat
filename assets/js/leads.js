/* Single form independent of the floating launcher. */
(function () {
    'use strict';
    var api = window.NetChat = window.NetChat || {}, resolveReady, showForm;
    api.ready = new Promise(function (resolve) { resolveReady = resolve; });
    api.openForm = function (options) { return api.ready.then(function () {
        options=options || {};
        if (Object.prototype.hasOwnProperty.call(options,'service_code') || options.service_selector) return window.NetChatServiceForms ? window.NetChatServiceForms.open(options) : false;
        if (options.form && options.form !== 'contact') return window.NetChatServiceForms && (window.netchatServices.forms || []).some(function(f){return f.id===options.form;}) ? window.NetChatServiceForms.open(options) : false;
        return showForm(options);
    }); };
    function init() {
        var modal = document.getElementById('nc-leads-modal'), form = document.getElementById('nc-leads-form');
        if (!modal || !form) { showForm = function () { return false; }; resolveReady(api); return; }
        // Themes can emit wp_footer inside a wrapper; the dialog must escape its inert ancestor.
        if (modal.parentElement !== document.body) document.body.appendChild(modal);
        var dialog = modal.querySelector('.nc-leads-dialog'), status = document.getElementById('nc-leads-status');
        var success=document.getElementById('nc-leads-success'), heading=document.getElementById('nc-leads-title');
        var submit = form.querySelector('[type="submit"]'), cfg = window.netchatLeads || {}, submitText = submit.textContent;
        var context = '', previousFocus, siblings = [], savedOverflow, pending = false, requestKey = '', signature = '', savedTab = [];
        function text(value, limit) { return typeof value === 'string' ? Array.from(value).slice(0, limit).join('') : ''; }
        function makeKey() {
            if (window.crypto.randomUUID) return window.crypto.randomUUID();
            var bytes = new Uint8Array(16); window.crypto.getRandomValues(bytes);
            return Array.from(bytes).map(function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
        }
        function focusables() {
            return Array.from(dialog.querySelectorAll('a[href],button,input,textarea,select,[tabindex]')).filter(function (el) {
                return !el.disabled && el.tabIndex >= 0 && el.getClientRects().length > 0;
            });
        }
        function lockBackground() {
            savedOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden';
            siblings = Array.from(document.body.children).filter(function (el) { return el !== modal; }).map(function (el) {
                var entry = { el: el, inert: el.hasAttribute('inert'), aria: el.getAttribute('aria-hidden') };
                el.setAttribute('inert', ''); el.setAttribute('aria-hidden', 'true');
                if (!('inert' in el)) Array.from(el.querySelectorAll('a[href],button,input,textarea,select,[tabindex]')).forEach(function (node) {
                    savedTab.push({ el: node, value: node.getAttribute('tabindex') }); node.setAttribute('tabindex', '-1');
                });
                return entry;
            });
        }
        api.__closeContactForm = function () {
            if (modal.hidden) return;
            modal.hidden = true; document.body.style.overflow = savedOverflow;
            if(previousFocus && previousFocus.getAttribute('aria-controls')==='nc-leads-modal')previousFocus.setAttribute('aria-expanded','false');
            siblings.forEach(function (entry) {
                if (!entry.inert) entry.el.removeAttribute('inert');
                if (entry.aria === null) entry.el.removeAttribute('aria-hidden'); else entry.el.setAttribute('aria-hidden', entry.aria);
            });
            savedTab.forEach(function (entry) { if (entry.value === null) entry.el.removeAttribute('tabindex'); else entry.el.setAttribute('tabindex', entry.value); });
            siblings = []; savedTab = [];
            if (previousFocus && previousFocus.isConnected && !previousFocus.closest('[inert]')) {
                if (previousFocus.closest('#nc-bubble') && api.open) api.open();
                previousFocus.focus();
            }
        };
        api.closeForm = function () { if(window.NetChatServiceForms && window.NetChatServiceForms.isOpen()) return window.NetChatServiceForms.close(); return api.__closeContactForm(); };
        showForm = function (options) {
            if (options.form && options.form !== 'contact') return false;
            if (!modal.hidden) return true;
            previousFocus = options.trigger || document.activeElement;
            if (api.close) api.close();
            if (!pending) context = text(options.context, 190);
            modal.hidden = false;
            document.dispatchEvent(new CustomEvent('netchat:form-open'));
            if(previousFocus && previousFocus.getAttribute('aria-controls')==='nc-leads-modal')previousFocus.setAttribute('aria-expanded','true');
            if(success && !success.hidden) { success.hidden=true;form.hidden=false;heading.hidden=false;status.textContent=''; }
            var first = form.querySelector('input:not([name="website"]):not([disabled]),textarea:not([disabled]),select:not([disabled])');
            (options.suppressAutofocus ? dialog : (first || dialog)).focus(); lockBackground(); return true;
        };
        api.__openContactForm = showForm;
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-netchat-service],[data-netchat-open="form"]'); if (!trigger) return;
            var options={ form: 'contact', context: trigger.getAttribute('data-netchat-context') || trigger.getAttribute('data-netchat-source') || '', trigger: trigger };
            if(trigger.hasAttribute('data-netchat-service')) { options.service_code=trigger.getAttribute('data-netchat-service');options.service_selector=true;options.cta_position=trigger.getAttribute('data-netchat-cta') || 'other'; }
            e.preventDefault(); api.openForm(options);
        });
        modal.addEventListener('click', function (e) { if (e.target === modal || e.target.closest('[data-netchat-close]')) api.closeForm(); });
        document.addEventListener('keydown', function (e) {
            if (modal.hidden) return;
            if (e.key === 'Escape') { e.preventDefault(); api.closeForm(); return; }
            if (e.key !== 'Tab') return;
            var nodes = focusables(); if (!nodes.length) { e.preventDefault(); dialog.focus(); return; }
            var first = nodes[0], last = nodes[nodes.length - 1];
            if (e.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
        });
        document.addEventListener('focusin', function (e) { if (!modal.hidden && !dialog.contains(e.target)) { var nodes = focusables(); (nodes[0] || dialog).focus(); } });
        function payload() {
            var url = new URL(window.location.href), utm = {};
            ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(function (key) { var value = url.searchParams.get(key); if (value) utm[key] = text(value,190); });
            var referrer = ''; try { referrer = document.referrer ? new URL(document.referrer).origin : ''; } catch (ignore) {}
            var data = { form_key: 'contact', context: context, page_url: text(url.origin + url.pathname,2048), page_title: text(document.title,2048), referrer: referrer, utm: utm, website: '', consent: false };
            ['name','email','phone','company','message','website'].forEach(function (key) { var field = form.elements.namedItem(key); if (field) data[key] = field.value; });
            var consent = form.elements.namedItem('consent'); if (consent) data.consent = consent.checked; return data;
        }
        async function request(url, options) {
            var controller = window.AbortController ? new AbortController() : null;
            var timer = controller ? setTimeout(function () { controller.abort(); },20000) : null;
            try {
                var response = await fetch(url, Object.assign({ credentials:'same-origin',cache:'no-store',signal:controller ? controller.signal : undefined },options));
                var body = await response.json();
                if (!response.ok) { var error = new Error(typeof body.message === 'string' ? body.message : cfg.errorText); error.status=response.status; error.code=body.code; throw error; }
                return body;
            } finally { clearTimeout(timer); }
        }
        async function send(data, retry) {
            var endpoint = cfg.endpoint.replace(/\/$/,'');
            var session = await request(endpoint + '/token',{ method:'GET', headers:{ Accept:'application/json' } });
            if (!session.token) throw new Error(cfg.errorText); data.token=session.token;
            try { return await request(endpoint,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(data)}); }
            catch (error) { if (!retry && error.status===403 && error.code==='netchat_leads_token') return send(data,true); throw error; }
        }
        form.addEventListener('input', function () { if (!pending) { status.textContent=''; status.removeAttribute('data-error'); } });
        form.addEventListener('submit', async function (e) {
            e.preventDefault(); if (pending || !form.reportValidity()) return;
            var data=payload();
            if (!(data.email || '').trim() && !(data.phone || '').trim()) { status.textContent=cfg.contactText; status.setAttribute('data-error','true'); var contact=form.elements.namedItem('email') || form.elements.namedItem('phone'); if(contact)contact.focus(); return; }
            var nextSignature=JSON.stringify(data); if (!requestKey || signature!==nextSignature) { requestKey=makeKey(); signature=nextSignature; } data.request_key=requestKey;
            var inputs=Array.from(form.querySelectorAll('input,textarea,select')).map(function (el) { var state={el:el,disabled:el.disabled}; el.disabled=true; return state; });
            pending=true; submit.disabled=true; submit.textContent=cfg.busyText; form.setAttribute('aria-busy','true'); status.textContent=''; status.removeAttribute('data-error');
            try { var result=await send(data,false); if(!result.success)throw new Error(cfg.errorText); status.textContent=cfg.successText; form.reset(); requestKey=''; signature=''; form.hidden=true;heading.hidden=true;success.hidden=false;dialog.scrollTop=0;success.focus(); }
            catch (error) { status.textContent=error.status ? error.message : cfg.errorText; status.setAttribute('data-error','true'); }
            finally { inputs.forEach(function (state) { state.el.disabled=state.disabled; }); pending=false; submit.disabled=false; submit.textContent=submitText; form.removeAttribute('aria-busy'); }
        });
        resolveReady(api); document.dispatchEvent(new CustomEvent('netchat:form-ready'));
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init); else init();
})();
