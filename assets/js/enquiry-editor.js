(function () {
    'use strict';
    var root = document.querySelector('.nc-enquiry-editor');
    if (!root) return;
    var baseline = JSON.stringify(netchatAdmin.config.leads);
    var preview = root.querySelector('[data-enquiry-preview]');
    var previewValues = {}, success = false;
    function config() {
        var value = JSON.parse(JSON.stringify(netchatAdmin.config.leads));
        root.querySelectorAll('[data-nc-lead-setting]').forEach(function (control) {
            var path = control.dataset.ncLeadSetting.split('.'), parent = value;
            for (var i = 0; i < path.length - 1; i++) parent = parent[path[i]];
            var current = control.type === 'checkbox' ? Number(control.checked) : control.value;
            if (control.type === 'number') current = Number(current);
            if (control.dataset.ncLeadType === 'recipients') current = current.split(/[,;\n]+/).map(function (s) { return s.trim(); }).filter(Boolean);
            parent[path[path.length - 1]] = current;
        });
        return value;
    }
    function select(attribute, panelAttribute, key) {
        root.querySelectorAll('[' + attribute + ']').forEach(function (button) { button.setAttribute('aria-pressed', String(button.getAttribute(attribute) === key)); });
        root.querySelectorAll('[' + panelAttribute + ']').forEach(function (panel) { panel.hidden = panel.getAttribute(panelAttribute) !== key; });
        if (attribute === 'data-enquiry-field') root.querySelector('[data-enquiry-field-select]').value = key;
    }
    function element(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function render() {
        var c = config(), dirty = JSON.stringify(c) !== baseline;
        var status = root.querySelector('[data-enquiry-status]');
        status.textContent = dirty ? 'Есть несохранённые изменения' : 'Все изменения сохранены';
        status.classList.toggle('is-dirty', dirty);
        Object.keys(c.fields).forEach(function (key) {
            var field = c.fields[key], button = root.querySelector('[data-enquiry-field="' + key + '"]');
            button.querySelector('small').textContent = !field.enabled ? 'Скрыто' : (field.required ? 'Обязательное' : 'Необязательное');
            button.querySelector('.nc-e-field-dot').classList.toggle('is-off', !field.enabled);
        });
        var active = document.activeElement, restore = active && active.dataset.enquiryPreviewValue;
        var selection = restore && active.selectionStart;
        preview.replaceChildren(element('h3', '', c.title));
        Object.keys(c.fields).forEach(function (key) {
            var field = c.fields[key]; if (!field.enabled) return;
            var wrapper = element('div', 'nc-e-preview-field'), label = element('label', '', field.label);
            label.htmlFor = 'nc-e-preview-' + key;
            if (field.required) label.append(element('span', '', ' *'));
            var input = element(key === 'message' ? 'textarea' : 'input');
            if (key !== 'message') input.type = key === 'email' ? 'email' : (key === 'phone' ? 'tel' : 'text');
            input.id = label.htmlFor; input.placeholder = field.placeholder; input.value = previewValues[key] || '';
            input.dataset.enquiryPreviewValue = key;
            wrapper.append(label, input); preview.append(wrapper);
        });
        if (c.consent.enabled) {
            var consent = element('label', 'nc-e-preview-consent'), checkbox = element('input'); checkbox.type = 'checkbox'; checkbox.dataset.enquiryPreviewValue = 'consent'; checkbox.checked = !!previewValues.consent;
            var text = element('span', '', c.consent.text + (c.consent.required ? ' *' : ''));
            if (c.consent.url) {
                var link = element('a', '', 'Политика обработки данных');
                try { var url = new URL(c.consent.url); if (/^https?:$/.test(url.protocol)) { link.href = url.href; link.target = '_blank'; link.rel = 'noopener noreferrer'; text.append(' ', link); } } catch (_) { /* Invalid draft links stay plain text. */ }
            }
            consent.append(checkbox, text); preview.append(consent);
        }
        var send = element('button', 'nc-e-preview-submit', c.button_text); send.type = 'button'; send.dataset.enquiryPreviewSend = ''; preview.append(send);
        if (success) preview.append(element('p', 'nc-e-preview-success', c.success_text));
        if (restore) { var target = preview.querySelector('[data-enquiry-preview-value="' + restore + '"]'); if (target) { target.focus({preventScroll:true}); if (selection !== null && target.setSelectionRange && /^(text|tel)$/.test(target.type)) target.setSelectionRange(selection, selection); } }
    }
    root.addEventListener('click', function (event) {
        var tab = event.target.closest('[data-enquiry-tab]');
        if (tab) { select('data-enquiry-tab', 'data-enquiry-panel', tab.dataset.enquiryTab); root.classList.remove('is-preview'); }
        var field = event.target.closest('[data-enquiry-field]');
        if (field) select('data-enquiry-field', 'data-enquiry-field-panel', field.dataset.enquiryField);
        if (event.target.closest('[data-enquiry-preview-open]')) { root.classList.add('is-preview'); root.querySelector('[data-enquiry-preview-close]').focus(); }
        if (event.target.closest('[data-enquiry-preview-close]')) { root.classList.remove('is-preview'); root.querySelector('[data-enquiry-preview-open]').focus(); }
        if (event.target.closest('[data-enquiry-preview-send]')) { success = true; render(); }
    });
    root.addEventListener('keydown', function (event) {
        var button = event.target.closest('[data-enquiry-tab], [data-enquiry-field]');
        if (button && ['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            var buttons = Array.from(button.parentElement.querySelectorAll('button')), index = buttons.indexOf(button);
            index = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (/Right|Down/.test(event.key) ? 1 : -1) + buttons.length) % buttons.length;
            buttons[index].click(); buttons[index].focus();
        }
    });
    function input(event) {
        if (event.target.matches('[data-enquiry-field-select]')) { select('data-enquiry-field', 'data-enquiry-field-panel', event.target.value); return; }
        if (event.target.dataset.enquiryPreviewValue) { previewValues[event.target.dataset.enquiryPreviewValue] = event.target.type === 'checkbox' ? event.target.checked : event.target.value; return; }
        if (event.target.matches('[data-nc-lead-setting]')) { success = false; render(); }
    }
    root.addEventListener('input', input); root.addEventListener('change', input);
    window.addEventListener('netchat-settings-saved', function (event) { baseline = JSON.stringify(event.detail && event.detail.reload ? config() : netchatAdmin.config.leads); render(); });
    window.addEventListener('beforeunload', function (event) { if (JSON.stringify(config()) !== baseline) { event.preventDefault(); event.returnValue = ''; } });
    render();
})();
