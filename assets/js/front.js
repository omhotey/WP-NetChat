/** NetChat contact launcher. */
(function () {
    'use strict';
    function init() {
        var t = document.getElementById('nc-toggle');
        var b = document.getElementById('nc-bubble');
        var h = document.getElementById('nc-hint');
        var wrap = document.getElementById('netchat');
        if (!t || !b || !wrap) return;
        var cfg = window.netchat || {};
        var formFirst = t.hasAttribute('data-netchat-open');
        var open = false, hintTimer, buttonTimer, closeTimer, hintDismissed = false;
        var hintHadFocus = false;
        var buttonReached = false, hintReached = false, frame = 0;
        var buttonPercent = Number(cfg.buttonScrollPercent) || 0, hintPercent = Number(cfg.hintScrollPercent) || 0;
        function buttonAccessible() { return !t.hidden && t.getClientRects().length>0 && window.getComputedStyle(t).visibility!=='hidden'; }
        function hideHint() {
            if(!h)return;
            if(h.contains(document.activeElement)||hintHadFocus){
                hintHadFocus=false;
                if(buttonAccessible())t.focus({preventScroll:true});
                else if(document.activeElement.blur)document.activeElement.blur();
            }
            h.hidden=true;h.classList.remove('nc-visible');
        }
        function stopHint() { hintDismissed=true;clearTimeout(hintTimer);hideHint(); }
        var initializedAt=Date.now();
        function navigationElapsed() {
            if(window.performance&&typeof window.performance.now==='function')return Math.max(0,window.performance.now());
            var start=window.performance&&window.performance.timing&&window.performance.timing.navigationStart;
            return Math.max(0,Date.now()-(start||initializedAt));
        }
        function hintAllowed() { return hintMobile || window.innerWidth>768; }
        function checkProgress() {
            frame=0;clearTimeout(buttonTimer);clearTimeout(hintTimer);
            var doc=document.scrollingElement||document.documentElement;
            var distance=Math.max(doc.scrollHeight,document.body.scrollHeight)-window.innerHeight;
            var progress=distance<=0?100:Math.max(0,Math.min(100,100*Math.max(0,window.pageYOffset||doc.scrollTop||0)/distance));
            buttonReached=buttonReached||progress>=buttonPercent;
            hintReached=hintReached||progress>=hintPercent;
            var elapsed=navigationElapsed(),buttonReady=buttonReached&&elapsed>=buttonDelay;
            t.hidden=!buttonReady;
            if(buttonReady)wrap.classList.remove('nc-scroll-wait');
            if(elapsed<buttonDelay)buttonTimer=setTimeout(checkProgress,buttonDelay-elapsed);
            if(!h||hintDismissed){hideHint();return;}
            if(open||document.querySelector('#nc-leads-modal:not([hidden])')){stopHint();return;}
            if(!hintAllowed()){hideHint();return;}
            if(elapsed<hintDelay)hintTimer=setTimeout(checkProgress,hintDelay-elapsed);
            if(!hintReached||elapsed<hintDelay||!buttonAccessible()){hideHint();return;}
            h.hidden=false;h.classList.add('nc-visible');
        }
        function scheduleProgress() { if(!frame)frame=window.requestAnimationFrame(checkProgress); }
        var motion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
        function duration(value, fallback) {
            if (value === undefined || value === null || value === '') return fallback;
            var n = Number(value);
            return isFinite(n) && n >= 0 ? n : fallback;
        }
        var speed = duration(cfg.speed, 300), hintDelay = duration(cfg.hintDelay, 2000), buttonDelay = duration(cfg.buttonDelay, 0);
        var hintMobile = cfg.hintMobile===1 || cfg.hintMobile===true || cfg.hintMobile==='1';
        if(h)h.classList.toggle('nc-hint-no-mobile',!hintMobile);
        wrap.style.setProperty('--nc-duration', speed + 'ms');
        if (speed === 0) wrap.classList.add('nc-no-motion');
        function buttons() { return b.querySelectorAll('.nc-btn'); }
        function fitMenu() {
            var rect=t.getBoundingClientRect(), available=wrap.classList.contains('nc-at-top') ? window.innerHeight-rect.bottom-20 : rect.top-20;
            b.classList.remove('nc-scroll'); b.style.maxHeight='';
            if (b.scrollHeight+48>available) { b.classList.add('nc-scroll'); b.style.maxHeight=Math.max(80,available)+'px'; }
        }
        window.addEventListener('resize',fitMenu); fitMenu();
        function openMenu() {
            stopHint();
            if(formFirst){
                if(window.NetChat && window.NetChat.openForm){
                    var options={form:'contact',trigger:t};
                    if(t.hasAttribute('data-netchat-service')){options.service_code='';options.service_selector=true;options.cta_position='widget';}
                    return window.NetChat.openForm(options);
                }
                return;
            }
            clearTimeout(closeTimer);
            if (open) return;
            fitMenu();
            open = true; b.classList.add('nc-open');
            t.setAttribute('aria-expanded', 'true'); b.setAttribute('aria-hidden', 'false');
            if (h) {
                h.classList.remove('nc-visible');
                if (h.contains(document.activeElement)) t.focus();
            }
            clearTimeout(hintTimer);
            buttons().forEach(function (btn, i) {
                btn.style.animationDelay = (!motion.matches && speed > 0 ? i * 60 : 0) + 'ms';
                btn.setAttribute('tabindex', '0');
            });
        }
        function closeMenu(restoreFocus) {
            clearTimeout(closeTimer);
            var hadFocus = b.contains(document.activeElement);
            open = false; b.classList.remove('nc-open');
            t.setAttribute('aria-expanded', formFirst && document.querySelector('#nc-leads-modal:not([hidden])') ? 'true' : 'false'); b.setAttribute('aria-hidden', 'true');
            buttons().forEach(function (btn) { btn.setAttribute('tabindex', '-1'); btn.style.animationDelay = ''; });
            if (restoreFocus || hadFocus) t.focus();
        }
        function toggleMenu() { if(document.querySelector('#nc-leads-modal:not([hidden])')){if(window.NetChat.closeForm)window.NetChat.closeForm();return;}if(formFirst){openMenu();return;}if (open) closeMenu(); else openMenu(); }
        if(!formFirst)t.addEventListener('click', function () { toggleMenu(); });
        t.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault(); openMenu();
                if(formFirst)return;
                var btns = buttons();
                if (btns.length) btns[e.key === 'ArrowUp' ? btns.length - 1 : 0].focus();
            }
        });
        b.addEventListener('keydown', function (e) {
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(e.key) < 0) return;
            var btns = Array.prototype.slice.call(buttons());
            if (!btns.length) return;
            var index = btns.indexOf(document.activeElement);
            if (e.key === 'Home') index = 0;
            else if (e.key === 'End') index = btns.length - 1;
            else index = (index + (e.key === 'ArrowDown' ? 1 : -1) + btns.length) % btns.length;
            e.preventDefault(); btns[index].focus();
        });
        document.addEventListener('click', function (e) { if (open && !wrap.contains(e.target)) closeMenu(); });
        document.addEventListener('keydown', function (e) { if (open && e.key === 'Escape') { e.preventDefault(); closeMenu(true); } });
        wrap.addEventListener('focusout', function () {
            setTimeout(function () { if (open && !wrap.contains(document.activeElement)) closeMenu(); }, 0);
        });
        b.addEventListener('click', function (e) { if (e.target.closest('.nc-btn')) closeTimer = setTimeout(closeMenu, 150); });
        if (h) {
            h.addEventListener('focusin',function(){hintHadFocus=true;});
            h.addEventListener('focusout',function(e){
                // CSS may blur a phone-disabled hint before the resize callback.
                if(!hintAllowed()&&(!e.relatedTarget||e.relatedTarget===document.body))return;
                hintHadFocus=!!(e.relatedTarget&&h.contains(e.relatedTarget));
            });
            document.addEventListener('netchat:form-open',stopHint);
            function dismissHint() { stopHint(); if(h.contains(document.activeElement)&&buttonAccessible())t.focus(); }
            h.querySelector('.nc-hint-close').addEventListener('click',function(e){e.stopPropagation();dismissHint();});
            h.addEventListener('keydown',function(e){if(e.key==='Escape'){e.preventDefault();dismissHint();}});
            h.addEventListener('click', function (e) { if (!e.target.closest('a,button,input,select,textarea')) openMenu(); });
            if (!h.querySelector('a,button,input,select,textarea')) {
                h.setAttribute('role', 'button'); h.setAttribute('tabindex', '0');
                h.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openMenu(); } });
            }
        }
        var api = window.NetChat || {};
        api.open = openMenu;
        api.close = function () { if (api.closeForm) api.closeForm(); closeMenu(); };
        api.toggle = toggleMenu;
        api.isOpen = function () { return open || !!document.querySelector('#nc-leads-modal:not([hidden])'); };
        window.NetChat = api;
        window.addEventListener('scroll',scheduleProgress,{passive:true});
        window.addEventListener('resize',scheduleProgress);
        window.addEventListener('pageshow',scheduleProgress);
        window.addEventListener('load',scheduleProgress);
        if(window.ResizeObserver){var observer=new ResizeObserver(scheduleProgress);observer.observe(document.documentElement);observer.observe(document.body);}
        else if(window.MutationObserver){var observer=new MutationObserver(function(records){
            if(records.some(function(record){var target=record.target.nodeType===1?record.target:record.target.parentElement;return target&&!wrap.contains(target)&&!target.closest('#nc-leads-modal');}))scheduleProgress();
        });observer.observe(document.documentElement,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:['style','class','height','width']});}
        // Covers late image/font height changes without polling.
        document.addEventListener('load',scheduleProgress,true);
        if(document.fonts&&document.fonts.ready)document.fonts.ready.then(scheduleProgress);
        checkProgress();
        document.dispatchEvent(new CustomEvent('netchat:ready', { detail: api }));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
