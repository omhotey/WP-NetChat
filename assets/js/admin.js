/**
 * NetChat Admin JS - Material Design 3
 * Author: omhotey
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Collect all form state
       ------------------------------------------------------------------ */
    function collectState() {
        var channels = [];
        var cards = document.querySelectorAll('#netchat-channels .nc-messenger-card');
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            var cb = card.querySelector('input[type="checkbox"]');
            var inp = card.querySelector('.nc-input');
            // Always use current input value
            var val = inp ? inp.value : '';
            channels.push({
                key:     card.getAttribute('data-key'),
                name:    card.getAttribute('data-name'),
                color:   card.getAttribute('data-color'),
                enabled: cb ? cb.checked : false,
                value:   val,
                hint:    card.getAttribute('data-hint') || ''
            });
        }

        var activePosBtn = document.querySelector('.nc-pos-btn.nc-pos-active');
        var isLeft = activePosBtn ? activePosBtn.getAttribute('data-value') === 'left' : false;
        var posRightEl = document.getElementById('nc-pos-right');
        var posBottomEl = document.getElementById('nc-pos-bottom');
        var szEl = document.getElementById('nc-opt-sz');
        var spEl = document.getElementById('nc-opt-sp');
        var clEl = document.getElementById('nc-opt-cl');
        var hEl = document.getElementById('nc-opt-h');
        var htEl = document.getElementById('nc-opt-ht');
        var hdEl = document.getElementById('nc-opt-hd');
        var dEl = document.getElementById('nc-opt-d');
        var mEl = document.getElementById('nc-opt-m');
        var activeLangBtn = document.querySelector('.nc-lang-opt.active');

        return {
            channels:   channels,
            leads:      collectLeads(),
            form_presentation: document.getElementById('nc-form-presentation')?.value || netchatAdmin.config.form_presentation || 'modal',
            form_drawer_side: document.getElementById('nc-form-drawer-side')?.value || netchatAdmin.config.form_drawer_side || 'left',
            launcher_mode: document.querySelector('[name="nc-launcher-mode"]:checked')?.value || netchatAdmin.config.launcher_mode || 'form',
            pos_right:  !isLeft && posRightEl ? posRightEl.value : '',
            pos_bottom: posBottomEl ? posBottomEl.value : '30',
            pos_left:   isLeft ? (posRightEl ? posRightEl.value : '') : '',
            sz:         szEl ? Number(szEl.value) : 56,
            sp:         spEl ? Number(spEl.value) : 300,
            cl:         clEl ? clEl.value : '#2563eb',
            h:          hEl ? hEl.checked : false,
            ht:         htEl ? htEl.value : '',
            hd:         hdEl ? Number(hdEl.value) : 2,
            hint_mobile: document.getElementById('nc-opt-hmobile')?.checked ?? !!netchatAdmin.config.hint_mobile,
            button_delay: document.getElementById('nc-opt-bd')?.value ?? netchatAdmin.config.button_delay ?? 0,
            button_scroll_percent: document.getElementById('nc-opt-bscroll')?.value ?? netchatAdmin.config.button_scroll_percent,
            hint_scroll_percent: document.getElementById('nc-opt-hscroll')?.value ?? netchatAdmin.config.hint_scroll_percent,
            d:          dEl ? dEl.checked : false,
            m:          mEl ? mEl.checked : false,
            lang:       activeLangBtn ? activeLangBtn.getAttribute('data-lang') : 'ru'
        };
    }

    /* ------------------------------------------------------------------
       Build REST payload
       ------------------------------------------------------------------ */
    function buildPayload(s) {
        if (document.querySelector('[data-nc-leads-only]')) {
            var settings = JSON.parse(JSON.stringify(netchatAdmin.config));
            settings.leads = s.leads;
            return settings;
        }
        ['nc-opt-bscroll','nc-opt-hscroll','nc-opt-bd','nc-opt-hd'].forEach(function(id){
            var input=document.getElementById(id);
            if(input&&!input.checkValidity()){input.setAttribute('aria-invalid','true');input.reportValidity();input.focus();throw new Error('Введите целое число от 0 до '+input.max+': '+document.querySelector('label[for="'+id+'"]').textContent);}
            if(input)input.removeAttribute('aria-invalid');
        });
        var data = {
            revision: netchatAdmin.revision,
            leads: s.leads,
            launcher_mode: s.launcher_mode,
            form_presentation: s.form_presentation,
            form_drawer_side: s.form_drawer_side,
            enabled: {},
            links: {},
            position: {
                top:    '',
                right:  s.pos_right !== ''  ? Number(s.pos_right)  : '',
                bottom: s.pos_bottom !== '' ? Number(s.pos_bottom) : 30,
                left:   s.pos_left !== ''   ? Number(s.pos_left)   : ''
            },
            size:       s.sz,
            animation:  s.sp,
            color:      s.cl,
            hint:       s.h ? 1 : 0,
            hint_text:  s.ht,
            hint_delay: s.hd,
            hint_mobile: s.hint_mobile ? 1 : 0,
            button_delay: Number(s.button_delay),
            button_scroll_percent: Number(s.button_scroll_percent),
            hint_scroll_percent: Number(s.hint_scroll_percent),
            desktop:    s.d ? 1 : 0,
            mobile:     s.m ? 1 : 0,
            lang:       s.lang,
            order:      s.channels.map(function (c) { return c.key; }).join(',')
        };

        for (var i = 0; i < s.channels.length; i++) {
            var ch = s.channels[i];
            data.enabled[ch.key] = ch.enabled ? 1 : 0;
            var checked = NetChatValidation.normalize(ch.key, ch.value);
            if (!checked.ok) throw new Error(netchatAdmin.i18n_invalid + ' (' + ch.name + ')');
            data.links[ch.key] = checked.url;
        }

        return data;
    }

    function collectLeads() {
        var config = JSON.parse(JSON.stringify(netchatAdmin.config.leads));
        document.querySelectorAll('[data-nc-lead-setting]').forEach(function (input) {
            var keys = input.getAttribute('data-nc-lead-setting').split('.');
            var target = config;
            for (var i = 0; i < keys.length - 1; i++) target = target[keys[i]];
            var value = input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;
            if (input.getAttribute('data-nc-lead-type') === 'recipients') value = value.split(/[,;\n]+/).map(function(s){return s.trim();}).filter(Boolean);
            else if (input.type === 'number') value = Number(value);
            target[keys[keys.length - 1]] = value;
        });
        return config;
    }

    /* ------------------------------------------------------------------
       REST API save
       ------------------------------------------------------------------ */
    function saveSettings(payload, reloadAfterSave) {
        var saveBtn = document.getElementById('nc-save-btn');
        var resetBtn = document.getElementById('nc-reset-btn');
        if (!saveBtn || saveBtn.disabled) return;
        var s = collectState();
        try { payload = payload || buildPayload(s); }
        catch (error) { showNotice(error.message, 'error'); return; }
        payload.revision = netchatAdmin.revision;
        saveBtn.disabled = true;
        if (resetBtn) resetBtn.disabled = true;
        saveBtn.textContent = netchatAdmin.i18n_saving || 'Saving...';
        fetch(netchatAdmin.restUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': netchatAdmin.restNonce },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok || !body.success) {
                    var error = new Error(body.message || netchatAdmin.i18n_save_err);
                    error.conflict = response.status === 409;
                    throw error;
                }
                return body;
            });
        }).then(function (response) {
            netchatAdmin.revision = response.revision;
            netchatAdmin.config = response.config;
            var willReload = (reloadAfterSave || response.lang_changed) && JSON.stringify(s) === JSON.stringify(collectState());
            window.dispatchEvent(new CustomEvent('netchat-settings-saved', {detail: {reload: !!willReload}}));
            showNotice(response.message || netchatAdmin.i18n_save_ok, 'success');
            if (willReload) {
                location.reload();
            }
        }).catch(function (error) {
            showNotice(error.conflict ? netchatAdmin.i18n_conflict : (error.message || netchatAdmin.i18n_save_err), 'error');
        }).then(function () {
            saveBtn.disabled = false;
            if (resetBtn) resetBtn.disabled = false;
            saveBtn.textContent = netchatAdmin.i18n_save || 'Save settings';
        });
    }

    /* ------------------------------------------------------------------
       Notice / toast
       ------------------------------------------------------------------ */
    function showNotice(msg, type) {
        var container = document.getElementById('netchat-notice-container');
        if (!container) return;

        // Remove previous notices
        var existing = container.querySelectorAll('.netchat-notice');
        for (var i = 0; i < existing.length; i++) existing[i].remove();

        var div = document.createElement('div');
        div.className = 'netchat-notice ' + type;
        div.textContent = msg;
        container.appendChild(div);

        if (type === 'error') return;
        setTimeout(function () {
            div.style.opacity = '0';
            div.style.transition = 'opacity 0.4s';
            setTimeout(function () { div.remove(); }, 400);
        }, 3000);
    }

    /* ------------------------------------------------------------------
       Greeting fields toggle
       ------------------------------------------------------------------ */
    function updateGreetingFields() {
        var hEl = document.getElementById('nc-opt-h');
        var fields = document.getElementById('nc-greeting-fields');
        if (!hEl || !fields) return;

        var checked = hEl.checked;
        fields.classList.toggle('hidden', !checked);
        fields.classList.toggle('visible', checked);
    }

    /* ------------------------------------------------------------------
       Editor toolbar - insert HTML tags (NEVER submit)
       ------------------------------------------------------------------ */
    function handleEditorButton(action) {
        var ta = document.getElementById('nc-opt-ht');
        if (!ta) return;

        ta.focus();
        var start = ta.selectionStart;
        var end   = ta.selectionEnd;
        var sel   = ta.value.substring(start, end);
        var insert = '';

        switch (action) {
            case 'bold':   insert = '<b>'  + sel + '</b>';  break;
            case 'italic': insert = '<i>'  + sel + '</i>';  break;
            case 'break':  insert = '<br>';                 break;
            case 'link':   insert = '<a href="#">' + (sel || '\u0442\u0435\u043A\u0441\u0442') + '</a>'; break;
        }

        ta.value = ta.value.substring(0, start) + insert + ta.value.substring(end);

        var newPos = start + insert.length;
        ta.setSelectionRange(newPos, newPos);
    }

    /* ------------------------------------------------------------------
       Messenger input visibility toggle
       ------------------------------------------------------------------ */
    function toggleMessengerInput(card) {
        var cb = card.querySelector('input[type="checkbox"]');
        var inputWrap = card.querySelector('.nc-messenger-input');
        if (!cb || !inputWrap) return;
        inputWrap.classList.toggle('visible', cb.checked);
    }

    /* ------------------------------------------------------------------
       Validate messenger input on the fly
       ------------------------------------------------------------------ */
    function validateMessengerInput(input) {
        var card = input.closest('.nc-messenger-card');
        if (!card) return;

        var key = card.getAttribute('data-key');
        var status = card.querySelector('.nc-input-status');
        if (!status) return;

        var result = NetChatValidation.normalize(key, input.value);
        status.textContent = result.ok ? '' : '\u26A0';
        input.setAttribute('aria-invalid', result.ok ? 'false' : 'true');
    }

    /* ------------------------------------------------------------------
       Event delegation - single listener approach
       ------------------------------------------------------------------ */
    function initEventListeners() {

        // --- Position buttons ---
        document.addEventListener('click', function (e) {
            var posBtn = e.target.closest('.nc-pos-btn');
            if (posBtn) {
                var btns = document.querySelectorAll('.nc-pos-btn');
                for (var i = 0; i < btns.length; i++) btns[i].classList.remove('nc-pos-active');
                posBtn.classList.add('nc-pos-active');
                var sideLabel = document.getElementById('nc-pos-side-label');
                if (sideLabel) sideLabel.textContent = sideLabel.getAttribute('data-' + posBtn.getAttribute('data-value'));
                return;
            }

            // --- Language buttons ---
            var langBtn = e.target.closest('.nc-lang-opt');
            if (langBtn) {
                var btns2 = document.querySelectorAll('.nc-lang-opt');
                for (var j = 0; j < btns2.length; j++) btns2[j].classList.remove('active');
                langBtn.classList.add('active');
                return;
            }

            // --- Editor toolbar buttons ---
            var editorBtn = e.target.closest('.nc-editor-btn');
            if (editorBtn) {
                e.preventDefault();
                e.stopPropagation();
                var action = editorBtn.getAttribute('data-action');
                handleEditorButton(action);
                return;
            }
        });

        // --- Greeting toggle ---
        var hEl = document.getElementById('nc-opt-h');
        if (hEl) {
            hEl.addEventListener('change', updateGreetingFields);
        }

        // --- Messenger toggles ---
        var messengerCards = document.querySelectorAll('.nc-messenger-card');
        for (var i = 0; i < messengerCards.length; i++) {
            (function (card) {
                var cb = card.querySelector('input[type="checkbox"]');
                if (cb) {
                    cb.addEventListener('change', function () {
                        toggleMessengerInput(card);
                    });
                }
            })(messengerCards[i]);
        }

        // --- Messenger input validation ---
        document.addEventListener('input', function (e) {
            var input = e.target.closest('.nc-messenger-card .nc-input');
            if (input) {
                // Update data-real-value so it persists even when toggle is off
                input.setAttribute('data-real-value', input.value);
                validateMessengerInput(input);
            }
        });

        // --- Prevent Enter on ALL number inputs ---
        document.addEventListener('keydown', function (e) {
            var target = e.target;
            if (target && target.type === 'number' && e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                target.blur();
            }
        });

        // --- Save button ---
        var saveBtn = document.getElementById('nc-save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveSettings();
            });
        }

        // --- Reset button ---
        var resetBtn = document.getElementById('nc-reset-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var leadsOnly = !!document.querySelector('[data-nc-leads-only]');
                if (!confirm(leadsOnly ? 'Восстановить настройки формы обращений? Состояние приёма и настройки виджета сохранятся.' : 'Сбросить настройки виджета и каналов? Приём заявок будет выключен, настройки формы сохранятся.')) return;
                if (leadsOnly) {
                    var reset = JSON.parse(JSON.stringify(netchatAdmin.config));
                    reset.leads = JSON.parse(JSON.stringify(netchatAdmin.defaults.leads));
                    reset.leads.enabled = netchatAdmin.config.leads.enabled;
                    saveSettings(reset, true);
                    return;
                }

                var widgetReset = JSON.parse(JSON.stringify(netchatAdmin.defaults));
                widgetReset.leads = JSON.parse(JSON.stringify(netchatAdmin.config.leads));
                widgetReset.leads.enabled = netchatAdmin.defaults.leads.enabled;
                saveSettings(widgetReset, true);
            });
        }
    }

    /* ------------------------------------------------------------------
       Init
       ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        updateGreetingFields();
        var presentation=document.getElementById('nc-form-presentation'),side=document.getElementById('nc-drawer-side-row');
        function drawerSideVisibility(){if(side)side.hidden=!presentation||presentation.value!=='drawer';}
        if(presentation)presentation.addEventListener('change',drawerSideVisibility);
        drawerSideVisibility();
        initEventListeners();
    });

})();
