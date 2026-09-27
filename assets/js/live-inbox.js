/**
 * NexaChatAI — Live chat inbox (operator side).
 *
 * Polls the inbox REST routes: the thread list every few seconds (this is
 * also the operator's "online" heartbeat) and the open conversation more
 * often. Everything is rendered as text (never HTML) from the server data.
 */
(function () {
	'use strict';

	var cfg = window.SSCLiveInbox;
	var listEl = document.getElementById('ssc-live-threads');
	var chatEl = document.getElementById('ssc-live-chat');
	if (!cfg || !listEl || !chatEl) { return; }

	var t = cfg.i18n || {};
	var state = {
		scope: 'open',
		threads: [],
		current: 0,
		lastId: 0,
		thread: null,
		waiting: 0,
		sending: false
	};

	/**
	 * REST address for a route plus query. Sites without pretty permalinks
	 * give a root like "…/index.php?rest_route=/ssc/v1/live/admin/", so the
	 * query must be joined with "&" there, never a second "?".
	 */
	function url(path, query) {
		var base = cfg.root + path;
		var qs = [];
		Object.keys(query || {}).forEach(function (k) { qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(query[k])); });
		if (!qs.length) { return base; }
		return base + (base.indexOf('?') === -1 ? '?' : '&') + qs.join('&');
	}

	function api(path, opts) {
		opts = opts || {};
		var init = {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce }
		};
		if (opts.body) {
			init.headers['Content-Type'] = 'application/json';
			init.body = JSON.stringify(opts.body);
		}
		return fetch(url(path, opts.query), init).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) { throw new Error((data && data.message) || t.error); }
				return data;
			});
		});
	}

	function el(tag, cls, text) {
		var node = document.createElement(tag);
		if (cls) { node.className = cls; }
		if (text !== undefined && text !== null) { node.textContent = text; }
		return node;
	}

	/* ---------- Thread list ---------- */

	function renderList() {
		listEl.textContent = '';
		if (!state.threads.length) {
			listEl.appendChild(el('li', 'ssc-live__none', t.empty));
			return;
		}
		state.threads.forEach(function (th) {
			var li = el('li', 'ssc-live__thread is-' + th.status + (th.id === state.current ? ' is-current' : ''));
			var btn = el('button', 'ssc-live__thread-btn');
			btn.type = 'button';
			btn.setAttribute('data-id', th.id);
			var top = el('span', 'ssc-live__thread-top');
			var name = el('strong', '', th.label);
			name.setAttribute('dir', 'auto');
			top.appendChild(name);
			if (th.unread && th.id !== state.current) { top.appendChild(el('span', 'ssc-live__unread', String(th.unread))); }
			btn.appendChild(top);
			var meta = el('span', 'ssc-live__thread-meta');
			meta.appendChild(el('span', 'ssc-live__pill ssc-live__pill--' + th.status, th.statusLabel));
			if (th.channel !== 'web') { meta.appendChild(el('span', 'ssc-live__channel', th.channel === 'telegram' ? 'Telegram' : 'Bale')); }
			if (th.operatorName) { meta.appendChild(el('span', 'ssc-live__op', th.operatorName)); }
			meta.appendChild(el('span', 'ssc-live__time', th.updatedHuman));
			btn.appendChild(meta);
			btn.addEventListener('click', function () { open(th.id); });
			li.appendChild(btn);
			listEl.appendChild(li);
		});
	}

	function loadList() {
		return api('threads', { query: { scope: state.scope } }).then(function (data) {
			state.threads = data.items || [];
			var avail = document.getElementById('ssc-live-available');
			if (avail && document.activeElement !== avail) { avail.checked = !!data.available; syncAvailLabel(); }
			if (data.waiting > state.waiting) { chime(); }
			state.waiting = data.waiting;
			document.title = (data.waiting ? '(' + data.waiting + ') ' : '') + document.title.replace(/^\(\d+\)\s/, '');
			renderList();
		}).catch(function () { /* next tick retries */ });
	}

	/* ---------- Conversation ---------- */

	function open(id) {
		state.current = id;
		state.lastId = 0;
		state.thread = null;
		chatEl.textContent = '';
		buildChat();
		renderList();
		loadThread(true);
	}

	var ui = {};

	function buildChat() {
		ui.head = el('div', 'ssc-live__head');
		ui.title = el('div', 'ssc-live__title');
		ui.actions = el('div', 'ssc-live__actions');
		ui.head.appendChild(ui.title);
		ui.head.appendChild(ui.actions);

		ui.summary = el('div', 'ssc-live__summary');

		ui.messages = el('div', 'ssc-live__messages');
		ui.messages.setAttribute('role', 'log');

		ui.form = el('form', 'ssc-live__composer');
		ui.input = el('textarea', 'ssc-live__input');
		ui.input.rows = 2;
		ui.input.placeholder = t.placeholder;
		ui.input.setAttribute('dir', 'auto');
		ui.input.setAttribute('aria-label', t.placeholder);
		ui.send = el('button', 'button button-primary', t.send);
		ui.send.type = 'submit';
		var row = el('div', 'ssc-live__composer-row');
		if (cfg.canned && cfg.canned.length) {
			var canned = el('select', 'ssc-live__canned');
			canned.setAttribute('aria-label', t.canned);
			canned.appendChild(el('option', '', t.canned + '…')).value = '';
			cfg.canned.forEach(function (text) {
				var o = el('option', '', text.length > 70 ? text.slice(0, 70) + '…' : text);
				o.value = text;
				canned.appendChild(o);
			});
			canned.addEventListener('change', function () {
				if (!canned.value) { return; }
				ui.input.value = (ui.input.value ? ui.input.value + '\n' : '') + canned.value;
				canned.value = '';
				ui.input.focus();
			});
			row.appendChild(canned);
		}
		row.appendChild(ui.send);
		ui.form.appendChild(ui.input);
		ui.form.appendChild(row);
		ui.form.addEventListener('submit', function (e) { e.preventDefault(); send(); });
		ui.input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); }
		});

		chatEl.appendChild(ui.head);
		chatEl.appendChild(ui.summary);
		chatEl.appendChild(ui.messages);
		chatEl.appendChild(ui.form);
	}

	function renderHead(th) {
		ui.title.textContent = '';
		var name = el('strong', '', th.label);
		name.setAttribute('dir', 'auto');
		ui.title.appendChild(name);
		ui.title.appendChild(el('span', 'ssc-live__pill ssc-live__pill--' + th.status, th.statusLabel));
		if (th.page) {
			var a = el('a', 'ssc-live__page', t.page);
			a.href = th.page;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.title = th.page;
			ui.title.appendChild(a);
		}
		ui.actions.textContent = '';
		var add = function (label, action, primary) {
			var b = el('button', 'button' + (primary ? ' button-primary' : ''), label);
			b.type = 'button';
			b.addEventListener('click', function () { act(action); });
			ui.actions.appendChild(b);
		};
		var mine = Number(th.operator) === Number(cfg.me);
		if (th.status === 'bot' || th.status === 'waiting' || (th.status === 'human' && !mine)) { add(t.take, 'take', true); }
		if (th.status === 'human') { add(t.release, 'release'); }
		if (th.status !== 'closed') { add(t.close, 'close'); }
		var closed = th.status === 'closed' && th.channel === 'web';
		ui.input.disabled = closed;
		ui.send.disabled = closed;
		ui.input.placeholder = closed ? t.closedNote : t.placeholder;

		ui.summary.textContent = '';
		ui.summary.appendChild(el('strong', '', t.summary));
		if (th.summary) {
			var p = el('p', 'ssc-live__summary-text', th.summary);
			p.setAttribute('dir', 'auto');
			ui.summary.appendChild(p);
		}
		var sb = el('button', 'button-link', th.summary ? '↻' : t.summarize);
		sb.type = 'button';
		sb.setAttribute('aria-label', t.summarize);
		sb.addEventListener('click', function () {
			sb.disabled = true;
			sb.textContent = t.summarizing;
			api('thread/' + th.id + '/summary', { method: 'POST', body: {} }).then(function (data) {
				th.summary = data.summary;
				renderHead(th);
			}).catch(function (err) { sb.disabled = false; sb.textContent = err.message; });
		});
		ui.summary.appendChild(sb);
	}

	/** The assistant writes Markdown; operators read it as clean text. */
	function plainMarkdown(text) {
		return String(text || '')
			.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '$1 ($2)')
			.replace(/\*\*([^*\n]+)\*\*/g, '$1')
			.replace(/^#{1,6}\s*/gm, '')
			.replace(/`([^`\n]+)`/g, '$1');
	}

	function renderMessage(m) {
		var row = el('div', 'ssc-live__msg ssc-live__msg--' + m.sender);
		if (m.sender !== 'system') {
			var who = m.sender === 'visitor' ? t.visitor : (m.sender === 'bot' ? t.assistant : m.name);
			var meta = el('span', 'ssc-live__msg-meta', who + (m.at ? ' · ' + m.at : '') + (m.via && m.via !== 'web' && m.via !== 'admin' ? ' · ' + m.via : ''));
			row.appendChild(meta);
		}
		var body = el('div', 'ssc-live__msg-body', m.sender === 'bot' ? plainMarkdown(m.body) : m.body);
		body.setAttribute('dir', 'auto');
		row.appendChild(body);
		ui.messages.appendChild(row);
	}

	function loadThread(first) {
		if (!state.current) { return Promise.resolve(); }
		var id = state.current;
		return api('thread/' + id, { query: { after: state.lastId } }).then(function (data) {
			if (id !== state.current) { return; }
			var nearBottom = ui.messages.scrollHeight - ui.messages.scrollTop - ui.messages.clientHeight < 80;
			var th = data.thread;
			var changed = !state.thread || state.thread.status !== th.status || state.thread.operator !== th.operator || state.thread.summary !== th.summary;
			state.thread = th;
			if (changed || first) { renderHead(th); }
			(data.messages || []).forEach(function (m) {
				renderMessage(m);
				state.lastId = Math.max(state.lastId, m.id);
			});
			if (first || nearBottom) { ui.messages.scrollTop = ui.messages.scrollHeight; }
		}).catch(function () { /* retry on next tick */ });
	}

	function send() {
		var text = ui.input.value.trim();
		if (!text || state.sending || !state.current) { return; }
		state.sending = true;
		ui.send.disabled = true;
		api('thread/' + state.current + '/reply', { method: 'POST', body: { body: text } }).then(function () {
			ui.input.value = '';
			return loadThread(false).then(loadList);
		}).catch(function (err) {
			window.alert(err.message || t.error);
		}).then(function () {
			state.sending = false;
			ui.send.disabled = false;
			ui.input.focus();
		});
	}

	function act(action) {
		if (!state.current) { return; }
		api('thread/' + state.current + '/action', { method: 'POST', body: { action: action } }).then(function () {
			return loadThread(false).then(loadList);
		}).catch(function (err) { window.alert(err.message || t.error); });
	}

	/* ---------- Presence, messenger link, chime ---------- */

	function syncAvailLabel() {
		var box = document.getElementById('ssc-live-available');
		var label = document.getElementById('ssc-live-available-label');
		if (box && label) { label.textContent = box.checked ? t.available : t.away; }
	}

	var avail = document.getElementById('ssc-live-available');
	if (avail) {
		avail.addEventListener('change', function () {
			syncAvailLabel();
			api('presence', { method: 'POST', body: { available: avail.checked } }).catch(function () {});
		});
	}

	function renderLink(linked) {
		var box = document.getElementById('ssc-live-link');
		if (!box || !cfg.bot) { return; }
		box.textContent = '';
		if (linked) {
			box.appendChild(el('span', 'ssc-live__linked', '✓ ' + t.linked));
			var un = el('button', 'button-link', t.unlink);
			un.type = 'button';
			un.addEventListener('click', function () {
				api('link', { method: 'POST', body: { do: 'unlink' } }).then(function () { renderLink(false); });
			});
			box.appendChild(un);
			return;
		}
		var b = el('button', 'button', t.link);
		b.type = 'button';
		b.addEventListener('click', function () {
			api('link', { method: 'POST', body: {} }).then(function (data) {
				box.textContent = '';
				var help = el('span', 'ssc-live__linkhelp', t.linkHelp.replace('%s', data.code));
				box.appendChild(help);
				if (data.url) {
					var a = el('a', 'button button-primary', t.openBot);
					a.href = data.url;
					a.target = '_blank';
					a.rel = 'noopener noreferrer';
					box.appendChild(a);
				}
			}).catch(function (err) { window.alert(err.message || t.error); });
		});
		box.appendChild(b);
	}
	renderLink(!!cfg.linked);

	var audioCtx = null;
	function chime() {
		try {
			audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
			var o = audioCtx.createOscillator();
			var g = audioCtx.createGain();
			o.frequency.value = 880;
			g.gain.setValueAtTime(0.08, audioCtx.currentTime);
			g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.35);
			o.connect(g); g.connect(audioCtx.destination);
			o.start(); o.stop(audioCtx.currentTime + 0.35);
		} catch (e) { /* audio unavailable */ }
	}

	/* ---------- Tabs and timers ---------- */

	Array.prototype.forEach.call(document.querySelectorAll('.ssc-live__tabs [data-scope]'), function (tab) {
		tab.addEventListener('click', function () {
			state.scope = tab.getAttribute('data-scope');
			Array.prototype.forEach.call(document.querySelectorAll('.ssc-live__tabs [data-scope]'), function (x) {
				x.classList.toggle('is-active', x === tab);
				x.setAttribute('aria-selected', x === tab ? 'true' : 'false');
			});
			loadList();
		});
	});

	var params = new URLSearchParams(window.location.search);
	loadList().then(function () {
		var wanted = parseInt(params.get('thread') || '0', 10);
		if (wanted) { open(wanted); }
	});
	window.setInterval(function () { if (!document.hidden) { loadList(); } }, 5000);
	window.setInterval(function () { if (!document.hidden) { loadThread(false); } }, 3000);
	// Hidden tabs still send a slow heartbeat so the operator stays "online".
	window.setInterval(function () { if (document.hidden) { loadList(); } }, 60000);
})();
