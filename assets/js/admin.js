/**
 * NetChat Admin JS - Material Design 3
 * Author: omhotey
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Validation helpers
       ------------------------------------------------------------------ */
    function validateInput(key, raw) {
        var v = raw.trim();
        if (!v) return { ok: true, url: '', msg: '' };

        switch (key) {
            case 'telegram': {
                var u = v.replace(/^@/, '');
                return /^[\w]{3,32}$/.test(u)
                    ? { ok: true, url: 'https://t.me/' + u, msg: '' }
                    : { ok: false, url: '', msg: '\u26A0\uFE0F 3\u201332' };
            }
            case 'whatsapp': {
                var d = v.replace(/[^\d]/g, '');
                return /^\d{10,15}$/.test(d)
                    ? { ok: true, url: 'https://wa.me/' + d, msg: '' }
                    : { ok: false, url: '', msg: '\u26A0\uFE0F 10\u201315' };
            }
            case 'vk': {
                var u2 = v.replace(/^(https?:\/\/)?(vk\.com\/|vk\.me\/)/, '');
                return /^[\w]{1,30}$/.test(u2)
                    ? { ok: true, url: 'https://vk.me/' + u2, msg: '' }
                    : { ok: false, url: '', msg: '\u26A0\uFE0F VK' };
            }
            case 'max': {
                if (/^https?:\/\/max\.ru\//i.test(v)) return { ok: true, url: v, msg: '' };
                if (/^[\w\/\-\.\?\=\#\&\+\~]+$/.test(v)) return { ok: true, url: 'https://max.ru/' + v, msg: '' };
                return { ok: true, url: v, msg: '' };
            }
            case 'viber': {
                var d2 = v.replace(/[^\d+]/g, '');
                return /^\+?\d{10,15}$/.test(d2)
                    ? { ok: true, url: 'viber://chat?number=%2B' + d2.replace(/^\+/, ''), msg: '' }
                    : { ok: false, url: '', msg: '\u26A0\uFE0F +\u043D\u043E\u043C\u0435\u0440' };
            }
            case 'messenger': {
                var u3 = v.replace(/^(https?:\/\/)?(m\.me\/|fb\.me\/)/, '');
                return /^[\w.]{1,50}$/.test(u3)
                    ? { ok: true, url: 'https://m.me/' + u3, msg: '' }
                    : { ok: false, url: '', msg: '\u26A0\uFE0F FB' };
            }
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)
                    ? { ok: true, url: 'mailto:' + v, msg: '' }
                    : { ok: false, url: '', msg: '\u26A0\uFE0F Email' };
            case 'phone': {
                var d3 = v.replace(/[^\d+]/g, '');
                return /^\+?\d[\d\-]{6,14}$/.test(v)
                    ? { ok: true, url: 'tel:' + d3, msg: '' }
                    : { ok: false, url: '', msg: '\u26A0\uFE0F \u0422\u0435\u043B' };
            }
        }
        return { ok: true, url: v, msg: '' };
    }

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
            pos_right:  posRightEl ? posRightEl.value : '',
            pos_bottom: posBottomEl ? posBottomEl.value : '30',
            pos_left:   isLeft ? (posRightEl ? posRightEl.value : '') : '',
            sz:         szEl ? parseInt(szEl.value, 10) || 56 : 56,
            sp:         spEl ? parseInt(spEl.value, 10) || 300 : 300,
            cl:         clEl ? clEl.value : '#2563eb',
            h:          hEl ? hEl.checked : false,
            ht:         htEl ? htEl.value : '',
            hd:         hdEl ? parseInt(hdEl.value, 10) || 2 : 2,
            d:          dEl ? dEl.checked : false,
            m:          mEl ? mEl.checked : false,
            lang:       activeLangBtn ? activeLangBtn.getAttribute('data-lang') : 'ru'
        };
    }

    /* ------------------------------------------------------------------
       Build REST payload
       ------------------------------------------------------------------ */
    function buildPayload(s) {
        var data = {
            enabled: {},
            links: {},
            position: {
                top:    '',
                right:  s.pos_right !== ''  ? parseInt(s.pos_right, 10)  : '',
                bottom: s.pos_bottom !== '' ? parseInt(s.pos_bottom, 10) : '30',
                left:   s.pos_left !== ''   ? parseInt(s.pos_left, 10)   : ''
            },
            size:       s.sz,
            animation:  s.sp,
            color:      s.cl,
            hint:       s.h ? 1 : 0,
            hint_text:  s.ht,
            hint_delay: s.hd,
            desktop:    s.d ? 1 : 0,
            mobile:     s.m ? 1 : 0,
            lang:       s.lang,
            order:      s.channels.map(function (c) { return c.key; }).join(',')
        };

        for (var i = 0; i < s.channels.length; i++) {
            var ch = s.channels[i];
            data.enabled[ch.key] = ch.enabled ? 1 : 0;
            var v = validateInput(ch.key, ch.value);
            data.links[ch.key] = v.ok ? v.url : '';
        }

        return data;
    }

    /* ------------------------------------------------------------------
       REST API save
       ------------------------------------------------------------------ */
    function saveSettings() {
        var saveBtn = document.getElementById('nc-save-btn');
        if (!saveBtn) return;

        saveBtn.disabled = true;
        saveBtn.textContent = '\u23F3 Saving...';

        var s = collectState();

        var payload = buildPayload(s);

        fetch(netchatAdmin.restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': netchatAdmin.restNonce
            },
            body: JSON.stringify(payload)
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '\uD83D\uDCBE ' + (netchatAdmin.i18n_save_ok || 'Settings saved!');

            if (res.success) {
                showNotice('\u2705 ' + (res.message || netchatAdmin.i18n_save_ok), 'success');
                if (res.lang_changed) {
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    setTimeout(function () {
                        saveBtn.innerHTML = '\uD83D\uDCBE ' + (netchatAdmin.i18n_save_ok || 'Save Settings');
                    }, 2000);
                }
            } else {
                showNotice('\u274C ' + (res.message || netchatAdmin.i18n_save_err), 'error');
                saveBtn.innerHTML = '\uD83D\uDCBE Save Settings';
            }
        })
        .catch(function () {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '\uD83D\uDCBE Save Settings';
            showNotice('\u274C ' + (netchatAdmin.i18n_save_err || 'Error saving'), 'error');
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
        var input = inputWrap ? inputWrap.querySelector('.nc-input') : null;
        if (!cb || !inputWrap || !input) return;

        if (cb.checked) {
            inputWrap.classList.add('visible');
            // Restore saved value from data-real-value
            var savedVal = input.getAttribute('data-real-value');
            if (savedVal) input.value = savedVal;
        } else {
            inputWrap.classList.remove('visible');
            input.value = '';
        }
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

        var v = validateInput(key, input.value);
        if (input.value && v.ok) {
            status.textContent = '\u2705';
        } else if (input.value && !v.ok) {
            status.textContent = '\u26A0\uFE0F';
        } else {
            status.textContent = '';
        }
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
                if (!confirm('Сбросить все настройки до умолчаний?')) return;

                // Send reset payload via REST API
                var resetPayload = {
                    enabled: { telegram:0,whatsapp:0,messenger:0,max:0,vk:0,viber:0,email:0,phone:0 },
                    links: { telegram:'',whatsapp:'',messenger:'',max:'',vk:'',viber:'',email:'',phone:'' },
                    position: { top:'',right:'',bottom:'30',left:'' },
                    size: 56, animation: 300, color: '#2563eb',
                    hint: 0, hint_text: '', hint_delay: 2,
                    desktop: 1, mobile: 1, lang: 'ru',
                    order: 'telegram,whatsapp,messenger,max,vk,viber,email,phone'
                };

                fetch(netchatAdmin.restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': netchatAdmin.restNonce
                    },
                    body: JSON.stringify(resetPayload)
                }).then(function(res) { return res.json(); })
                .then(function(res) {
                    if (res.success) {
                        location.reload();
                    }
                });
            });
        }
    }

    /* ------------------------------------------------------------------
       Init
       ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        updateGreetingFields();
        initEventListeners();
    });

})();
