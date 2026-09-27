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
        /*
         * The assistant's character: a chat bubble with a face, painted in the
         * widget's own colour. Inline SVG (no image request, follows the theme);
         * the loops are pure CSS so they never run through JavaScript, and only
         * the launcher's copy follows the pointer. The face variant (no tail or
         * antenna, bigger eyes) is for small spots like the chat header.
         */
        function mascotSvg(face) {
                var eyes = face
                        ? '<g class="ssc-m-eyes"><ellipse cx="36" cy="46" rx="12" ry="13" fill="#fff"/><ellipse cx="64" cy="46" rx="12" ry="13" fill="#fff"/>'
                        + '<g class="ssc-m-pupils"><circle cx="36" cy="47.5" r="6.8" fill="#1f2433"/><circle cx="64" cy="47.5" r="6.8" fill="#1f2433"/><circle cx="33.6" cy="44.6" r="2.2" fill="#fff"/><circle cx="61.6" cy="44.6" r="2.2" fill="#fff"/></g></g>'
                        : '<g class="ssc-m-eyes"><ellipse cx="37" cy="44" rx="10.5" ry="11.5" fill="#fff"/><ellipse cx="63" cy="44" rx="10.5" ry="11.5" fill="#fff"/>'
                        + '<ellipse cx="37" cy="44" rx="10.5" ry="11.5" fill="none" stroke="#000" stroke-opacity=".12" stroke-width=".8"/><ellipse cx="63" cy="44" rx="10.5" ry="11.5" fill="none" stroke="#000" stroke-opacity=".12" stroke-width=".8"/>'
                        + '<g class="ssc-m-pupils"><circle cx="37" cy="45.5" r="5.8" fill="#1f2433"/><circle cx="63" cy="45.5" r="5.8" fill="#1f2433"/><circle cx="34.9" cy="42.9" r="1.9" fill="#fff"/><circle cx="60.9" cy="42.9" r="1.9" fill="#fff"/></g></g>';
                return '<svg class="ssc-mascot' + (face ? ' ssc-mascot--face' : '') + '" viewBox="0 0 100 100" aria-hidden="true" focusable="false">'
                        + '<g class="ssc-m-body">'
                        + (face ? '' : '<g class="ssc-m-antenna"><path d="M50 13 V5" stroke="var(--ssc-mascot)" stroke-width="3" stroke-linecap="round"/><circle class="ssc-m-tip" cx="50" cy="4.5" r="4" fill="var(--ssc-mascot)"/><circle cx="48.8" cy="3.3" r="1.3" fill="#fff" fill-opacity=".7"/></g>'
                        + '<path d="M27 68 Q24 84 13 91 Q35 90 45 77 Z" fill="var(--ssc-mascot)"/>')
                        + '<ellipse cx="50" cy="' + (face ? 50 : 46) + '" rx="' + (face ? 44 : 39) + '" ry="' + (face ? 42 : 35) + '" fill="var(--ssc-mascot)"/>'
                        + '<ellipse cx="36" cy="' + (face ? 26 : 25) + '" rx="' + (face ? 20 : 17) + '" ry="10" fill="#fff" fill-opacity=".2"/>'
                        + '<g class="ssc-m-face">' + eyes
                        + '<ellipse cx="' + (face ? 22 : 26) + '" cy="' + (face ? 63 : 58) + '" rx="5.5" ry="3.2" fill="#ff7a9a" fill-opacity=".45"/><ellipse cx="' + (face ? 78 : 74) + '" cy="' + (face ? 63 : 58) + '" rx="5.5" ry="3.2" fill="#ff7a9a" fill-opacity=".45"/>'
                        + '<path class="ssc-m-smile" d="' + (face ? 'M41 67 Q50 75 59 67' : 'M42 61 Q50 68 58 61') + '" fill="none" stroke="var(--ssc-mascot-ink)" stroke-width="' + (face ? 3.2 : 2.6) + '" stroke-linecap="round"/>'
                        + (face ? '' : '<g class="ssc-m-grin"><path d="M41 59 Q50 73 59 59 Z" fill="var(--ssc-mascot-ink)"/><path d="M45 65 Q50 70 55 65 Z" fill="#ff7a8a"/></g>')
                        + '</g></g></svg>';
        }

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

        /*
         * Memory storage. The conversation survives closing the tab (local
         * storage, for the number of days the server remembers it), so a
         * visitor who comes back continues where they left off. Health
         * conversations (pharma) are never persisted in the browser.
         */
        var MEM = cfg.memory || {};
        var MEM_TTL = Math.max(1, Number(MEM.days) || 7) * 24 * 60 * 60 * 1000;

        function memStore() {
                try { return MEM.persist === false ? window.sessionStorage : window.localStorage; } catch (e) { return null; }
        }

        function memGet(key) {
                var store = memStore();
                try { return store ? store.getItem(key) : null; } catch (e) { return null; }
        }

        function memSet(key, value) {
                var store = memStore();
                try { if (store) { store.setItem(key, value); } } catch (e) { /* storage full or blocked */ }
        }

        function memRemove(key) {
                var store = memStore();
                try { if (store) { store.removeItem(key); } } catch (e) { /* storage blocked */ }
                // Earlier versions kept both in the tab's session storage.
                try { window.sessionStorage.removeItem(key); } catch (e) { /* storage blocked */ }
        }

        /** Conversation transcript persisted across pages and visits (60 items). */
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
                        memSet(THREAD_KEY, JSON.stringify(slim));
                        if (convId) { memSet(CONV_KEY, JSON.stringify({ id: convId, at: Date.now() })); }
                } catch (e) { /* storage unavailable */ }
        }

        function loadThread() {
                if (!state.persist) { return null; }
                try {
                        var raw = memGet(THREAD_KEY);
                        if (!raw) { return null; }
                        var data = JSON.parse(raw);
                        if (!data || !Array.isArray(data.items) || !data.at || Date.now() - data.at > MEM_TTL) {
                                memRemove(THREAD_KEY);
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
                var params = {
                        message: message,
                        product: state.product || 'general',
                        conv: getConv()
                };
                if (cfg.features && cfg.features.live) { params.page = window.location.href; }
                return transport(chatRoute(), params);
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
                if (cfg.features && cfg.features.live) { body.append('page', window.location.href); }
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
                        var stored = null;
                        try { stored = JSON.parse(memGet(CONV_KEY) || 'null'); } catch (e) { stored = null; }
                        if (stored && stored.id && stored.at && Date.now() - stored.at <= MEM_TTL) { convId = stored.id; }
                }
                if (!convId || !/^[a-f0-9]{32}$/.test(convId)) {
                        convId = newConvId();
                }
                if (state.persist) { memSet(CONV_KEY, JSON.stringify({ id: convId, at: Date.now() })); }
                return convId;
        }

        function resetConv() {
                convId = null;
                memRemove(CONV_KEY);
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
                // Text on a coloured surface is picked by contrast, so a light brand
                // colour (yellow, white…) never gets white text on it.
                vars['--ssc-primary-contrast'] = inkFor(cfg.primaryColor || '#16203a');
                // The character wears the brand colour; its mouth takes the readable ink.
                vars['--ssc-mascot'] = cfg.primaryColor || '#16203a';
                vars['--ssc-mascot-ink'] = inkFor(cfg.primaryColor || '#16203a');
                vars['--ssc-user-bubble-custom'] = cfg.userBubble || '';
                vars['--ssc-user-ink-custom'] = cfg.userBubble ? inkFor(cfg.userBubble) : '';
                vars['--ssc-bot-bubble-custom'] = cfg.botBubble || '';
                vars['--ssc-bot-ink-custom'] = cfg.botBubble ? inkFor(cfg.botBubble) : '';
                return vars;
        }

        /** Relative luminance (0 dark … 1 light) of a #rgb / #rrggbb colour, or -1. */
        function luminance(color) {
                var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(String(color || '').trim());
                if (!m) { return -1; }
                var hex = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1];
                var ch = [0, 2, 4].map(function (i) {
                        var c = parseInt(hex.substr(i, 2), 16) / 255;
                        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
                });
                return 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2];
        }

        /** Dark or white text, whichever reads better on the given background. */
        function inkFor(color) {
                var l = luminance(color);
                if (l < 0) { return '#fff'; }
                // Contrast against white vs. against #101322 (the light-theme ink).
                return (1.05 / (l + 0.05)) >= ((l + 0.05) / 0.0567) ? '#fff' : '#101322';
        }

        /**
         * Apply the colour/size variables to a host. Empty custom colours are
         * removed so the theme defaults apply, and a light custom bot bubble is
         * flagged so the dark theme can swap it for its own card colour.
         */
        function paintVars(host) {
                var vars = cssVars();
                Object.keys(vars).forEach(function (k) {
                        if ('' === vars[k]) { host.style.removeProperty(k); } else { host.style.setProperty(k, vars[k]); }
                });
                host.style.removeProperty('--ssc-user-bubble');
                host.style.removeProperty('--ssc-bot-bubble');
                if (cfg.botBubble && luminance(cfg.botBubble) > 0.5) {
                        host.setAttribute('data-bot-bubble', 'light');
                } else {
                        host.removeAttribute('data-bot-bubble');
                }
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
                } else if ('mascot' === cfg.launcherStyle) {
                        launcher.classList.add('ssc-launcher--mascot');
                        launcher.appendChild(el('span', 'ssc-launcher__mascot', mascotSvg(false)));
                        followPointer(launcher.querySelector('.ssc-mascot'));
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

        /**
         * The launcher's eyes follow the pointer. Written straight onto the SVG
         * inside requestAnimationFrame: one attribute per frame, no re-render,
         * and nothing at all for visitors who asked for less motion.
         */
        function followPointer(svg) {
                if (!svg) { return; }
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
                var pupils = svg.querySelector('.ssc-m-pupils');
                var face = svg.querySelector('.ssc-m-face');
                var frame = 0, point = null;
                function apply() {
                        frame = 0;
                        if (!point || state.open || root.classList.contains('is-thinking')) { return; }
                        var box = svg.getBoundingClientRect();
                        if (!box.width) { return; }
                        var dx = point.x - (box.left + box.width / 2);
                        var dy = point.y - (box.top + box.height * 0.44);
                        var dist = Math.sqrt(dx * dx + dy * dy) || 1;
                        var pull = Math.min(1, dist / 260);
                        var x = dx / dist * pull, y = dy / dist * pull;
                        pupils.setAttribute('transform', 'translate(' + (x * 3.4).toFixed(2) + ' ' + (y * 2.8).toFixed(2) + ')');
                        face.setAttribute('transform', 'translate(' + (x * 1.4).toFixed(2) + ' ' + (y * 1).toFixed(2) + ')');
                }
                window.addEventListener('pointermove', function (e) {
                        point = { x: e.clientX, y: e.clientY };
                        if (!frame) { frame = window.requestAnimationFrame(apply); }
                }, { passive: true });
        }

        /** Header avatar: the character's face unless an avatar image is set. */
        function avatarMarkup() {
                if (cfg.avatarUrl) { return '<img src="' + esc(cfg.avatarUrl) + '" alt="" />'; }
                return 'mascot' === cfg.launcherStyle ? mascotSvg(true) : ICON_BOT;
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
                avatar.classList.toggle('has-mascot', !cfg.avatarUrl && 'mascot' === cfg.launcherStyle);
                if (cfg.avatarUrl) {
                        avatar.innerHTML = avatarMarkup();
                        avatar.classList.add('has-img');
                } else {
                        avatar.innerHTML = avatarMarkup();
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
                        clearConversation();
                        startConversation(); input.focus();
                });

                // Signed-in users: their previous conversations, on any device.
                if (MEM.threads && !cfg.preview && cfg.restUrl) {
                        var historyBtn = el('button', 'ssc-iconbtn ssc-history-btn');
                        historyBtn.type = 'button';
                        historyBtn.title = (cfg.i18n && cfg.i18n.history) || 'Previous conversations';
                        historyBtn.setAttribute('aria-label', historyBtn.title);
                        historyBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>';
                        historyBtn.addEventListener('click', function () { if (!state.loading) { showHistory(); } });
                        actions.appendChild(historyBtn);
                }
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

                // Conversations are stored for quality review: say so, quietly.
                if (MEM.notice && !cfg.preview) {
                        var saved = el('p', 'ssc-disclaimer ssc-saved-notice', esc((cfg.i18n && cfg.i18n.savedNotice) || ''));
                        saved.setAttribute('dir', 'auto');
                        win.appendChild(saved);
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
                        if (cfg.features && cfg.features.live && !cfg.preview && (state.hadConversation || live.status !== 'bot')) { pollLive(); }
                } else {
                        if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
                        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
                        if (state.hadConversation && !state.loading) { maybeOfferCsat(); }
                        if (cfg.features && cfg.features.live) { scheduleLivePoll(); }
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
                // scrollHeight excludes the border; without it the box is 2px short
                // and shows a scrollbar on a single line.
                var border = input.offsetHeight - input.clientHeight;
                var wanted = input.scrollHeight + border;
                input.style.height = Math.min(wanted, 132) + 'px';
                input.style.overflowY = wanted > 132 ? 'auto' : 'hidden';
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

        /** Forget the current conversation in this browser (a new one starts). */
        function clearConversation() {
                if (window.speechSynthesis) { window.speechSynthesis.cancel(); }
                state.items = []; state.product = null; state.hadConversation = false; state.csatDone = false;
                if (csatTimer) { window.clearTimeout(csatTimer); csatTimer = null; }
                if (live.status === 'waiting' || live.status === 'human') {
                        // Leaving a handled chat: tell the operator's side it is over.
                        transport('live/leave', { conv: getConv() }).catch(function () {});
                }
                if (live.timer) { window.clearTimeout(live.timer); }
                live.status = 'bot'; live.lastId = 0; live.operator = ''; live.offered = false;
                if (live.bar) { live.bar.hidden = true; }
                thread.textContent = '';
                memRemove(THREAD_KEY);
                resetConv();
        }

        /* ------------------------------------------------------------------ *
         * Previous conversations (signed-in users)
         * ------------------------------------------------------------------ */

        function restCall(method, route) {
                var headers = {};
                if (cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
                return request(cfg.restUrl + route, { method: method, headers: headers, credentials: 'same-origin' }, function (res) {
                        return res.json().then(function (data) {
                                if (!res.ok || !data || !data.ok) { throw new Error((data && data.message) || 'Request failed'); }
                                return data;
                        });
                });
        }

        var historyPanel = null;

        function closeHistory() {
                if (historyPanel) { historyPanel.remove(); historyPanel = null; }
        }

        function showHistory() {
                if (historyPanel) { closeHistory(); return; }
                var i18n = cfg.i18n || {};
                historyPanel = el('div', 'ssc-history');
                historyPanel.setAttribute('role', 'dialog');
                historyPanel.setAttribute('aria-label', i18n.history || 'Previous conversations');
                var head = el('div', 'ssc-history__head');
                var title = el('strong', '', esc(i18n.history || 'Previous conversations'));
                var back = el('button', 'ssc-history__back', esc(i18n.historyBack || 'Back to chat'));
                back.type = 'button';
                back.addEventListener('click', closeHistory);
                head.appendChild(title);
                head.appendChild(back);
                historyPanel.appendChild(head);
                var list = el('div', 'ssc-history__list');
                list.appendChild(el('div', 'ssc-history__empty', '<span class="ssc-typing"><span></span><span></span><span></span></span>'));
                historyPanel.appendChild(list);
                thread.parentNode.insertBefore(historyPanel, thread);
                back.focus();
                restCall('GET', 'conversations').then(function (data) {
                        list.textContent = '';
                        if (!data.threads || !data.threads.length) {
                                list.appendChild(el('p', 'ssc-history__empty', esc(i18n.historyEmpty || 'No previous conversations yet.')));
                                return;
                        }
                        data.threads.forEach(function (item) {
                                var row = el('div', 'ssc-history__row' + (item.id === convId ? ' is-current' : ''));
                                var open = el('button', 'ssc-history__open');
                                open.type = 'button';
                                open.setAttribute('dir', 'auto');
                                open.innerHTML = '<span class="ssc-history__title">' + esc(item.title || '…') + '</span><span class="ssc-history__meta">' + esc(item.updated || '') + '</span>';
                                open.addEventListener('click', function () { openThread(item.id); });
                                var del = el('button', 'ssc-history__del', '&times;');
                                del.type = 'button';
                                del.title = i18n.historyDelete || 'Delete';
                                del.setAttribute('aria-label', del.title);
                                del.addEventListener('click', function () {
                                        restCall('DELETE', 'conversations/' + item.id).then(function () {
                                                row.remove();
                                                if (item.id === convId) { clearConversation(); startConversation(); }
                                        }).catch(function () {});
                                });
                                row.appendChild(open);
                                row.appendChild(del);
                                list.appendChild(row);
                        });
                }).catch(function () {
                        list.textContent = '';
                        list.appendChild(el('p', 'ssc-history__empty', esc((cfg.i18n && cfg.i18n.connectionError) || 'Connection error')));
                });
        }

        function openThread(id) {
                restCall('GET', 'conversations/' + id).then(function (data) {
                        closeHistory();
                        clearConversation();
                        convId = data.id;
                        memSet(CONV_KEY, JSON.stringify({ id: convId, at: Date.now() }));
                        var items = (data.messages || []).map(function (m) {
                                return { k: m.role === 'assistant' ? 'bot' : 'user', t: m.content, h: true };
                        });
                        memSet(THREAD_KEY, JSON.stringify({ at: Date.now(), product: null, items: items }));
                        startConversation();
                }).catch(function () {
                        closeHistory();
                        addItem('bot', (cfg.i18n && cfg.i18n.historyGone) || 'This conversation is no longer available.', { history: false, transient: true });
                });
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
                if (cfg.woo && cfg.woo.tracking) {
                        chips.push({ label: wooText('trackOrder', 'Track my order'), onClick: showOrderForm });
                }
                if (cfg.woo && cfg.woo.remind && /(?:^|;\s*)woocommerce_items_in_cart=1/.test(document.cookie)) {
                        chips.push({ label: wooText('remind', 'Remind me about my cart by SMS'), onClick: showRemindForm });
                }
                if (cfg.features && cfg.features.live) {
                        chips.push({ label: liveText('talkToPerson', 'Talk to a person'), onClick: requestHuman });
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

        /** The character looks up while the answer is being written. */
        function thinking(on) {
                if (root) { root.classList.toggle('is-thinking', !!on); }
        }

        function ask(text) {
                state.loading = true;
                thinking(true);
                var typing = showTyping();
                var streamBuf = '';
                var bubble = null;
                var streaming = false;

                function ensureBubble() {
                        if (!bubble) {
                                thinking(false);
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
                        thinking(false);

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

                        if (data.source === 'live' || (data.flags && data.flags.live)) {
                                // A person is (about to be) in charge: the message went to them.
                                if (bubble && bubble.parentElement) { bubble.parentElement.removeChild(bubble); }
                                liveSetStatus((data.flags && data.flags.live) || live.status);
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
                        renderCards(node, data.cards);
                        handleActions(data.actions);
                        var tools = messageTools(node, reply);
                        if (cfg.features && cfg.features.feedback && data.log_id) {
                                feedbackControls(tools, data.log_id, data.log_token);
                        }
                        scheduleCsat();
                        state.hadConversation = true;
                        scheduleLivePoll();
                        scrollDown(); // Sources and tools were added below the answer.
                        if (!state.open) { bumpUnread(); maybeBeep(); }
                }).catch(function () {
                        if (typing && typing.parentElement) { typing.parentElement.removeChild(typing); }
                        state.loading = false;
                        thinking(false);
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
                if (data.handoff && cfg.features && (cfg.features.handoff || cfg.features.live)) {
                        addItem('bot', cfg.handoffText || '', { history: false });
                        addChips([{ label: cfg.features.live ? liveText('talkToPerson', 'Talk to a person') : (i18n.handoffBtn || 'Talk to a human'), onClick: cfg.features.live ? requestHuman : showLeadForm }]);
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
         * WooCommerce (module): product cards, order tracking, offers
         * ------------------------------------------------------------------ */

        function wooText(key, fallback) {
                return (cfg.woo && cfg.woo.i18n && cfg.woo.i18n[key]) || fallback;
        }

        /** WooCommerce's own AJAX endpoints (add_to_cart, apply_coupon): session-aware, theme-compatible. */
        function wooAjax(endpoint, params) {
                var body = new URLSearchParams();
                Object.keys(params).forEach(function (k) { body.append(k, params[k]); });
                return fetch(cfg.woo.ajaxUrl.replace('%%endpoint%%', endpoint), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                        body: body.toString()
                });
        }

        /** Tell the theme (mini-cart, counters) that the cart changed, the way WooCommerce does. */
        function wooCartChanged(fragments, hash) {
                if (window.jQuery) {
                        try {
                                window.jQuery(document.body).trigger('added_to_cart', [fragments || {}, hash || '']);
                                window.jQuery(document.body).trigger('wc_fragment_refresh');
                        } catch (e) { /* theme without handlers */ }
                }
        }

        function renderCards(node, cards) {
                if (!cards || !cards.length || !cfg.woo) { return; }
                var list = el('div', 'ssc-pcards');
                list.setAttribute('role', 'list');
                cards.forEach(function (c) {
                        var card = el('div', 'ssc-pcard' + (c.inStock ? '' : ' is-out'));
                        card.setAttribute('role', 'listitem');
                        var link = el('a', 'ssc-pcard__media');
                        link.href = c.url;
                        link.setAttribute('aria-label', c.name);
                        if (c.image) {
                                var img = el('img', '');
                                img.src = c.image;
                                img.alt = '';
                                img.loading = 'lazy';
                                link.appendChild(img);
                        }
                        card.appendChild(link);
                        var name = el('a', 'ssc-pcard__name', esc(c.name));
                        name.href = c.url;
                        name.setAttribute('dir', 'auto');
                        card.appendChild(name);
                        var price = el('div', 'ssc-pcard__price');
                        if (c.regular) { price.appendChild(el('del', '', esc(c.regular))); }
                        price.appendChild(el('span', '', esc(c.price)));
                        price.setAttribute('dir', 'auto');
                        card.appendChild(price);
                        card.appendChild(el('span', 'ssc-pcard__stock', esc(c.stock)));
                        if (c.addable) {
                                var btn = el('button', 'ssc-pcard__btn', esc(wooText('addToCart', 'Add to cart')));
                                btn.type = 'button';
                                btn.addEventListener('click', function () { addToCart(c, btn, card); });
                                card.appendChild(btn);
                        } else {
                                var view = el('a', 'ssc-pcard__btn ssc-pcard__btn--ghost', esc(c.inStock ? wooText('options', 'Choose options') : wooText('view', 'View')));
                                view.href = c.url;
                                card.appendChild(view);
                        }
                        list.appendChild(card);
                });
                node.appendChild(list);
                scrollDown();
        }

        function addToCart(c, btn, card) {
                if (btn.disabled) { return; }
                btn.disabled = true;
                wooAjax('add_to_cart', { product_id: c.id, quantity: 1 }).then(function (res) {
                        return res.json();
                }).then(function (data) {
                        if (!data || data.error) {
                                // WooCommerce asks for the product page (options, stock rules…).
                                if (data && data.product_url) { window.location.href = data.product_url; return; }
                                throw new Error('add_to_cart');
                        }
                        btn.textContent = wooText('added', 'Added ✓');
                        btn.classList.add('is-done');
                        var cart = el('a', 'ssc-pcard__cart', esc(wooText('viewCart', 'View cart')));
                        cart.href = cfg.woo.cartUrl;
                        card.appendChild(cart);
                        wooCartChanged(data.fragments, data.cart_hash);
                }).catch(function () {
                        btn.disabled = false;
                        btn.textContent = wooText('error', 'That did not work. Please try again.');
                });
        }

        function handleActions(actions) {
                if (!actions || !actions.length) { return; }
                if (actions.indexOf('track_order') !== -1 && cfg.woo && cfg.woo.tracking) {
                        addChips([{ label: wooText('trackOrder', 'Track my order'), onClick: showOrderForm }]);
                }
        }

        /** Small form card (order tracking, cart reminder). */
        function wooForm(title, fields, submitLabel, onSubmit) {
                var card = el('div', 'ssc-cardform ssc-cardform--woo');
                card.appendChild(el('h3', 'ssc-cardform__title', esc(title)));
                var form = el('form', 'ssc-cardform__form');
                form.setAttribute('novalidate', 'novalidate');
                fields.forEach(function (f) {
                        if (f.type === 'checkbox') {
                                var cw = el('label', 'ssc-f ssc-f--check');
                                var cb = el('input', 'ssc-f__check');
                                cb.type = 'checkbox';
                                cb.name = f.name;
                                cb.value = '1';
                                cw.appendChild(cb);
                                cw.appendChild(el('span', 'ssc-f__label', esc(f.label)));
                                form.appendChild(cw);
                                return;
                        }
                        var wrap = el('label', 'ssc-f');
                        wrap.appendChild(el('span', 'ssc-f__label', esc(f.label)));
                        var input = el('input', 'ssc-f__input');
                        input.type = f.type || 'text';
                        input.name = f.name;
                        input.required = true;
                        if (f.ltr) { input.dir = 'ltr'; }
                        if (f.inputmode) { input.setAttribute('inputmode', f.inputmode); }
                        wrap.appendChild(input);
                        form.appendChild(wrap);
                });
                var err = el('p', 'ssc-cardform__error', '');
                err.setAttribute('role', 'alert');
                form.appendChild(err);
                var submit = el('button', 'ssc-btn2', esc(submitLabel));
                submit.type = 'submit';
                form.appendChild(submit);
                form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        err.textContent = '';
                        submit.disabled = true;
                        var params = {};
                        new FormData(form).forEach(function (v, k) { params[k] = v; });
                        onSubmit(params, card, err).then(function () { submit.disabled = false; });
                });
                card.appendChild(form);
                thread.appendChild(card);
                scrollDown();
                var first = form.querySelector('input');
                if (first && !COARSE_POINTER) { first.focus(); }
                return card;
        }

        function showOrderForm() {
                wooForm(wooText('trackOrder', 'Track my order'), [
                        { name: 'order', label: wooText('orderNumber', 'Order number'), ltr: true, inputmode: 'numeric' },
                        { name: 'contact', label: wooText('contact', 'Phone or email used for the order'), ltr: true }
                ], wooText('check', 'Check'), function (params, card, err) {
                        return transport('woo/order', params).then(function (res) {
                                if (!res || !res.success) {
                                        err.textContent = (res && res.data && res.data.message) || wooText('error', 'That did not work. Please try again.');
                                        return;
                                }
                                card.parentElement.replaceChild(orderCard(res.data), card);
                                scrollDown();
                        }).catch(function () { err.textContent = wooText('error', 'That did not work. Please try again.'); });
                });
        }

        function orderCard(o) {
                var card = el('div', 'ssc-msg ssc-msg--bot ssc-order');
                card.setAttribute('dir', 'auto');
                card.appendChild(el('strong', 'ssc-order__title', esc('#' + o.number + ' · ' + o.status)));
                var dl = el('dl', 'ssc-order__list');
                var row = function (label, value) {
                        if (!value) { return; }
                        dl.appendChild(el('dt', '', esc(label)));
                        dl.appendChild(el('dd', '', esc(value)));
                };
                row(wooText('date', 'Date'), o.date);
                row(wooText('items', 'Items'), (o.items || []).map(function (i) { return i.name + ' × ' + i.qty; }).join('، '));
                row(wooText('total', 'Total'), o.total);
                row(wooText('note', 'Latest update'), o.note);
                card.appendChild(dl);
                if (o.tracking) {
                        var track = el('div', 'ssc-order__track');
                        track.appendChild(el('span', '', esc(wooText('tracking', 'Tracking code') + ': ')));
                        var code = el('code', '', esc(o.tracking));
                        code.dir = 'ltr';
                        track.appendChild(code);
                        if (navigator.clipboard) {
                                var copy = el('button', 'ssc-order__copy', esc(wooText('copy', 'Copy')));
                                copy.type = 'button';
                                copy.addEventListener('click', function () {
                                        navigator.clipboard.writeText(o.tracking).then(function () { copy.textContent = wooText('copied', 'Copied ✓'); }).catch(function () {});
                                });
                                track.appendChild(copy);
                        }
                        card.appendChild(track);
                }
                if (o.url) {
                        var more = el('a', 'ssc-order__link', esc(wooText('view', 'View')));
                        more.href = o.url;
                        card.appendChild(more);
                }
                return card;
        }

        function showRemindForm() {
                wooForm(wooText('remind', 'Remind me about my cart by SMS'), [
                        { name: 'phone', label: wooText('mobile', 'Mobile number'), type: 'tel', ltr: true, inputmode: 'tel' },
                        { name: 'consent', label: wooText('remindOk', 'I agree to receive one SMS reminder about my cart.'), type: 'checkbox' }
                ], wooText('send', 'Send'), function (params, card, err) {
                        return transport('woo/remind', params).then(function (res) {
                                if (!res || !res.success) {
                                        err.textContent = (res && res.data && res.data.message) || wooText('error', 'That did not work. Please try again.');
                                        return;
                                }
                                var done = el('div', 'ssc-msg ssc-msg--success', esc(res.data.message || ''));
                                card.parentElement.replaceChild(done, card);
                        }).catch(function () { err.textContent = wooText('error', 'That did not work. Please try again.'); });
                });
        }

        /* Smart offer: a teaser first; the coupon is created only when the visitor asks for it. */
        var wooOffer = null;

        function setupWooOffer() {
                var coupon = cfg.woo && cfg.woo.coupon;
                if (!coupon || cfg.preview) { return; }
                try { if (sessionStorage.getItem('ssc_woo_offer')) { return; } } catch (e) { /* storage off */ }
                var show = function () {
                        if (wooOffer || state.open) { return; }
                        try { sessionStorage.setItem('ssc_woo_offer', '1'); } catch (e) { /* storage off */ }
                        proactiveDismiss();
                        wooOffer = el('div', 'ssc-proactive ssc-proactive--offer');
                        wooOffer.setAttribute('role', 'status');
                        var invite = el('button', 'ssc-proactive__text', esc(coupon.teaser));
                        invite.type = 'button';
                        invite.setAttribute('dir', 'auto');
                        invite.addEventListener('click', function () {
                                closeOffer();
                                toggleWindow(true);
                                claimCoupon();
                        });
                        var dismiss = el('button', 'ssc-proactive__close', ICON_CLOSE);
                        dismiss.type = 'button';
                        dismiss.setAttribute('aria-label', (cfg.i18n && cfg.i18n.close) || 'Close');
                        dismiss.addEventListener('click', closeOffer);
                        wooOffer.appendChild(invite);
                        wooOffer.appendChild(dismiss);
                        root.appendChild(wooOffer);
                };
                if ((coupon.trigger === 'exit' || coupon.trigger === 'both') && !COARSE_POINTER) {
                        var onLeave = function (e) {
                                if (!e.relatedTarget && e.clientY <= 0) {
                                        document.removeEventListener('mouseout', onLeave);
                                        show();
                                }
                        };
                        window.setTimeout(function () { document.addEventListener('mouseout', onLeave); }, 5000);
                }
                // Hesitation: a while on a product or cart page without buying.
                var productPage = document.body && (document.body.classList.contains('single-product') || document.body.classList.contains('woocommerce-cart'));
                if ((coupon.trigger === 'idle' || coupon.trigger === 'both' || COARSE_POINTER) && productPage) {
                        window.setTimeout(show, Math.max(10, coupon.idle || 40) * 1000);
                }
        }

        function closeOffer() {
                if (wooOffer && wooOffer.parentElement) { wooOffer.parentElement.removeChild(wooOffer); }
                wooOffer = null;
        }

        function claimCoupon() {
                transport('woo/coupon', {}).then(function (res) {
                        if (!res || !res.success) {
                                addItem('bot', (res && res.data && res.data.message) || wooText('error', 'That did not work. Please try again.'), { history: false });
                                return;
                        }
                        var c = res.data;
                        var card = el('div', 'ssc-msg ssc-msg--bot ssc-coupon');
                        card.setAttribute('dir', 'auto');
                        card.appendChild(el('p', 'ssc-coupon__text', esc(c.text)));
                        var code = el('div', 'ssc-coupon__code', esc(c.code));
                        code.dir = 'ltr';
                        card.appendChild(code);
                        if (c.expires) { card.appendChild(el('p', 'ssc-coupon__exp', esc(wooText('expires', 'Valid until') + ': ' + c.expires))); }
                        var row = el('div', 'ssc-coupon__row');
                        if (navigator.clipboard) {
                                var copy = el('button', 'ssc-pcard__btn ssc-pcard__btn--ghost', esc(wooText('copy', 'Copy')));
                                copy.type = 'button';
                                copy.addEventListener('click', function () {
                                        navigator.clipboard.writeText(c.code).then(function () { copy.textContent = wooText('copied', 'Copied ✓'); }).catch(function () {});
                                });
                                row.appendChild(copy);
                        }
                        if (cfg.woo.applyNonce) {
                                var apply = el('button', 'ssc-pcard__btn', esc(wooText('apply', 'Apply to my cart')));
                                apply.type = 'button';
                                apply.addEventListener('click', function () {
                                        apply.disabled = true;
                                        wooAjax('apply_coupon', { coupon_code: c.code, security: cfg.woo.applyNonce }).then(function (r) { return r.text(); }).then(function (html) {
                                                var notice = new DOMParser().parseFromString(html, 'text/html').body.textContent.trim();
                                                var failed = /woocommerce-error|is-error/.test(html);
                                                apply.textContent = failed ? (notice || wooText('error', 'That did not work. Please try again.')) : wooText('applied', 'Applied to your cart ✓');
                                                if (failed) { apply.disabled = false; } else { wooCartChanged(); }
                                        }).catch(function () { apply.disabled = false; });
                                });
                                row.appendChild(apply);
                        }
                        card.appendChild(row);
                        thread.appendChild(card);
                        scrollDown();
                }).catch(function () {
                        addItem('bot', wooText('error', 'That did not work. Please try again.'), { history: false });
                });
        }

        /* ------------------------------------------------------------------ *
         * Live chat (module): a person can join the conversation
         * ------------------------------------------------------------------ */

        var live = { status: 'bot', lastId: 0, timer: null, operator: '', offered: false, bar: null };

        function liveText(key, fallback) {
                return (cfg.live && cfg.live.i18n && cfg.live.i18n[key]) || fallback;
        }

        /** Ask for a person; falls back to the request form when nobody can answer. */
        function requestHuman() {
                if (!(cfg.features && cfg.features.live)) { return; }
                transport('live/request', { conv: getConv(), page: window.location.href }).then(function (res) {
                        var data = (res && res.success && res.data) || {};
                        if (data.message) { addItem('bot', data.message, { history: false }); }
                        if (data.available) {
                                live.offered = false;
                                liveSetStatus(data.status || 'waiting');
                        } else if (cfg.features.leads) {
                                showLeadForm();
                        }
                }).catch(function () {
                        addItem('bot', (cfg.i18n && cfg.i18n.connectionError) || 'Connection error.', { history: false });
                });
        }

        /** Status bar above the composer while a person is involved. */
        function liveBar(text, withBack) {
                if (!live.bar) {
                        live.bar = el('div', 'ssc-livebar');
                        live.bar.setAttribute('role', 'status');
                        win.insertBefore(live.bar, composer);
                }
                live.bar.innerHTML = '';
                var label = el('span', 'ssc-livebar__text', esc(text));
                label.setAttribute('dir', 'auto');
                live.bar.appendChild(label);
                if (withBack) {
                        var back = el('button', 'ssc-livebar__back', esc(liveText('backToBot', 'Back to the assistant')));
                        back.type = 'button';
                        back.addEventListener('click', function () {
                                transport('live/leave', { conv: getConv() }).then(function () { liveSetStatus('bot'); pollLive(); });
                        });
                        live.bar.appendChild(back);
                }
                live.bar.hidden = false;
        }

        function liveSetStatus(status) {
                if (!status) { return; }
                live.status = status;
                if (status === 'waiting') {
                        liveBar(liveText('waiting', 'Waiting for a colleague to join…'), true);
                } else if (status === 'human') {
                        liveBar((live.operator || liveText('operator', 'Support team')) + ' ' + liveText('joined', 'joined the chat'), true);
                } else if (live.bar) {
                        live.bar.hidden = true;
                }
                scheduleLivePoll();
        }

        function scheduleLivePoll() {
                if (!(cfg.features && cfg.features.live) || cfg.preview) { return; }
                if (live.timer) { window.clearTimeout(live.timer); }
                var active = live.status === 'waiting' || live.status === 'human';
                var delay;
                if (active) {
                        delay = state.open ? 3000 : 10000;
                } else if (state.open && (state.hadConversation || live.lastId)) {
                        delay = 20000; // An operator may join a running conversation.
                } else {
                        return;
                }
                if (document.hidden) { delay = Math.max(delay, 15000); }
                live.timer = window.setTimeout(pollLive, delay);
        }

        function pollLive() {
                if (!(cfg.features && cfg.features.live) || cfg.preview) { return; }
                transport('live/poll', { conv: getConv(), after: live.lastId }).then(function (res) {
                        var data = (res && res.success && res.data) || {};
                        if (data.operator) { live.operator = data.operator; }
                        var fresh = false;
                        (data.messages || []).forEach(function (m) {
                                live.lastId = Math.max(live.lastId, m.id);
                                if (m.sender === 'operator') {
                                        addOperatorMessage(m.name, m.body);
                                        fresh = true;
                                } else if (m.sender === 'system') {
                                        addItem('note', m.body, { history: false });
                                }
                        });
                        if (fresh && !state.open) { bumpUnread(); maybeBeep(); }
                        if (data.status && data.status !== live.status) {
                                liveSetStatus(data.status);
                        } else if (live.status === 'human' && live.bar && data.operator) {
                                liveSetStatus('human');
                        } else {
                                scheduleLivePoll();
                        }
                        // Nobody picked up in time: offer the request form instead.
                        var limit = ((cfg.live && cfg.live.waitMinutes) || 3) * 60;
                        if (live.status === 'waiting' && data.waited >= limit && !live.offered) {
                                live.offered = true;
                                addItem('bot', liveText('noAnswer', 'Nobody has picked up yet. Would you like to leave your number instead?'), { history: false });
                                var chips = [{ label: liveText('keepWaiting', 'Keep waiting'), onClick: function () {} }];
                                if (cfg.features.leads) { chips.unshift({ label: liveText('leaveNumber', 'Leave my number'), onClick: showLeadForm }); }
                                addChips(chips);
                        }
                }).catch(function () { scheduleLivePoll(); });
        }

        function addOperatorMessage(name, text) {
                var node = addItem('operator', text, { history: false });
                var who = el('span', 'ssc-msg__who', esc(name || liveText('operator', 'Support team')));
                node.insertBefore(who, node.firstChild);
                return node;
        }

        /* ------------------------------------------------------------------ *
         * ADR form (pharma module)
         * ------------------------------------------------------------------ */

        function showAdrForm() {
                var card = el('div', 'ssc-cardform ssc-cardform--adr');
                card.appendChild(el('h3', 'ssc-cardform__title', esc((cfg.i18n && cfg.i18n.reportAdr) || 'Report side effect')));

                var adrForm = cfg.adrForm || {};
                if (adrForm.intro) {
                        var intro = el('p', 'ssc-cardform__intro', esc(adrForm.intro));
                        intro.setAttribute('dir', 'auto');
                        card.appendChild(intro);
                }

                var form = el('form', 'ssc-cardform__form');
                form.setAttribute('novalidate', 'novalidate');

                var hp = el('input', 'ssc-hp');
                hp.type = 'text';
                hp.name = 'ssc_hp';
                hp.tabIndex = -1;
                hp.setAttribute('aria-hidden', 'true');
                form.appendChild(hp);

                // Optional questions can fold under "More details" so the form looks short.
                var more = null;
                var hasOptional = (cfg.adrOptions || []).some(function (f) { return !f.required; });
                if (adrForm.collapse && hasOptional) {
                        more = el('details', 'ssc-cardform__more');
                        more.appendChild(el('summary', 'ssc-cardform__more-toggle', esc((cfg.i18n && cfg.i18n.moreDetails) || 'More details (optional)')));
                }
                var ordered = (cfg.adrOptions || []).slice();
                if (more) {
                        ordered = ordered.filter(function (f) { return f.required; }).concat(ordered.filter(function (f) { return !f.required; }));
                }

                ordered.forEach(function (f) {
                        var host = (more && !f.required) ? more : form;
                        if (more && !f.required && !more.parentNode) { form.appendChild(more); }
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
                                host.appendChild(fs);
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
                                host.appendChild(wrap);
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
                                host.appendChild(wrap2);
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
                                host.appendChild(wrap3);
                                return;
                        }
                        input.name = f.key;
                        if (f.type === 'tel' || f.type === 'number') { input.dir = 'ltr'; }
                        if (f.type === 'number') { input.step = 'any'; }
                        if (f.key === 'patient_age') { input.min = '0'; input.max = '130'; }
                        if (f.required) { input.required = true; }
                        wrap3.appendChild(input);
                        host.appendChild(wrap3);
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
                paintVars(root);
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
                setupWooOffer();
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
                paintVars(host);
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
                        avatar.innerHTML = avatarMarkup();
                        avatar.classList.toggle('has-img', !!cfg.avatarUrl);
                        avatar.classList.toggle('has-mascot', !cfg.avatarUrl && 'mascot' === cfg.launcherStyle);
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
                        paintVars(mountNode);
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
