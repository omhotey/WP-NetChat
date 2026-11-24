/** Canonical links. The server repeats validation before committing settings. */
(function (root) {
    'use strict';
    function normalize(type, raw) {
        function invalid() { return { ok: false, url: '', msg: 'Invalid contact: ' + type }; }
        function valid(url) { return { ok: true, url: url, msg: '' }; }
        if (typeof raw !== 'string' || raw.length > 2048 || /[\x00-\x1f\x7f]/.test(raw)) return invalid();
        var value = raw.trim();
        if (!value) return valid('');
        if (/\s/.test(value) && ['phone', 'whatsapp', 'viber'].indexOf(type) < 0) return invalid();
        if (type === 'email') {
            var email = value.replace(/^mailto:/i, '');
            return /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@(?:[A-Za-z0-9-]+\.)+[A-Za-z0-9-]+$/.test(email) ? valid('mailto:' + email) : invalid();
        }
        var parts = value.match(/^https?:\/\/([^/?#]+)([^?#]*)(\?[^#]*)?(#.*)?$/i);
        var path = value, suffix = '', number = value;
        if (['phone', 'whatsapp', 'viber'].indexOf(type) >= 0) {
            if (type === 'phone') number = number.replace(/^tel:/i, '');
            else if (type === 'whatsapp' && /^https?:\/\//i.test(value)) {
                if (!parts || parts[1].toLowerCase() !== 'wa.me' || /\s/.test(value)) return invalid();
                number = parts[2].replace(/^\/+|\/+$/g, '');
                return /^\d{10,15}$/.test(number) ? valid('https://wa.me/' + number + (parts[3] || '')) : invalid();
            } else if (type === 'viber' && /^viber:/i.test(value)) {
                var viber = value.match(/^viber:\/\/chat\?number=(?:%2B|\+)?(\d{10,15})$/i);
                return viber ? valid('viber://chat?number=%2B' + viber[1]) : invalid();
            }
            if (!/^\+?[\d ().-]+$/.test(number)) return invalid();
            var digits = number.replace(/\D/g, '');
            if (digits.length < (type === 'phone' ? 7 : 10) || digits.length > 15) return invalid();
            if (type === 'phone') return valid('tel:' + (number[0] === '+' ? '+' : '') + digits);
            return valid(type === 'whatsapp' ? 'https://wa.me/' + digits : 'viber://chat?number=%2B' + digits);
        }
        var hosts = { telegram: 't.me', messenger: 'm.me', vk: 'vk.me', max: 'max.ru' };
        var host = hosts[type];
        if (!host) return invalid();
        if (/^https?:\/\//i.test(value)) {
            if (!parts) return invalid();
            var accepted = [host];
            if (type === 'vk') accepted.push('vk.com');
            if (type === 'messenger') accepted.push('fb.me');
            if (accepted.indexOf(parts[1].toLowerCase()) < 0) return invalid();
            path = parts[2].replace(/^\/+|\/+$/g, ''); suffix = (parts[3] || '') + (parts[4] || '');
        } else {
            var prefix = new RegExp('^' + host.replace('.', '\\.') + '/', 'i');
            path = path.replace(prefix, '');
            if (type === 'vk') path = path.replace(/^vk\.com\//i, '');
            if (type === 'messenger') path = path.replace(/^fb\.me\//i, '');
            if (type === 'telegram') path = path.replace(/^@+/, '');
        }
        var pattern = type === 'telegram' ? /^(?:[A-Za-z0-9_]{3,32}|\+[A-Za-z0-9_-]+|joinchat\/[A-Za-z0-9_-]+|[A-Za-z0-9_]{3,32}\/\d+)$/ :
            type === 'vk' ? /^[A-Za-z0-9_]{1,100}$/ : type === 'messenger' ? /^[A-Za-z0-9_.]{1,100}$/ : /^[A-Za-z0-9_+~.-]+(?:\/[A-Za-z0-9_+~.-]+)*$/;
        return pattern.test(path) ? valid('https://' + host + '/' + path + suffix) : invalid();
    }
    root.NetChatValidation = { normalize: normalize };
    if (typeof module === 'object' && module.exports) module.exports = root.NetChatValidation;
})(typeof window === 'object' ? window : globalThis);
