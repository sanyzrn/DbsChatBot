/**
 * Smart Support Chatbot — frontend widget (v5).
 *
 * Design notes:
 * - Vanilla JS, no dependencies, no build step.
 * - Targeted DOM updates (no full re-render): fixes live-region/TTS desync.
 * - RTL/LTR driven by config; CSS uses logical properties only.
 * - Closed window uses visibility + inert (keyboard-safe).
 * - Reduced-motion respected (typewriter included).
 * - REST first, admin-ajax fallback (page-cache compatibility).
 * - No secrets in this config; everything sensitive stays server-side.
 */
(function () {
        'use strict';

        if (window.SSCChatbot) { return; }

        var cfg = window.SSCChatbotConfig;
        if (!cfg || (!cfg.restUrl && !cfg.ajaxUrl)) { return; }

        var REDUCED_MOTION = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* ------------------------------------------------------------------ *
         * Utilities
         * ------------------------------------------------------------------ */

        function el(tag, cls, html) {
                var node = document.createElement(tag);
                if (cls) { node.className = cls; }
                if (html !== undefined && html !== null && html !== '') { node.innerHTML = html; }
                return node;
        }

        function esc(text) {
                var d = document.createElement('div');
                d.textContent = String(text === undefined || text === null ? '' : text);
                return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        /** Minimal safe markdown: **bold**, links, line breaks. Escapes first. */
        function md(text) {
                var escaped = esc(text);
                escaped = escaped.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
                escaped = escaped.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, function (m, label, url) {
                        return '<a href="' + url + '" target="_blank" rel="noopener noreferrer nofollow">' + label + '</a>';
                });
                escaped = escaped.replace(/\n/g, '<br>');
                return escaped;
        }

        function uid() {
                return 'ssc-' + Math.random().toString(36).slice(2, 10);
        }

        /* ------------------------------------------------------------------ *
         * Client id + persisted conversation
         * ------------------------------------------------------------------ */

        var CID_KEY = 'ssc_cid';
        var THREAD_KEY = 'ssc_thread_v1';

        function getCid() {
                try {
                        var c = localStorage.getItem(CID_KEY);
                        if (!c) {
                                c = uid();
                                localStorage.setItem(CID_KEY, c);
                        }
                        return c;
                } catch (e) {
                        return 'anon';
                }
        }

        /** Conversation transcript persisted across pages (2h TTL, 60 items). */
        function saveThread() {
                if (!state.persist) { return; }
                try {
                        var slim = {
                                at: Date.now(),
                                product: state.product,
                                items: state.items.slice(-60).map(function (item) {
                                        return { k: item.kind, t: item.text, h: !!item.history };
                                })
                        };
                        sessionStorage.setItem(THREAD_KEY, JSON.stringify(slim));
                } catch (e) { /* storage unavailable */ }
        }

        function loadThread() {
                if (!state.persist) { return null; }
                try {
                        var raw = sessionStorage.getItem(THREAD_KEY);
                        if (!raw) { return null; }
                        var data = JSON.parse(raw);
                        if (!data || !Array.isArray(data.items) || !data.at || Date.now() - data.at > 2 * 60 * 60 * 1000) {
                                sessionStorage.removeItem(THREAD_KEY);
                                return null;
                        }
                        return data;
                } catch (e) {
                        return null;
                }
        }

        /* ------------------------------------------------------------------ *
         * Transport
         * ------------------------------------------------------------------ */

        // Keep the deadline active until the body (including SSE) has been consumed.
        function request(url, options, consume) {
                var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                if (controller) { options.signal = controller.signal; }
                var timer = controller ? setTimeout(function () { controller.abort(); }, 120000) : null;
                return fetch(url, options).then(consume).finally(function () { if (timer) { clearTimeout(timer); } });
        }

        function transport(route, params) {
                var body = new URLSearchParams();
                Object.keys(params).forEach(function (k) { body.append(k, params[k]); });
                body.append('cid', getCid());
                if (!cfg.restUrl) { return legacyAjax(route, params); }
                var headers = { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' };
                if (cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
                return request(cfg.restUrl + route, {
                        method: 'POST', headers: headers, credentials: 'same-origin', body: body.toString()
                }, function (res) {
                        return res.json().then(function (data) {
                                // Only a definitively missing route permits replay. A failed POST may already be stored.
                                if (!cfg.preview && res.status === 404 && data.code === 'rest_no_route') { return legacyAjax(route, params); }
                                if (!res.ok || (data && data.code && data.message)) {
                                        return { success: false, data: { message: data.message, code: data.code } };
                                }
                                return { success: true, data: data };
                        });
                });
        }

        function legacyAjax(route, params) {
                if (!cfg.ajaxUrl || cfg.preview) { return Promise.reject(new Error('Transport unavailable')); }
                var body = new URLSearchParams();
                body.append('action', 'ssc_chatbot_' + route);
                Object.keys(params).forEach(function (k) { body.append(k, params[k]); });
                body.append('cid', getCid());
                var headers = { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' };
                if (cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
                return request(cfg.ajaxUrl, {
                        method: 'POST', headers: headers, credentials: 'same-origin', body: body.toString()
                }, function (res) { return res.json(); });
        }

        function chatRoute() {
                return cfg.preview && cfg.previewRoute ? cfg.previewRoute : 'chat';
        }

        function sendChat(message) {
                return transport(chatRoute(), {
                        message: message,
                        product: state.product || 'general',
                        history: JSON.stringify(historyItems())
                });
        }

        /** SSE framing survives arbitrary network chunk boundaries and CRLF lines. */
        function sseParser(onEvent) {
                var buffer = '', eventName = '', data = [];
                return function (chunk) {
                        buffer += chunk;
                        var end;
                        while ((end = buffer.indexOf('\n')) !== -1) {
                                var line = buffer.slice(0, end).replace(/\r$/, '');
                                buffer = buffer.slice(end + 1);
                                if (line === '') {
                                        if (data.length) { onEvent(eventName || 'message', JSON.parse(data.join('\n'))); }
                                        eventName = ''; data = [];
                                } else if (line.indexOf('event:') === 0) { eventName = line.slice(6).trim(); }
                                else if (line.indexOf('data:') === 0) { data.push(line.slice(5).replace(/^ /, '')); }
                        }
                };
        }

        function sendChatStream(message, onDelta) {
                if (!canStream) { return sendChat(message); }
                var body = new URLSearchParams();
                body.append('message', message);
                body.append('product', state.product || 'general');
                body.append('history', JSON.stringify(historyItems()));
                body.append('cid', getCid());
                var headers = { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' };
                if (cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
                return request(cfg.restUrl + 'chat-stream', {
                        method: 'POST', headers: headers, credentials: 'same-origin', body: body.toString()
                }, function (res) {
                        if (!res.ok) {
                                return res.json().then(function (data) {
                                        if (res.status === 404 && data.code === 'rest_no_route') { return sendChat(message); }
                                        return { success: false, data: data };
                                });
                        }
                        if (!res.body || (res.headers.get('Content-Type') || '').indexOf('text/event-stream') === -1) {
                                throw new Error('Invalid stream response');
                        }
                        var reader = res.body.getReader(), decoder = new TextDecoder(), envelope = null;
                        var parse = sseParser(function (name, payload) {
                                if (name === 'delta' && typeof payload.text === 'string') { onDelta(payload.text); }
                                if (name === 'done') { envelope = payload; }
                                if (name === 'error') { throw new Error(payload.code || 'Stream failed'); }
                        });
                        function pump() {
                                return reader.read().then(function (step) {
                                        parse(step.done ? decoder.decode() : decoder.decode(step.value, { stream: true }));
                                        if (envelope) {
                                                reader.cancel().catch(function () {});
                                                return { success: true, data: envelope };
                                        }
                                        if (step.done) { throw new Error('Incomplete stream'); }
                                        return pump();
                                });
                        }
                        return pump().catch(function (error) { reader.cancel().catch(function () {}); throw error; });
                });
        }

        /* ------------------------------------------------------------------ *
         * State
         * ------------------------------------------------------------------ */

        var state = {
                open: false,
                started: false,
                product: null,      // focused product id
                items: [],          // {kind: bot|user|form|csat|success, text, node, history}
                loading: false,
                persist: !cfg.preview && !(cfg.features && cfg.features.pharma),
                csatDone: false,
                hadConversation: false,
                unread: 0
        };

        var avail = cfg.availability || {};
        var canStream = !!(!cfg.preview && avail.streaming && cfg.restUrl && typeof ReadableStream !== 'undefined' && typeof TextDecoder !== 'undefined');

        function historyItems() {
                return state.items.slice(0, -1)
                        .filter(function (item) { return item.history; }).slice(-20)
                        .map(function (item) { return { role: item.kind === 'user' ? 'user' : 'assistant', content: item.text }; });
        }

        /* ------------------------------------------------------------------ *
         * DOM construction
         * ------------------------------------------------------------------ */

        var root = document.getElementById('ssc-chatbot-root');
        if (!root) {
                if (cfg.preview) {
                        // Wizard preview: the mount node appears later; create a detached root.
                        root = el('div', 'ssc-root');
                } else {
                        return; // No mount point on this page.
                }
        }
        var launcher, win, thread, composer, input, live;

        function cssVars() {
                var vars = {
                        '--ssc-primary': cfg.primaryColor || '#16203a',
                        '--ssc-font-size': (cfg.fontSize || 14) + 'px',
                        '--ssc-win-width': (cfg.windowWidth || 384) + 'px',
                        '--ssc-win-radius': (cfg.windowRadius || 24) + 'px',
                        '--ssc-bubble-radius': (cfg.bubbleRadius || 16) + 'px',
                        '--ssc-launcher-size': (cfg.launcherSize || 60) + 'px'
                };
                if (cfg.fontStack) { vars['--ssc-font'] = cfg.fontStack; }
                if (cfg.userBubble) { vars['--ssc-user-bubble'] = cfg.userBubble; }
                if (cfg.botBubble) { vars['--ssc-bot-bubble'] = cfg.botBubble; }
                return vars;
        }

        function applyTheme() {
                var mode = cfg.themeMode || 'light';
                if ('auto' === mode && window.matchMedia) {
                        mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                root.setAttribute('data-theme', mode);
        }

        function buildLauncher() {
                launcher = el('button', 'ssc-launcher ssc-pos-' + (cfg.position || 'right'));
                launcher.type = 'button';
                launcher.setAttribute('aria-expanded', 'false');
                launcher.setAttribute('aria-controls', 'ssc-window');
                launcher.setAttribute('aria-label', (cfg.i18n && cfg.i18n.open) || 'Open chat');
                if (cfg.launcherIconUrl) {
                        launcher.appendChild(el('span', 'ssc-launcher__img', '<img src="' + esc(cfg.launcherIconUrl) + '" alt="" />'));
                } else if (cfg.avatarUrl) {
                        launcher.appendChild(el('span', 'ssc-launcher__img', '<img src="' + esc(cfg.avatarUrl) + '" alt="" />'));
                } else {
                        launcher.appendChild(el('span', 'ssc-launcher__icon', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>'));
                }
                launcher.appendChild(el('span', 'ssc-launcher__dot', ''));
                var badge = el('span', 'ssc-launcher__badge', '');
                badge.setAttribute('aria-hidden', 'true');
                badge.hidden = true;
                launcher.appendChild(badge);
                launcher.addEventListener('click', toggleWindow);
                root.appendChild(launcher);

                if (avail && avail.online === false) {
                        root.setAttribute('data-status', 'offline');
                }
        }

        function bumpUnread() {
                if (state.open) { return; }
                state.unread += 1;
                paintBadge();
        }

        function clearUnread() {
                state.unread = 0;
                paintBadge();
        }

        function paintBadge() {
                if (!launcher) { return; }
                var badge = launcher.querySelector('.ssc-launcher__badge');
                if (!badge) { return; }
                if (state.unread > 0) {
                        badge.hidden = false;
                        badge.textContent = state.unread > 9 ? '9+' : String(state.unread);
                        launcher.setAttribute('aria-label', ((cfg.i18n && cfg.i18n.open) || 'Open chat') + ' (' + state.unread + ')');
                } else {
                        badge.hidden = true;
                        badge.textContent = '';
                        launcher.setAttribute('aria-label', (cfg.i18n && cfg.i18n.open) || 'Open chat');
                }
        }

        /** Tiny Web Audio ping — no external file, respects the sound setting. */
        function maybeBeep() {
                if (!avail || !avail.sound) { return; }
                try {
                        var Ctx = window.AudioContext || window.webkitAudioContext;
                        if (!Ctx) { return; }
                        var ctx = new Ctx();
                        var osc = ctx.createOscillator();
                        var gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = 880;
                        gain.gain.value = 0.03;
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.08);
                        osc.onended = function () { try { ctx.close(); } catch (e) {} };
                } catch (e) { /* autoplay policies */ }
        }

        function deviceAllowed() {
                var rule = (avail && avail.device) || 'all';
                if ('all' === rule) { return true; }
                var mobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
                if ('mobile' === rule) { return !!mobile; }
                if ('desktop' === rule) { return !mobile; }
                return true;
        }

        function buildWindow() {
                win = el('div', 'ssc-window');
                win.id = 'ssc-window';
                win.setAttribute('role', 'dialog');
                win.setAttribute('aria-modal', 'false');
                win.setAttribute('aria-labelledby', 'ssc-title');
                win.classList.add('is-closed');

                // Header.
                var head = el('div', 'ssc-head');
                var avatar = el('span', 'ssc-head__avatar');
                if (cfg.avatarUrl) { avatar.innerHTML = '<img src="' + esc(cfg.avatarUrl) + '" alt="" />'; }
                head.appendChild(avatar);
                var titles = el('div', 'ssc-head__titles');
                titles.appendChild(el('strong', 'ssc-head__title', esc(cfg.assistantName || '')));
                var statusLine = el('span', 'ssc-head__status', esc(cfg.orgName || ''));
                if (avail && avail.online === false) {
                        statusLine.textContent = ((cfg.i18n && cfg.i18n.offline) || 'Offline') + ' · ' + (cfg.orgName || '');
                        root.setAttribute('data-status', 'offline');
                }
                titles.appendChild(statusLine);
                titles.id = 'ssc-title';
                head.appendChild(titles);

                var actions = el('div', 'ssc-head__actions');
                var resetBtn = el('button', 'ssc-iconbtn', '&#8635;');
                resetBtn.type = 'button';
                resetBtn.title = (cfg.i18n && cfg.i18n.newConversation) || 'New conversation';
                resetBtn.setAttribute('aria-label', resetBtn.title);
                resetBtn.addEventListener('click', function () {
                        if (state.loading) { return; }
                        if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
                        state.items = []; state.product = null; state.hadConversation = false; state.csatDone = false;
                        thread.textContent = '';
                        try { sessionStorage.removeItem(THREAD_KEY); } catch (e) {}
                        startConversation(); input.focus();
                });
                actions.appendChild(resetBtn);

                // Voice output toggle (module-gated).
                if (cfg.features && cfg.features.voiceOutput) {
                        var speakBtn = el('button', 'ssc-iconbtn ssc-speak-all');
                        speakBtn.type = 'button';
                        speakBtn.title = (cfg.i18n && cfg.i18n.speak) || 'Listen';
                        speakBtn.setAttribute('aria-label', (cfg.i18n && cfg.i18n.speak) || 'Listen');
                        speakBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>';
                        speakBtn.addEventListener('click', function () { toggleSpeakLast(); });
                        actions.appendChild(speakBtn);
                }

                var closeBtn = el('button', 'ssc-iconbtn ssc-close');
                closeBtn.type = 'button';
                closeBtn.title = (cfg.i18n && cfg.i18n.close) || 'Close';
                closeBtn.setAttribute('aria-label', (cfg.i18n && cfg.i18n.close) || 'Close');
                closeBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                closeBtn.addEventListener('click', function () { toggleWindow(false); });
                actions.appendChild(closeBtn);
                head.appendChild(actions);
                win.appendChild(head);

                // Live thread region (created ONCE, never destroyed: reliable SR announcements).
                thread = el('div', 'ssc-thread');
                thread.setAttribute('role', 'log');
                thread.setAttribute('aria-live', 'polite');
                thread.setAttribute('aria-relevant', 'additions');
                win.appendChild(thread);

                // Disclaimer.
                if (cfg.disclaimer) {
                        win.appendChild(el('p', 'ssc-disclaimer', esc(cfg.disclaimer)));
                }

                // Composer.
                composer = el('form', 'ssc-composer');
                composer.setAttribute('novalidate', 'novalidate');
                input = el('input', 'ssc-input');
                input.type = 'text';
                input.id = uid();
                input.setAttribute('placeholder', (cfg.i18n && cfg.i18n.placeholder) || '');
                input.setAttribute('aria-label', (cfg.i18n && cfg.i18n.inputLabel) || 'Message');
                input.autocomplete = 'off';
                input.maxLength = 2000;

                var left = el('div', 'ssc-composer__left');
                if (cfg.features && cfg.features.voiceInput && voiceSupported()) {
                        var micBtn = el('button', 'ssc-iconbtn ssc-mic');
                        micBtn.type = 'button';
                        micBtn.title = (cfg.i18n && cfg.i18n.mic) || 'Speak';
                        micBtn.setAttribute('aria-label', (cfg.i18n && cfg.i18n.mic) || 'Speak');
                        micBtn.setAttribute('aria-pressed', 'false');
                        micBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>';
                        micBtn.addEventListener('click', toggleDictation);
                        left.appendChild(micBtn);
                }
                composer.appendChild(left);
                composer.appendChild(input);

                var sendBtn = el('button', 'ssc-send');
                sendBtn.type = 'submit';
                sendBtn.setAttribute('aria-label', (cfg.i18n && cfg.i18n.send) || 'Send');
                sendBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
                composer.appendChild(sendBtn);
                composer.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var text = input.value.trim();
                        if (text && !state.loading) {
                                input.value = '';
                                userSend(text);
                        }
                });
                win.appendChild(composer);

                root.appendChild(win);
        }

        /* ------------------------------------------------------------------ *
         * Open/close (visibility + inert = keyboard safe)
         * ------------------------------------------------------------------ */

        var lastFocus = null;

        function toggleWindow(force) {
                var open = (force !== undefined) ? force : !state.open;
                if (open === state.open) { return; }
                state.open = open;

                launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
                win.classList.toggle('is-closed', !open);
                win.classList.toggle('is-open', open);
                if (win.inert !== undefined) { win.inert = !open; }

                if (open) {
                        lastFocus = document.activeElement;
                        if (!state.started) { startConversation(); }
                        window.setTimeout(function () { input.focus(); }, 60);
                        proactiveDismiss();
                        clearUnread();
                } else {
                        if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
                        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
                }
        }

        /* ------------------------------------------------------------------ *
         * Message rendering (append-only)
         * ------------------------------------------------------------------ */

        function scrollDown() {
                thread.scrollTop = thread.scrollHeight;
        }

        function addItem(kind, text, opts) {
                opts = opts || {};
                var bubble = el('div', 'ssc-msg ssc-msg--' + kind);
                if (kind === 'user') {
                        bubble.innerHTML = md(text);
                } else {
                        bubble.innerHTML = md(text);
                }
                if (opts.id) { bubble.id = opts.id; }
                thread.appendChild(bubble);
                state.items.push({ kind: kind, text: text, node: bubble, history: opts.history !== false });
                scrollDown();
                saveThread();
                return bubble;
        }

        function removeItem(node) {
                state.items = state.items.filter(function (item) { return item.node !== node; });
                if (node && node.parentElement) { node.parentElement.removeChild(node); }
                saveThread();
        }

        function addChips(chips) {
                if (!chips || !chips.length) { return; }
                var wrap = el('div', 'ssc-chips');
                chips.forEach(function (chip) {
                        var btn = el('button', 'ssc-chip', esc(chip.label));
                        btn.type = 'button';
                        btn.addEventListener('click', function () {
                                wrap.parentElement.removeChild(wrap);
                                chip.onClick();
                        });
                        wrap.appendChild(btn);
                });
                thread.appendChild(wrap);
                scrollDown();
        }

        function showTyping() {
                var typing = el('div', 'ssc-msg ssc-msg--bot ssc-typing', '<span class="ssc-typing__dot"></span><span class="ssc-typing__dot"></span><span class="ssc-typing__dot"></span>');
                thread.appendChild(typing);
                scrollDown();
                return typing;
        }

        function typeInto(node, text) {
                if (REDUCED_MOTION) { return; }
                // Gradual reveal of already-rendered HTML is complex; instead we reveal
                // plain text progressively then swap in rich HTML at the end.
                var plain = node.innerHTML;
                node.textContent = '…';
                var i = 0;
                var total = text.length;
                var step = Math.max(1, Math.ceil(total / 90));
                var timer = window.setInterval(function () {
                        i += step;
                        node.textContent = text.slice(0, i);
                        scrollDown();
                        if (i >= total) {
                                window.clearInterval(timer);
                                node.innerHTML = plain;
                                scrollDown();
                        }
                }, 12);
        }

        /* ------------------------------------------------------------------ *
         * Conversation flow
         * ------------------------------------------------------------------ */

        function startConversation() {
                var saved = loadThread();
                state.started = true;
                var welcome = (cfg.welcomeTitle ? cfg.welcomeTitle + '\n' : '') + stripHtml(cfg.welcomeText || '');
                if (avail && avail.online === false && avail.offlineMessage) {
                        welcome += '\n\n' + avail.offlineMessage;
                }
                addItem('bot', welcome, { history: false });
                mainMenu();
                restoreThread(saved);
        }

        function stripHtml(text) {
                return new DOMParser().parseFromString(String(text || ''), 'text/html').body.textContent || '';
        }

        function mainMenu() {
                var chips = [];
                var i18n = cfg.i18n || {};
                chips.push({ label: i18n.askUs || 'Ask us', onClick: function () { focusProduct(null); } });
                if (cfg.products && cfg.products.length) {
                        chips.push({ label: i18n.products || 'Products', onClick: chooseProduct });
                }
                if (cfg.features && cfg.features.leads) {
                        chips.push({ label: i18n.requestForm || 'Request', onClick: showLeadForm });
                }
                if (cfg.features && cfg.features.pharma) {
                        chips.push({ label: i18n.reportAdr || 'Report side effect', onClick: showAdrForm });
                }
                addChips(chips);
        }

        function restoreThread(saved) {
                if (!saved || !saved.items || cfg.preview) { return; }
                // Restore only the message transcript (forms/CSAT are one-shot).
                saved.items.forEach(function (item) {
                        if ((item.k === 'user' || item.k === 'bot') && item.t) {
                                addItem(item.k, item.t, { history: item.h });
                        }
                });
                state.product = saved.product || null;
                state.hadConversation = true;
        }

        function chooseProduct() {
                var chips = (cfg.products || []).map(function (p) {
                        return {
                                label: p.name,
                                onClick: function () { focusProduct(p); }
                        };
                });
                if (!chips.length) { return; }
                var i18n = cfg.i18n || {};
                addItem('bot', i18n.chooseProduct || 'Which one?', { history: false });
                addChips(chips);
        }

        function focusProduct(product) {
                state.product = product ? product.id : null;
                if (product) {
                        addItem('bot', (product.name ? '**' + product.name + '**\n' : '') + (stripHtml(product.summary || '')).slice(0, 320), { history: false });
                        if (product.brochure) {
                                var wrap = el('div', 'ssc-chips');
                                var btn = el('button', 'ssc-chip ssc-chip--outline', esc((cfg.i18n && cfg.i18n.brochure) || 'Brochure'));
                                btn.type = 'button';
                                btn.addEventListener('click', function () {
                                        window.open(product.brochure, '_blank', 'noopener');
                                });
                                wrap.appendChild(btn);
                                thread.appendChild(wrap);
                                scrollDown();
                        }
                }
        }

        function userSend(text) {
                if (state.loading || !String(text).trim()) { return; }
                addItem('user', text);
                state.hadConversation = true;
                ask(text);
        }

        function ask(text) {
                state.loading = true;
                var typing = showTyping();
                var streamBuf = '';
                var bubble = null;
                var streaming = false;

                function ensureBubble() {
                        if (!bubble) {
                                if (typing && typing.parentElement) { typing.parentElement.removeChild(typing); typing = null; }
                                bubble = el('div', 'ssc-msg ssc-msg--bot is-streaming');
                                bubble.textContent = '';
                                thread.appendChild(bubble);
                                scrollDown();
                        }
                        return bubble;
                }

                sendChatStream(text, function (delta) {
                        streaming = true;
                        streamBuf += delta;
                        var node = ensureBubble();
                        node.textContent = streamBuf;
                        scrollDown();
                }).then(function (res) {
                        if (typing && typing.parentElement) { typing.parentElement.removeChild(typing); }
                        state.loading = false;

                        var data = (res && res.data) || {};
                        var reply = data.reply;
                        if (!res || !res.success || reply === undefined) {
                                var i18n = cfg.i18n || {};
                                var code = data && data.code;
                                var msg = (code === 'ssc_rate_limited')
                                        ? (i18n.rateLimited || 'Rate limit reached.')
                                        : (code === 'ssc_bad_nonce' || code === 'ssc_session_expired' || data && (-1 === data.message))
                                                ? (i18n.sessionExpired || 'Session expired — refresh the page.')
                                                : (i18n.connectionError || 'Connection error.');
                                if (bubble && bubble.parentElement) { bubble.parentElement.removeChild(bubble); }
                                addItem('bot', msg, { history: false });
                                bumpUnread();
                                return;
                        }

                        var node;
                        if (bubble) {
                                bubble.classList.remove('is-streaming');
                                // Finalize the streaming bubble into a normal item.
                                bubble.innerHTML = md(reply);
                                state.items.push({ kind: 'bot', text: reply, node: bubble, history: true });
                                saveThread();
                                node = bubble;
                                scrollDown();
                        } else {
                                node = addItem('bot', reply);
                                // The final DOM is stable: feedback handlers must not be replaced by an animation.
                        }
                        handleFlags(data);
                        if (cfg.features && cfg.features.feedback && data.log_id) {
                                feedbackControls(node, data.log_id, data.log_token);
                        }
                        if (!state.open) { bumpUnread(); maybeBeep(); }
                }).catch(function () {
                        if (typing && typing.parentElement) { typing.parentElement.removeChild(typing); }
                        state.loading = false;
                        if (bubble && bubble.parentElement) { bubble.parentElement.removeChild(bubble); }
                        addItem('bot', (cfg.i18n && cfg.i18n.connectionError) || 'Connection error.', { history: false });
                });
        }

        function handleFlags(data) {
                var i18n = cfg.i18n || {};
                if (data.flags && data.flags.adr_offer && cfg.features && cfg.features.pharma) {
                        addChips([{ label: i18n.reportAdr || 'Report side effect', onClick: showAdrForm }]);
                        return;
                }
                if (data.handoff && cfg.features && cfg.features.handoff) {
                        addItem('bot', cfg.handoffText || '', { history: false });
                        addChips([{ label: i18n.handoffBtn || 'Talk to a human', onClick: showLeadForm }]);
                        return;
                }
                if (cfg.features && cfg.features.faq) {
                        suggestRelated();
                }
        }

        function feedbackControls(node, logId, logToken) {
                var bar = el('div', 'ssc-feedback');
                ['👍', '👎'].forEach(function (face, idx) {
                        var btn = el('button', 'ssc-feedback__btn', face);
                        btn.type = 'button';
                        btn.title = idx === 0 ? '👍' : '👎';
                        btn.setAttribute('aria-label', idx === 0 ? ((cfg.i18n && cfg.i18n.goodAnswer) || 'Good answer') : ((cfg.i18n && cfg.i18n.poorAnswer) || 'Poor answer'));
                        btn.addEventListener('click', function () {
                                bar.innerHTML = '✓';
                                transport('feedback', {
                                        log_id: String(logId),
                                        rating: idx === 0 ? '1' : '-1',
                                        log_token: logToken || ''
                                }).catch(function () { /* fire and forget */ });
                        });
                        bar.appendChild(btn);
                });
                node.appendChild(bar);
        }

        function suggestRelated() {
                var text = lastQuestion();
                if (!text) { return; }
                transport('suggest', { term: text, product: state.product || 'general' }).then(function (res) {
                        var items = res && res.data && res.data.items;
                        if (!items || !items.length) { return; }
                        var chips = items.slice(0, 3).map(function (item) {
                                return { label: item.question.slice(0, 48), onClick: function () { userSend(item.question); } };
                        });
                        addChips(chips);
                }).catch(function () { /* silent */ });
        }

        function lastQuestion() {
                for (var i = state.items.length - 1; i >= 0; --i) {
                        if (state.items[i].kind === 'user') { return state.items[i].text; }
                }
                return null;
        }

        /* ------------------------------------------------------------------ *
         * Lead form (leads module)
         * ------------------------------------------------------------------ */

        function showLeadForm() {
                var i18n = cfg.i18n || {};
                var card = el('div', 'ssc-cardform');
                card.appendChild(el('h3', 'ssc-cardform__title', esc(i18n.requestForm || 'Request')));

                var form = el('form', 'ssc-cardform__form');
                form.setAttribute('novalidate', 'novalidate');

                // Honeypot.
                var hp = el('input', 'ssc-hp');
                hp.type = 'text';
                hp.name = 'ssc_hp';
                hp.tabIndex = -1;
                hp.setAttribute('aria-hidden', 'true');
                hp.autocomplete = 'off';
                form.appendChild(hp);

                function field(label, name, type, required, extra) {
                        var wrap = el('label', 'ssc-f');
                        wrap.appendChild(el('span', 'ssc-f__label', esc(label) + (required ? ' *' : '')));
                        var input = el('input', 'ssc-f__input');
                        input.type = type || 'text';
                        input.name = name;
                        if (type === 'tel' || type === 'email' || type === 'number') { input.dir = 'ltr'; }
                        if (extra && extra.placeholder) { input.placeholder = extra.placeholder; }
                        if (required) { input.required = true; }
                        wrap.appendChild(input);
                        form.appendChild(wrap);
                        return input;
                }

                field(i18n.formName || 'Name', 'name', 'text', true);
                field(i18n.formPhone || 'Phone', 'phone', 'tel', true);

                // Configured custom fields (validated server-side against definitions).
                (cfg.formFields || []).forEach(function (f) {
                        if (f.type === 'textarea') {
                                var wrap = el('label', 'ssc-f');
                                wrap.appendChild(el('span', 'ssc-f__label', esc(f.label) + (f.required ? ' *' : '')));
                                var ta = el('textarea', 'ssc-f__input');
                                ta.name = 'extra[' + f.key + ']';
                                ta.rows = 2;
                                if (f.required) { ta.required = true; }
                                if (f.placeholder) { ta.placeholder = f.placeholder; }
                                wrap.appendChild(ta);
                                form.appendChild(wrap);
                        } else if (f.type === 'select' || f.type === 'radio') {
                                var wrap2 = el('label', 'ssc-f');
                                wrap2.appendChild(el('span', 'ssc-f__label', esc(f.label) + (f.required ? ' *' : '')));
                                var sel = el('select', 'ssc-f__input');
                                sel.name = 'extra[' + f.key + ']';
                                var emptyProduct = el('option', '', '—'); emptyProduct.value = ''; sel.appendChild(emptyProduct);
                                
                                (f.options || []).forEach(function (opt) {
                                        var o = el('option', '', esc(opt));
                                        o.value = opt;
                                        sel.appendChild(o);
                                });
                                wrap2.appendChild(sel);
                                form.appendChild(wrap2);
                        } else if (f.type === 'checkbox') {
                                var wrap3 = el('label', 'ssc-f ssc-f--check');
                                var cb = el('input', 'ssc-f__check');
                                cb.type = 'checkbox';
                                cb.name = 'extra[' + f.key + ']';
                                cb.value = 'yes';
                                wrap3.appendChild(cb);
                                wrap3.appendChild(el('span', 'ssc-f__label', esc(f.label)));
                                form.appendChild(wrap3);
                        } else {
                                field(f.label, 'extra[' + f.key + ']', f.type, !!f.required, { placeholder: f.placeholder });
                        }
                });

                var msgLabel = el('label', 'ssc-f');
                msgLabel.appendChild(el('span', 'ssc-f__label', esc(i18n.formMessage || 'Message') + ' *'));
                var msg = el('textarea', 'ssc-f__input');
                msg.name = 'description';
                msg.rows = 3;
                msg.required = true;
                msgLabel.appendChild(msg);
                form.appendChild(msgLabel);

                // Consent (enforced server-side when enabled).
                if (cfg.consent && cfg.consent.enabled) {
                        var cw = el('label', 'ssc-f ssc-f--check');
                        var cc = el('input', 'ssc-f__check');
                        cc.type = 'checkbox';
                        cc.name = 'consent';
                        cc.value = '1';
                        cc.required = true;
                        cw.appendChild(cc);
                        var ct = el('span', 'ssc-f__label', esc(cfg.consent.text || ''));
                        if (cfg.consent.link) {
                                ct.appendChild(el('a', '', esc((cfg.i18n && cfg.i18n.privacy) || 'Privacy')));
                                ct.lastChild.href = cfg.consent.link;
                                ct.lastChild.target = '_blank';
                                ct.lastChild.rel = 'noopener noreferrer';
                                ct.appendChild(document.createTextNode(' '));
                        }
                        cw.appendChild(ct);
                        form.appendChild(cw);
                }

                var err = el('p', 'ssc-cardform__error', '');
                err.setAttribute('role', 'alert');
                form.appendChild(err);

                var submit = el('button', 'ssc-btn2', esc(i18n.formSubmit || 'Submit'));
                submit.type = 'submit';
                form.appendChild(submit);

                form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        err.textContent = '';
                        submit.setAttribute('disabled', 'disabled');

                        var params = { type: 'consult' };
                        new FormData(form).forEach(function (value, key) {
                                if (key.indexOf('extra[') === 0) {
                                        var m = /^extra\[(.+)\]$/.exec(key);
                                        if (m) {
                                                params.extra = params.extra || {};
                                                params.extra[m[1]] = value;
                                        }
                                } else {
                                        params[key] = value;
                                }
                        });
                        params.history = lastQuestion() || '';

                        submitForm(card, form, params, 'submit');
                });

                card.appendChild(form);
                thread.appendChild(card);
                scrollDown();
        }

        /* ------------------------------------------------------------------ *
         * ADR form (pharma module)
         * ------------------------------------------------------------------ */

        function showAdrForm() {
                var card = el('div', 'ssc-cardform ssc-cardform--adr');
                card.appendChild(el('h3', 'ssc-cardform__title', esc((cfg.i18n && cfg.i18n.reportAdr) || 'Report side effect')));

                var form = el('form', 'ssc-cardform__form');
                form.setAttribute('novalidate', 'novalidate');

                var hp = el('input', 'ssc-hp');
                hp.type = 'text';
                hp.name = 'ssc_hp';
                hp.tabIndex = -1;
                hp.setAttribute('aria-hidden', 'true');
                form.appendChild(hp);

                (cfg.adrOptions || []).forEach(function (f) {
                        if ('checkboxes' === f.type) {
                                var fs = el('fieldset', 'ssc-f ssc-f--group');
                                fs.appendChild(el('legend', 'ssc-f__label', esc(f.label)));
                                (f.options || []).forEach(function (opt) {
                                        var lab = el('label', 'ssc-f--check');
                                        var cb = el('input', 'ssc-f__check');
                                        cb.type = 'checkbox';
                                        cb.name = f.key + '[]';
                                        cb.value = opt.value;
                                        lab.appendChild(cb);
                                        lab.appendChild(el('span', 'ssc-f__label', esc(opt.label)));
                                        fs.appendChild(lab);
                                });
                                form.appendChild(fs);
                                return;
                        }
                        if (f.type === 'product') {
                                var wrap = el('label', 'ssc-f');
                                wrap.appendChild(el('span', 'ssc-f__label', esc(f.label) + (f.required ? ' *' : '')));
                                var sel = el('select', 'ssc-f__input');
                                sel.name = f.key;
                                if (f.required) { sel.required = true; }
                                var emptyProduct = el('option', '', '—'); emptyProduct.value = ''; sel.appendChild(emptyProduct);
                                
                                (cfg.products || []).forEach(function (p) {
                                        var o = el('option', '', esc(p.name));
                                        o.value = p.id;
                                        sel.appendChild(o);
                                });
                                if (state.product) { sel.value = state.product; }
                                wrap.appendChild(sel);
                                form.appendChild(wrap);
                                return;
                        }
                        if (f.type === 'textarea') {
                                var wrap2 = el('label', 'ssc-f');
                                wrap2.appendChild(el('span', 'ssc-f__label', esc(f.label) + (f.required ? ' *' : '')));
                                var ta = el('textarea', 'ssc-f__input');
                                ta.name = f.key;
                                ta.rows = 3;
                                if (f.required) { ta.required = true; }
                                wrap2.appendChild(ta);
                                form.appendChild(wrap2);
                                return;
                        }
                        var wrap3 = el('label', 'ssc-f');
                        wrap3.appendChild(el('span', 'ssc-f__label', esc(f.label) + (f.required ? ' *' : '')));
                        var input = el('input', 'ssc-f__input');
                        input.type = (f.type === 'select') ? 'text' : (f.type || 'text');
                        if (f.type === 'select') {
                                var sel2 = el('select', 'ssc-f__input');
                                sel2.name = f.key;
                                var emptyChoice = el('option', '', '—'); emptyChoice.value = ''; sel2.appendChild(emptyChoice);
                                (f.options || []).forEach(function (opt) {
                                        var o = el('option', '', esc(opt.label));
                                        o.value = opt.value;
                                        sel2.appendChild(o);
                                });
                                wrap3.appendChild(sel2);
                                sel2.required = !!f.required;
                                form.appendChild(wrap3);
                                return;
                        }
                        input.name = f.key;
                        if (f.type === 'tel' || f.type === 'number') { input.dir = 'ltr'; }
                        if (f.type === 'number') { input.min = '0'; input.max = '130'; input.step = 'any'; }
                        if (f.required) { input.required = true; }
                        wrap3.appendChild(input);
                        form.appendChild(wrap3);
                });

                // Consent is ALWAYS required for ADR (sensitive health data), even
                // when the global leads-consent toggle is off.
                var adrConsentWrap = el('label', 'ssc-f ssc-f--check');
                var adrConsent = el('input', 'ssc-f__check');
                adrConsent.type = 'checkbox';
                adrConsent.name = 'consent';
                adrConsent.value = '1';
                adrConsent.required = true;
                adrConsentWrap.appendChild(adrConsent);
                var adrConsentLabel = el('span', 'ssc-f__label', esc((cfg.consent && cfg.consent.text) || ((cfg.i18n && cfg.i18n.consentRequired) || 'I consent to the processing of this safety report.')));
                if (cfg.consent && cfg.consent.link) {
                        adrConsentLabel.appendChild(document.createTextNode(' '));
                        var adrPrivacy = el('a', '', esc((cfg.i18n && cfg.i18n.privacy) || 'Privacy'));
                        adrPrivacy.href = cfg.consent.link;
                        adrPrivacy.target = '_blank';
                        adrPrivacy.rel = 'noopener noreferrer';
                        adrConsentLabel.appendChild(adrPrivacy);
                }
                adrConsentWrap.appendChild(adrConsentLabel);
                form.appendChild(adrConsentWrap);

                var err = el('p', 'ssc-cardform__error', '');
                err.setAttribute('role', 'alert');
                form.appendChild(err);

                var submit = el('button', 'ssc-btn2', esc((cfg.i18n && cfg.i18n.formSubmit) || 'Submit'));
                submit.type = 'submit';
                form.appendChild(submit);

                form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        err.textContent = '';
                        submit.setAttribute('disabled', 'disabled');

                        var params = { type: 'pharma_adr' };
                        new FormData(form).forEach(function (value, key) {
                                if (/\[\]$/.test(key)) {
                                        var base = key.slice(0, -2);
                                        params[base] = params[base] || [];
                                        params[base].push(value);
                                } else {
                                        params[key] = value;
                                }
                        });

                        transport('submit', toFormParams(params)).then(function (res) {
                                handleFormResult(card, form, res);
                        }).catch(function () {
                                handleFormResult(card, form, null);
                        });
                });

                card.appendChild(form);
                thread.appendChild(card);
                scrollDown();
        }

        function toFormParams(params) {
                // transport() uses URLSearchParams: flatten arrays/objects to JSON.
                var out = {};
                Object.keys(params).forEach(function (k) {
                        var v = params[k];
                        out[k] = (v && typeof v === 'object') ? JSON.stringify(v) : v;
                });
                return out;
        }

        function submitForm(card, form, params) {
                transport('submit', toFormParams(params)).then(function (res) {
                        handleFormResult(card, form, res);
                }).catch(function () {
                        handleFormResult(card, form, null);
                });
        }

        function handleFormResult(card, form, res) {
                var i18n = cfg.i18n || {};
                var ok = res && res.success && res.data && (res.data.ok !== false);
                if (ok) {
                        if (card && card.parentElement) {
                                var done = el('div', 'ssc-msg ssc-msg--success', esc(i18n.formSent || 'Received ✓'));
                                card.parentElement.replaceChild(done, card);
                                scrollDown();
                        }
                        return;
                }
                var btn = form && form.querySelector('button[type="submit"]');
                if (btn) { btn.removeAttribute('disabled'); }
                var errEl = form && form.querySelector('.ssc-cardform__error');
                if (errEl) {
                        errEl.textContent = (res && res.data && res.data.message) ? res.data.message : (i18n.formError || 'Could not submit.');
                }
        }

        /* ------------------------------------------------------------------ *
         * CSAT (module)
         * ------------------------------------------------------------------ */

        function maybeOfferCsat() {
                if (!cfg.features || !cfg.features.csat || state.csatDone || !state.hadConversation) { return; }
                if (!state.open) { return; }
                state.csatDone = true;
                var i18n = cfg.i18n || {};
                var card = el('div', 'ssc-cardform ssc-cardform--csat');
                card.appendChild(el('h3', 'ssc-cardform__title', esc(i18n.csatTitle || 'How was it?')));
                var group = el('div', 'ssc-csat');
                group.setAttribute('role', 'radiogroup');
                group.setAttribute('aria-label', i18n.csatTitle || 'Rating');
                for (var i = 1; i <= 5; ++i) {
                        (function (score) {
                                var star = el('button', 'ssc-csat__star', '★');
                                star.type = 'button';
                                star.setAttribute('role', 'radio');
                                star.setAttribute('aria-label', String(score));
                                star.addEventListener('click', function () {
                                        group.innerHTML = esc(i18n.csatThanks || 'Thanks!');
                                        transport('csat', { score: String(score) }).catch(function () { /* silent */ });
                                });
                                group.appendChild(star);
                        }(i));
                }
                card.appendChild(group);
                var skip = el('button', 'ssc-csat__skip', esc(i18n.csatSkip || 'Skip'));
                skip.type = 'button';
                skip.addEventListener('click', function () {
                        if (card.parentElement) { card.parentElement.removeChild(card); }
                });
                card.appendChild(skip);
                thread.appendChild(card);
                scrollDown();
        }

        /* ------------------------------------------------------------------ *
         * Voice (module; Web Speech API)
         * ------------------------------------------------------------------ */

        function voiceSupported() {
                return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
        }

        var recognition = null;

        function toggleDictation() {
                var micBtn = root.querySelector('.ssc-mic');
                if (recognition) {
                        recognition.stop();
                        return;
                }
                var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SR) {
                        if (micBtn) { micBtn.classList.add('is-error'); }
                        return;
                }
                recognition = new SR();
                recognition.lang = cfg.voiceLanguage || 'fa-IR';
                recognition.interimResults = true;
                recognition.continuous = false;
                var base = input.value;

                recognition.onstart = function () {
                        if (micBtn) { micBtn.classList.add('is-live'); micBtn.setAttribute('aria-pressed', 'true'); }
                };
                recognition.onresult = function (event) {
                        var text = '';
                        for (var i = event.resultIndex; i < event.results.length; ++i) {
                                text += event.results[i][0].transcript;
                        }
                        input.value = (base ? base + ' ' : '') + text;
                };
                recognition.onerror = function () {
                        recognition = null;
                        if (micBtn) { micBtn.classList.remove('is-live'); micBtn.setAttribute('aria-pressed', 'false'); micBtn.classList.add('is-error'); }
                };
                recognition.onend = function () {
                        recognition = null;
                        if (micBtn) { micBtn.classList.remove('is-live'); micBtn.setAttribute('aria-pressed', 'false'); }
                };
                recognition.start();
        }

        var speakingNode = null;

        function toggleSpeakLast() {
                var btn = root.querySelector('.ssc-speak-all');
                if (!window.speechSynthesis) { return; }
                if (window.speechSynthesis.speaking) {
                        window.speechSynthesis.cancel();
                        setSpeaking(false, btn);
                        return;
                }
                // Find the last bot message text.
                var text = null;
                for (var i = state.items.length - 1; i >= 0; --i) {
                        if (state.items[i].kind === 'bot') { text = state.items[i].text; break; }
                }
                if (!text) { return; }
                var utter = new window.SpeechSynthesisUtterance(stripHtml(text));
                utter.lang = cfg.voiceLanguage || 'fa-IR';
                var voices = window.speechSynthesis.getVoices();
                var voice = voices.filter(function (v) { return v.lang && v.lang.indexOf(utter.lang.slice(0, 2)) === 0; })[0];
                if (voice) { utter.voice = voice; }
                utter.onend = function () { setSpeaking(false, btn); };
                utter.onerror = function () { setSpeaking(false, btn); };
                window.speechSynthesis.cancel();
                window.setTimeout(function () { window.speechSynthesis.speak(utter); }, 60); // Chrome quirk.
                setSpeaking(true, btn);
        }

        function setSpeaking(on, btn) {
                speakingNode = on ? btn : null;
                if (btn) { btn.classList.toggle('is-speaking', on); }
        }

        if (window.speechSynthesis && window.speechSynthesis.onvoiceschanged !== undefined) {
                window.speechSynthesis.onvoiceschanged = function () { /* voices cached lazily */ };
        }

        /* ------------------------------------------------------------------ *
         * Proactive invitation (module; restrained)
         * ------------------------------------------------------------------ */

        var proactiveBubble = null;

        function setupProactive() {
                if (!cfg.features || !cfg.features.proactive || cfg.preview) { return; }
                try { if (sessionStorage.getItem('ssc_proactive') === 'off') { return; } } catch (e) { /* ignore */ }

                var delay = Math.max(2, (cfg.proactiveDelay || 12)) * 1000;
                window.setTimeout(showProactive, delay);
        }

        function showProactive() {
                if (state.open || proactiveBubble) { return; }
                proactiveBubble = el('div', 'ssc-proactive');
                proactiveBubble.appendChild(el('p', 'ssc-proactive__text', esc(cfg.proactiveText || '')));
                proactiveBubble.addEventListener('click', function () {
                        proactiveDismiss();
                        toggleWindow(true);
                });
                root.appendChild(proactiveBubble);
                window.setTimeout(proactiveDismiss, 8000);
        }

        function proactiveDismiss() {
                try { sessionStorage.setItem('ssc_proactive', 'off'); } catch (e) { /* ignore */ }
                if (proactiveBubble && proactiveBubble.parentElement) {
                        proactiveBubble.parentElement.removeChild(proactiveBubble);
                }
                proactiveBubble = null;
        }

        /* ------------------------------------------------------------------ *
         * Keyboard: Escape closes; focus containment (soft trap)
         * ------------------------------------------------------------------ */

        document.addEventListener('keydown', function (e) {
                // Global toggle: Alt+C (or Alt+Shift+C on some layouts).
                if (e.altKey && !e.ctrlKey && !e.metaKey && (e.key === 'c' || e.key === 'C' || e.code === 'KeyC')) {
                        var tag = (e.target && e.target.tagName) || '';
                        if ('INPUT' !== tag && 'TEXTAREA' !== tag && 'SELECT' !== tag) {
                                e.preventDefault();
                                toggleWindow();
                                return;
                        }
                }
                if (!state.open || !win || !win.contains(e.target)) { return; }
                if ('Escape' === e.key) {
                        toggleWindow(false);
                        return;
                }
                if ('Tab' === e.key) {
                        var focusables = Array.prototype.filter.call(win.querySelectorAll('button, input, textarea, select, a[href]'), function (node) { return !node.disabled && node.tabIndex !== -1 && node.getClientRects().length > 0; });
                        if (!focusables.length) { return; }
                        var first = focusables[0];
                        var last = focusables[focusables.length - 1];
                        if (e.shiftKey && document.activeElement === first) {
                                e.preventDefault();
                                last.focus();
                        } else if (!e.shiftKey && document.activeElement === last) {
                                e.preventDefault();
                                first.focus();
                        }
                }
        });

        // CSAT offered when the visitor closes after a real conversation.
        var csatHook = function () { maybeOfferCsat(); };

        /* ------------------------------------------------------------------ *
         * Boot
         * ------------------------------------------------------------------ */

        function boot() {
                root.setAttribute('dir', cfg.direction || 'rtl');
                var vars = cssVars();
                Object.keys(vars).forEach(function (k) { root.style.setProperty(k, vars[k]); });
                applyTheme();

                if (!deviceAllowed()) {
                        return; // Device rule: no launcher, no window, no listeners.
                }

                buildLauncher();
                buildWindow();

                if (cfg.themeMode === 'auto' && window.matchMedia) {
                        try {
                                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);
                        } catch (e) { /* older browsers */ }
                }

                setupProactive();

                // Offer CSAT when closing the window after a conversation.
                document.addEventListener('click', function (e) {
                        if (state.open) { return; }
                        var closeBtn = e.target.closest && e.target.closest('.ssc-close');
                        if (closeBtn) { window.setTimeout(csatHook, 50); }
                });

                window.addEventListener('beforeunload', function () {
                        if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
                });
        }

        boot();

        /* ------------------------------------------------------------------ *
         * Public API (preview mount for the wizard)
         * ------------------------------------------------------------------ */

        window.SSCChatbot = {
                toggle: toggleWindow,
                mountPreview: function (mountNode) {
                        if (!mountNode || !win) { return; }
                        mountNode.classList.add('ssc-root', 'ssc-preview-mount');
                        mountNode.setAttribute('dir', cfg.direction || 'rtl');
                        var vars = cssVars();
                        Object.keys(vars).forEach(function (k) { mountNode.style.setProperty(k, vars[k]); });
                        applyTheme();
                        mountNode.appendChild(launcher);
                        mountNode.appendChild(win);
                        launcher.hidden = true;
                        win.classList.add('ssc-window--inline');
                        toggleWindow(true);
                }
        };
})();
