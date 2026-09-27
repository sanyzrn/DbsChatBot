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
        var COARSE_POINTER = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
        var ICON_CHAT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
        var ICON_CLOSE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        var ICON_BOT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 4v4M9 13h.01M15 13h.01M9.5 16.5h5"/></svg>';

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

        /** Inline markdown on already-escaped text: code, links, bare URLs, bold, italic. */
        function inlineMd(escaped) {
                var codes = [];
                escaped = escaped.replace(/`([^`\n]+)`/g, function (m, code) {
                        codes.push(code);
                        return '\u0000' + (codes.length - 1) + '\u0000';
                });
                escaped = escaped.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, function (m, label, url) {
                        return '<a href="' + url + '" target="_blank" rel="noopener noreferrer nofollow">' + label + '</a>';
                });
                // Bare URLs (never inside an attribute: those are preceded by a quote).
                escaped = escaped.replace(/(^|[\s(])(https?:\/\/[^\s<]*[^\s<.,;:!?)])/g, function (m, lead, url) {
                        return lead + '<a href="' + url + '" target="_blank" rel="noopener noreferrer nofollow">' + url + '</a>';
                });
                escaped = escaped.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
                escaped = escaped.replace(/(^|[^*\w])\*([^*\s][^*\n]*?)\*(?![*\w])/g, '$1<em>$2</em>');
                return escaped.replace(/\u0000(\d+)\u0000/g, function (m, i) { return '<code>' + codes[+i] + '</code>'; });
        }

        /**
         * Safe markdown subset for AI replies: paragraphs, headings, bullet and
         * numbered lists plus inline formatting. Everything is escaped first, so
         * model output can never inject markup.
         */
        function md(text) {
                var lines = esc(text).replace(/\r\n?/g, '\n').split('\n');
                var html = '', para = [], list = null;
                function flushPara() {
                        if (para.length) { html += '<p>' + para.map(inlineMd).join('<br>') + '</p>'; }
                        para = [];
                }
                function flushList() {
                        if (list) { html += '</' + list + '>'; }
                        list = null;
                }
                lines.forEach(function (line) {
                        var m;
                        if ((m = /^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/.exec(line))) {
                                flushPara(); flushList();
                                html += '<p class="ssc-md-h"><strong>' + inlineMd(m[1]) + '</strong></p>';
                        } else if ((m = /^\s*[-*\u2022]\s+(.+)$/.exec(line))) {
                                flushPara();
                                if (list !== 'ul') { flushList(); html += '<ul>'; list = 'ul'; }
                                html += '<li>' + inlineMd(m[1]) + '</li>';
                        } else if ((m = /^\s*(\d{1,3})[.)]\s+(.+)$/.exec(line))) {
                                flushPara();
                                if (list !== 'ol') { flushList(); html += '<ol' + (m[1] !== '1' ? ' start="' + (+m[1]) + '"' : '') + '>'; list = 'ol'; }
                                html += '<li>' + inlineMd(m[2]) + '</li>';
                        } else if (/^\s*$/.test(line)) {
                                flushPara(); flushList();
                        } else {
                                flushList();
                                para.push(line);
                        }
                });
                flushPara(); flushList();
                return html;
        }

        /** Markdown-free text (screen readers, speech, clipboard). */
        function plainText(text) {
                return String(text || '')
                        .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '$1 ($2)')
                        .replace(/^\s{0,3}#{1,6}\s+/gm, '')
                        .replace(/\*\*([^*\n]+)\*\*/g, '$1')
                        .replace(/`([^`\n]+)`/g, '$1');
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
                                // Only real conversation turns are persisted: the welcome message,
                                // menus and one-shot notices are rebuilt on every page.
                                items: state.items.filter(function (item) { return item.history && !item.transient; }).slice(-60).map(function (item) {
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
                        conv: getConv()
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
                body.append('conv', getConv());
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

        /*
         * Conversation id: 128 random bits. The server keeps the transcript it
         * actually produced under this id, so the browser never supplies the
         * model's context (and cannot forge assistant turns).
         */
        var CONV_KEY = 'ssc_conv_v1';
        var convId = null;

        function newConvId() {
                var bytes = new Uint8Array(16), hex = '';
                if (window.crypto && window.crypto.getRandomValues) {
                        window.crypto.getRandomValues(bytes);
                } else {
                        for (var i = 0; i < 16; ++i) { bytes[i] = Math.floor(Math.random() * 256); }
                }
                for (var j = 0; j < 16; ++j) { hex += ('0' + bytes[j].toString(16)).slice(-2); }
                return hex;
        }

        function getConv() {
                if (convId) { return convId; }
                if (state.persist) {
                        try { convId = sessionStorage.getItem(CONV_KEY); } catch (e) { convId = null; }
                }
                if (!convId || !/^[a-f0-9]{32}$/.test(convId)) {
                        convId = newConvId();
                        if (state.persist) {
                                try { sessionStorage.setItem(CONV_KEY, convId); } catch (e) { /* storage unavailable */ }
                        }
                }
                return convId;
        }

        function resetConv() {
                convId = null;
                try { sessionStorage.removeItem(CONV_KEY); } catch (e) { /* storage unavailable */ }
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
        var launcher, win, thread, composer, input, live, statusLine;
        var statusCheckedAt = 0;

        /** Header status line + launcher dot from the current availability. */
        function paintStatus() {
                var offline = avail && avail.online === false;
                if (offline) { root.setAttribute('data-status', 'offline'); } else { root.removeAttribute('data-status'); }
                if (statusLine) {
                        statusLine.textContent = offline ? ((cfg.i18n && cfg.i18n.offline) || 'Offline') + ' · ' + (cfg.orgName || '') : (cfg.orgName || '');
                }
        }

        /*
         * Business hours are evaluated at render time; a cached page would keep
         * showing that moment's status for hours. Re-read it live (at most once
         * every five minutes) when business hours are enabled.
         */
        function refreshStatus() {
                if (!avail || !avail.dynamic || !cfg.restUrl || cfg.preview || !window.fetch) { return; }
                if (Date.now() - statusCheckedAt < 300000) { return; }
                statusCheckedAt = Date.now();
                var url = cfg.restUrl + 'status';
                fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (res) {
                        return res.ok ? res.json() : null;
                }).then(function (data) {
                        if (!data || typeof data.online !== 'boolean') { return; }
                        avail.online = data.online;
                        avail.offlineMessage = data.offlineMessage || '';
                        paintStatus();
                }).catch(function () { /* keep the rendered status */ });
        }

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
                        launcher.appendChild(el('span', 'ssc-launcher__icon', ICON_CHAT));
                }
                // Swapped in while the window is open so the same button reads as "close".
                launcher.appendChild(el('span', 'ssc-launcher__close', ICON_CLOSE));
                launcher.appendChild(el('span', 'ssc-launcher__dot', ''));
                var badge = el('span', 'ssc-launcher__badge', '');
                badge.setAttribute('aria-hidden', 'true');
                badge.hidden = true;
                launcher.appendChild(badge);
                // A wrapper, not toggleWindow itself: the click event would be read as
                // "force open" and the launcher could then never close the window.
                launcher.addEventListener('click', function () { toggleWindow(); });
                root.appendChild(launcher);
                paintStatus();
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
                if (cfg.avatarUrl) {
                        avatar.innerHTML = '<img src="' + esc(cfg.avatarUrl) + '" alt="" />';
                        avatar.classList.add('has-img');
                } else {
                        avatar.innerHTML = ICON_BOT;
                }
                head.appendChild(avatar);
                var titles = el('div', 'ssc-head__titles');
                titles.appendChild(el('strong', 'ssc-head__title', esc(cfg.assistantName || '')));
                statusLine = el('span', 'ssc-head__status', '');
                titles.appendChild(statusLine);
                paintStatus();
                titles.id = 'ssc-title';
                head.appendChild(titles);

                var actions = el('div', 'ssc-head__actions');
                var i18nHead = cfg.i18n || {};

                // Call the support line (tap-to-call on phones).
                var tel = String(cfg.supportPhone || '').replace(/[^\d+]/g, '');
                if (tel.length >= 5) {
                        var callBtn = el('a', 'ssc-iconbtn ssc-call', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>');
                        callBtn.href = 'tel:' + tel;
                        callBtn.title = (i18nHead.callUs || 'Call us') + ' ' + cfg.supportPhone;
                        callBtn.setAttribute('aria-label', callBtn.title);
                        actions.appendChild(callBtn);
                }

                // Bring the main menu back at any point in the conversation.
                var menuBtn = el('button', 'ssc-iconbtn ssc-menu', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>');
                menuBtn.type = 'button';
                menuBtn.title = i18nHead.mainMenu || 'Main menu';
                menuBtn.setAttribute('aria-label', menuBtn.title);
                menuBtn.addEventListener('click', function () {
                        if (state.loading) { return; }
                        var last = thread.lastElementChild;
                        if (!last || !last.classList.contains('ssc-chips--menu')) {
                                // Move the menu to the end of the conversation, with a caption so it reads as a reply.
                                var stale = thread.querySelectorAll('.ssc-chips--menu');
                                Array.prototype.forEach.call(stale, function (node) { node.parentElement.removeChild(node); });
                                mainMenu(i18nHead.menuPrompt || 'How can I help you?');
                                last = thread.lastElementChild;
                        }
                        // Always give visible feedback, even when the menu was already on screen.
                        scrollDown();
                        if (last) {
                                last.classList.remove('ssc-flash');
                                void last.offsetWidth; // Restart the animation on repeated clicks.
                                last.classList.add('ssc-flash');
                                var first = last.querySelector('.ssc-chip');
                                if (first && !COARSE_POINTER) { first.focus(); }
                        }
                });
                actions.appendChild(menuBtn);

                var resetBtn = el('button', 'ssc-iconbtn', '&#8635;');
                resetBtn.type = 'button';
                resetBtn.title = (cfg.i18n && cfg.i18n.newConversation) || 'New conversation';
                resetBtn.setAttribute('aria-label', resetBtn.title);
                resetBtn.addEventListener('click', function () {
                        if (state.loading) { return; }
                        if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
                        state.items = []; state.product = null; state.hadConversation = false; state.csatDone = false;
                        if (csatTimer) { window.clearTimeout(csatTimer); csatTimer = null; }
                        thread.textContent = '';
                        try { sessionStorage.removeItem(THREAD_KEY); } catch (e) {}
                        resetConv();
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
                        var disclaimer = el('p', 'ssc-disclaimer', esc(cfg.disclaimer));
                        disclaimer.setAttribute('dir', 'auto');
                        win.appendChild(disclaimer);
                }

                // Composer.
                composer = el('form', 'ssc-composer');
                composer.setAttribute('novalidate', 'novalidate');
                // Multi-line composer: Enter sends, Shift+Enter adds a line.
                input = el('textarea', 'ssc-input');
                input.rows = 1;
                input.id = uid();
                input.setAttribute('placeholder', (cfg.i18n && cfg.i18n.placeholder) || '');
                input.setAttribute('aria-label', (cfg.i18n && cfg.i18n.inputLabel) || 'Message');
                input.autocomplete = 'off';
                input.setAttribute('dir', 'auto');
                input.maxLength = 2000;
                input.addEventListener('input', autosize);
                input.addEventListener('keydown', function (e) {
                        // Never submit mid-composition (Persian/CJK input methods use Enter).
                        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && e.keyCode !== 229) {
                                e.preventDefault();
                                if (composer.requestSubmit) { composer.requestSubmit(); } else { composer.dispatchEvent(new Event('submit', { cancelable: true })); }
                        }
                });

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
                                autosize();
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
                var open = ('boolean' === typeof force) ? force : !state.open;
                if (open === state.open) { return; }
                state.open = open;

                launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
                launcher.setAttribute('aria-label', open ? ((cfg.i18n && cfg.i18n.close) || 'Close chat') : ((cfg.i18n && cfg.i18n.open) || 'Open chat'));
                root.classList.toggle('is-open', open);
                win.classList.toggle('is-closed', !open);
                win.classList.toggle('is-open', open);
                if (win.inert !== undefined) { win.inert = !open; }

                if (open) {
                        lastFocus = document.activeElement;
                        refreshStatus();
                        if (!state.started) { startConversation(); }
                        // On touch devices an immediate focus pops the keyboard over the welcome message.
                        // Admin previews must not steal focus (the page would jump to them).
                        if (!COARSE_POINTER && !cfg.preview) { window.setTimeout(function () { input.focus(); }, 60); }
                        proactiveDismiss();
                        clearUnread();
                } else {
                        if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
                        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
                        if (state.hadConversation && !state.loading) { maybeOfferCsat(); }
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
                // Each bubble follows its own text direction (English inside an RTL widget and vice versa).
                bubble.setAttribute('dir', 'auto');
                bubble.innerHTML = md(text);
                if (opts.id) { bubble.id = opts.id; }
                thread.appendChild(bubble);
                state.items.push({ kind: kind, text: text, node: bubble, history: opts.history !== false, transient: !!opts.transient });
                scrollDown();
                saveThread();
                return bubble;
        }

        function removeItem(node) {
                state.items = state.items.filter(function (item) { return item.node !== node; });
                if (node && node.parentElement) { node.parentElement.removeChild(node); }
                saveThread();
        }

        /** Grow the composer with its content, up to five lines. */
        function autosize() {
                if (!input) { return; }
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 132) + 'px';
        }

        function addChips(chips, label) {
                if (!chips || !chips.length) { return; }
                var wrap = el('div', 'ssc-chips');
                if (label) {
                        var caption = el('span', 'ssc-chips__label', esc(label));
                        caption.setAttribute('dir', 'auto');
                        wrap.appendChild(caption);
                }
                chips.forEach(function (chip) {
                        var btn = el('button', 'ssc-chip', esc(chip.label));
                        btn.type = 'button';
                        btn.setAttribute('dir', 'auto');
                        if (chip.title) { btn.title = chip.title; }
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
                var typing = el('div', 'ssc-msg ssc-msg--bot ssc-typing', '<span class="ssc-typing__dot"></span><span class="ssc-typing__dot"></span><span class="ssc-typing__dot"></span><span class="ssc-sr">' + esc((cfg.i18n && cfg.i18n.typing) || 'Typing…') + '</span>');
                typing.setAttribute('role', 'status');
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
                addItem('bot', welcome, { history: false, transient: true });
                restoreThread(saved);
                mainMenu();
        }

        function stripHtml(text) {
                return new DOMParser().parseFromString(String(text || ''), 'text/html').body.textContent || '';
        }

        function mainMenu(caption) {
                var chips = [];
                var i18n = cfg.i18n || {};
                chips.push({ label: i18n.askUs || 'Ask us', title: i18n.askUsDesc || '', onClick: function () { focusProduct(null); if (input && !COARSE_POINTER) { input.focus(); } } });
                if (cfg.products && cfg.products.length) {
                        chips.push({ label: i18n.products || 'Products', title: i18n.productsDesc || '', onClick: chooseProduct });
                }
                if (cfg.features && cfg.features.leads) {
                        chips.push({ label: i18n.requestForm || 'Request', onClick: showLeadForm });
                }
                if (cfg.features && cfg.features.pharma) {
                        chips.push({ label: i18n.reportAdr || 'Report side effect', onClick: showAdrForm });
                }
                addChips(chips, caption);
                var menus = thread.querySelectorAll('.ssc-chips');
                if (menus.length) { menus[menus.length - 1].classList.add('ssc-chips--menu'); }
        }

        function restoreThread(saved) {
                if (!saved || !saved.items || cfg.preview) { return; }
                // Restore only real conversation turns (older versions also stored the
                // welcome message and notices, which then duplicated on every page).
                var restored = 0;
                saved.items.forEach(function (item) {
                        if ((item.k === 'user' || item.k === 'bot') && item.t && item.h) {
                                addItem(item.k, item.t, { history: true });
                                ++restored;
                        }
                });
                state.product = saved.product || null;
                state.hadConversation = restored > 0;
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
                                bubble.setAttribute('dir', 'auto');
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
                        renderSources(node, data.sources);
                        var tools = messageTools(node, reply);
                        if (cfg.features && cfg.features.feedback && data.log_id) {
                                feedbackControls(tools, data.log_id, data.log_token);
                        }
                        scheduleCsat();
                        scrollDown(); // Sources and tools were added below the answer.
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

        /** Knowledge documents the answer was grounded on (links when imported from a URL). */
        function renderSources(node, sources) {
                if (!sources || !sources.length) { return; }
                var i18n = cfg.i18n || {};
                var wrap = el('div', 'ssc-sources');
                wrap.appendChild(el('span', 'ssc-sources__label', esc(i18n.sources || 'Sources:')));
                sources.slice(0, 6).forEach(function (src) {
                        if (!src || !src.title) { return; }
                        var item;
                        if (src.url && /^https?:\/\//.test(src.url)) {
                                item = el('a', 'ssc-sources__item', esc(src.title));
                                item.href = src.url;
                                item.target = '_blank';
                                item.rel = 'noopener noreferrer nofollow';
                        } else {
                                item = el('span', 'ssc-sources__item', esc(src.title));
                        }
                        item.setAttribute('dir', 'auto');
                        wrap.appendChild(item);
                });
                node.appendChild(wrap);
        }

        /** Action row under an answer (copy; feedback is appended when enabled). */
        function messageTools(node, text) {
                var bar = el('div', 'ssc-msgtools');
                if (navigator.clipboard && window.isSecureContext !== false) {
                        var i18n = cfg.i18n || {};
                        var copy = el('button', 'ssc-msgtools__btn', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>');
                        copy.type = 'button';
                        copy.title = i18n.copy || 'Copy answer';
                        copy.setAttribute('aria-label', copy.title);
                        copy.addEventListener('click', function () {
                                navigator.clipboard.writeText(plainText(text)).then(function () {
                                        copy.title = i18n.copied || 'Copied';
                                        copy.classList.add('is-done');
                                        window.setTimeout(function () { copy.classList.remove('is-done'); copy.title = i18n.copy || 'Copy answer'; }, 1500);
                                }).catch(function () { /* clipboard denied */ });
                        });
                        bar.appendChild(copy);
                }
                node.appendChild(bar);
                return bar;
        }

        function feedbackControls(node, logId, logToken) {
                var bar = el('span', 'ssc-feedback');
                ['👍', '👎'].forEach(function (face, idx) {
                        var btn = el('button', 'ssc-feedback__btn', face);
                        btn.type = 'button';
                        btn.title = idx === 0 ? '👍' : '👎';
                        btn.setAttribute('aria-label', idx === 0 ? ((cfg.i18n && cfg.i18n.goodAnswer) || 'Good answer') : ((cfg.i18n && cfg.i18n.poorAnswer) || 'Poor answer'));
                        btn.addEventListener('click', function () {
                                bar.textContent = '✓';
                                bar.setAttribute('role', 'status');
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
                                return { label: item.question.slice(0, 48), title: item.question, onClick: function () { userSend(item.question); } };
                        });
                        addChips(chips, (cfg.i18n && cfg.i18n.suggestions) || '');
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
                        } else if (f.type === 'radio') {
                                // A real radio group (was rendered as a dropdown).
                                var group = el('fieldset', 'ssc-f ssc-f--group ssc-f--radios');
                                group.appendChild(el('legend', 'ssc-f__label', esc(f.label) + (f.required ? ' *' : '')));
                                (f.options || []).forEach(function (opt, idx) {
                                        var lab = el('label', 'ssc-f--check');
                                        var rb = el('input', 'ssc-f__check');
                                        rb.type = 'radio';
                                        rb.name = 'extra[' + f.key + ']';
                                        rb.value = opt;
                                        if (f.required && 0 === idx) { rb.required = true; }
                                        lab.appendChild(rb);
                                        lab.appendChild(el('span', 'ssc-f__label', esc(opt)));
                                        group.appendChild(lab);
                                });
                                form.appendChild(group);
                        } else if (f.type === 'select') {
                                var wrap2 = el('label', 'ssc-f');
                                wrap2.appendChild(el('span', 'ssc-f__label', esc(f.label) + (f.required ? ' *' : '')));
                                var sel = el('select', 'ssc-f__input');
                                sel.name = 'extra[' + f.key + ']';
                                if (f.required) { sel.required = true; }
                                var emptyChoice0 = el('option', '', '—'); emptyChoice0.value = ''; sel.appendChild(emptyChoice0);
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
                                if (f.required) { cb.required = true; }
                                wrap3.appendChild(cb);
                                wrap3.appendChild(el('span', 'ssc-f__label', esc(f.label) + (f.required ? ' *' : '')));
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

        var csatTimer = null;

        /** Offer the survey after a quiet minute following an answer. */
        function scheduleCsat() {
                if (!cfg.features || !cfg.features.csat || state.csatDone) { return; }
                if (csatTimer) { window.clearTimeout(csatTimer); }
                csatTimer = window.setTimeout(function () {
                        csatTimer = null;
                        if (state.open && !state.loading) { maybeOfferCsat(); }
                }, 60000);
        }

        /*
         * Shown after a quiet period or when the visitor closes the window after a
         * real conversation (it is then waiting in the thread when they return).
         * It previously required the window to be OPEN while only ever being
         * triggered by closing it, so the survey never appeared.
         */
        function maybeOfferCsat() {
                if (!cfg.features || !cfg.features.csat || state.csatDone || !state.hadConversation || !thread) { return; }
                if (csatTimer) { window.clearTimeout(csatTimer); csatTimer = null; }
                state.csatDone = true;
                var i18n = cfg.i18n || {};
                var card = el('div', 'ssc-cardform ssc-cardform--csat');
                card.appendChild(el('h3', 'ssc-cardform__title', esc(i18n.csatTitle || 'How was it?')));
                var group = el('div', 'ssc-csat');
                group.setAttribute('role', 'group');
                group.setAttribute('aria-label', i18n.csatTitle || 'Rating');
                for (var i = 1; i <= 5; ++i) {
                        (function (score) {
                                var star = el('button', 'ssc-csat__star', '★');
                                star.type = 'button';
                                star.setAttribute('aria-label', score + ' / 5');
                                star.addEventListener('click', function () {
                                        group.textContent = i18n.csatThanks || 'Thanks!';
                                        group.setAttribute('role', 'status');
                                        if (skip.parentElement) { skip.parentElement.removeChild(skip); }
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

                var placeholder = input.getAttribute('placeholder') || '';
                recognition.onstart = function () {
                        if (micBtn) { micBtn.classList.add('is-live'); micBtn.setAttribute('aria-pressed', 'true'); }
                        input.setAttribute('placeholder', (cfg.i18n && cfg.i18n.micListening) || 'Listening…');
                };
                recognition.onresult = function (event) {
                        var text = '';
                        for (var i = event.resultIndex; i < event.results.length; ++i) {
                                text += event.results[i][0].transcript;
                        }
                        input.value = (base ? base + ' ' : '') + text;
                        autosize();
                };
                recognition.onerror = function () {
                        recognition = null;
                        input.setAttribute('placeholder', placeholder);
                        if (micBtn) { micBtn.classList.remove('is-live'); micBtn.setAttribute('aria-pressed', 'false'); micBtn.classList.add('is-error'); }
                };
                recognition.onend = function () {
                        recognition = null;
                        input.setAttribute('placeholder', placeholder);
                        autosize();
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
                var utter = new window.SpeechSynthesisUtterance(plainText(stripHtml(text)));
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
                if (btn) {
                        var i18n = cfg.i18n || {};
                        btn.classList.toggle('is-speaking', on);
                        btn.title = on ? (i18n.speakStop || 'Stop audio') : (i18n.speak || 'Listen');
                        btn.setAttribute('aria-label', btn.title);
                }
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

                if (!String(cfg.proactiveText || '').trim()) { return; } // "-" rule or empty text.

                var delay = Math.max(2, (cfg.proactiveDelay || 12)) * 1000;
                var trigger = cfg.proactiveTrigger || 'delay';

                if ('scroll' === trigger) {
                        var depth = Math.min(100, Math.max(10, cfg.proactiveScroll || 50)) / 100;
                        var onScroll = function () {
                                var doc = document.documentElement;
                                var max = Math.max(1, doc.scrollHeight - window.innerHeight);
                                if ((window.scrollY || doc.scrollTop) / max >= depth) {
                                        window.removeEventListener('scroll', onScroll);
                                        showProactive();
                                }
                        };
                        window.addEventListener('scroll', onScroll, { passive: true });
                        return;
                }

                // Exit intent needs a mouse; phones fall back to the delay.
                if ('exit' === trigger && !COARSE_POINTER) {
                        var onLeave = function (e) {
                                if (!e.relatedTarget && e.clientY <= 0) {
                                        document.removeEventListener('mouseout', onLeave);
                                        showProactive();
                                }
                        };
                        window.setTimeout(function () { document.addEventListener('mouseout', onLeave); }, 3000);
                        return;
                }

                window.setTimeout(showProactive, delay);
        }

        function showProactive() {
                if (state.open || proactiveBubble) { return; }
                if (!String(cfg.proactiveText || '').trim()) { return; }
                proactiveBubble = el('div', 'ssc-proactive');
                proactiveBubble.setAttribute('role', 'status');
                var invite = el('button', 'ssc-proactive__text', esc(cfg.proactiveText));
                invite.type = 'button';
                invite.setAttribute('dir', 'auto');
                invite.addEventListener('click', function () {
                        proactiveDismiss();
                        toggleWindow(true);
                });
                var dismiss = el('button', 'ssc-proactive__close', ICON_CLOSE);
                dismiss.type = 'button';
                dismiss.setAttribute('aria-label', (cfg.i18n && cfg.i18n.close) || 'Close');
                dismiss.addEventListener('click', proactiveDismiss);
                proactiveBubble.appendChild(invite);
                proactiveBubble.appendChild(dismiss);
                root.appendChild(proactiveBubble);
                window.setTimeout(proactiveDismiss, 12000);
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
                        // Never steal a keystroke from an editor (Option+C types "ç" on macOS).
                        if ('INPUT' !== tag && 'TEXTAREA' !== tag && 'SELECT' !== tag && !(e.target && e.target.isContentEditable)) {
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

        // A click anywhere outside the widget closes it (the floating widget only:
        // admin previews stay open). Pointerdown runs before chips remove themselves,
        // so clicks inside the conversation are never mistaken for outside ones.
        document.addEventListener('pointerdown', function (e) {
                if (!state.open || previewMount || !root || root.contains(e.target)) { return; }
                if (e.target && e.target.isConnected === false) { return; }
                toggleWindow(false);
        }, true);

        /* ------------------------------------------------------------------ *
         * Boot
         * ------------------------------------------------------------------ */

        function boot() {
                root.setAttribute('dir', cfg.direction || 'rtl');
                // Position lives on the root so the window and invitation follow the launcher.
                if ('left' === cfg.position) { root.classList.add('ssc-pos-left'); }
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
                refreshStatus();

                window.addEventListener('beforeunload', function () {
                        if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
                });
        }

        boot();

        /* ------------------------------------------------------------------ *
         * Public API (preview mount for the wizard)
         * ------------------------------------------------------------------ */

        var previewMount = null;

        /**
         * Live restyling for admin previews: merge settings into the config and
         * repaint only what they affect (no rebuild, the conversation stays).
         */
        function applyConfig(patch) {
                Object.keys(patch || {}).forEach(function (k) { cfg[k] = patch[k]; });
                var host = previewMount || root;
                var vars = cssVars();
                Object.keys(vars).forEach(function (k) { host.style.setProperty(k, vars[k]); });
                ['--ssc-user-bubble', '--ssc-bot-bubble'].forEach(function (k) {
                        if ((k === '--ssc-user-bubble' && !cfg.userBubble) || (k === '--ssc-bot-bubble' && !cfg.botBubble)) { host.style.removeProperty(k); }
                });
                host.setAttribute('dir', cfg.direction || 'rtl');
                host.classList.toggle('ssc-pos-left', 'left' === cfg.position);
                var mode = cfg.themeMode || 'light';
                if ('auto' === mode && window.matchMedia) { mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
                host.setAttribute('data-theme', mode);
                if (!win) { return; }
                var title = win.querySelector('.ssc-head__title');
                if (title) { title.textContent = cfg.assistantName || ''; }
                paintStatus();
                var avatar = win.querySelector('.ssc-head__avatar');
                if (avatar) {
                        if (cfg.avatarUrl) { avatar.innerHTML = '<img src="' + esc(cfg.avatarUrl) + '" alt="" />'; avatar.classList.add('has-img'); } else { avatar.innerHTML = ICON_BOT; avatar.classList.remove('has-img'); }
                }
                var disclaimer = win.querySelector('.ssc-disclaimer');
                if (cfg.disclaimer) {
                        if (!disclaimer) {
                                disclaimer = el('p', 'ssc-disclaimer', '');
                                disclaimer.setAttribute('dir', 'auto');
                                win.insertBefore(disclaimer, composer);
                        }
                        disclaimer.textContent = cfg.disclaimer;
                } else if (disclaimer) {
                        disclaimer.parentElement.removeChild(disclaimer);
                }
                var welcome = state.items.filter(function (item) { return item.transient; })[0];
                if (welcome) {
                        welcome.text = (cfg.welcomeTitle ? cfg.welcomeTitle + '\n' : '') + stripHtml(cfg.welcomeText || '');
                        welcome.node.innerHTML = md(welcome.text);
                }
        }

        window.SSCChatbot = {
                toggle: toggleWindow,
                applyConfig: applyConfig,
                mountPreview: function (mountNode) {
                        if (!mountNode || !win) { return; }
                        previewMount = mountNode;
                        mountNode.classList.add('ssc-root', 'ssc-preview-mount');
                        mountNode.setAttribute('dir', cfg.direction || 'rtl');
                        var vars = cssVars();
                        Object.keys(vars).forEach(function (k) { mountNode.style.setProperty(k, vars[k]); });
                        applyTheme();
                        mountNode.appendChild(launcher);
                        mountNode.appendChild(win);
                        launcher.hidden = true;
                        win.classList.add('ssc-window--inline');
                        if ('left' === cfg.position) { mountNode.classList.add('ssc-pos-left'); }
                        toggleWindow(true);
                }
        };
})();
