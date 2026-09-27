/**
 * Smart Support Chatbot — admin interactions.
 * Vanilla JS (no jQuery dependency). RTL-agnostic.
 */
(function () {
	'use strict';

	var cfg = window.SSCAdmin || {};
	var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
	var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

	/* ---------- Provider field switching ---------- */

	var providerSelect = $('#ai_provider');

	function currentProvider() {
		return providerSelect ? providerSelect.value : '';
	}

	function syncProviderFields() {
		if (!providerSelect) { return; }
		var current = currentProvider();
		$$('.ssc-provider-fields').forEach(function (block) {
			block.hidden = (block.getAttribute('data-provider') !== current);
		});
	}

	if (providerSelect) {
		providerSelect.addEventListener('change', syncProviderFields);
		syncProviderFields();
	}

	/* ---------- Manual model entry ---------- */

	/** The manual input that belongs to ONE select (every provider block has its own). */
	function manualInputFor(select) {
		var scope = select.closest('.ssc-field') || select.parentElement;
		return scope ? scope.querySelector('.ssc-model-manual') : null;
	}

	function bindManualModel(select) {
		if (!select || select.getAttribute('data-manual') !== '1') { return; }
		// Scoped to this select's own field: a form holds one block per provider.
		var input = manualInputFor(select);
		if (!input) { return; }

		function sync() {
			if (select.value === '__manual__') {
				input.hidden = false;
				input.removeAttribute('disabled');
				input.focus();
			} else {
				input.hidden = true;
				input.setAttribute('disabled', 'disabled');
			}
		}

		/*
		 * Manual value must win on submit. Assigning an unlisted value to a
		 * <select> silently yields '' (selectedIndex -1), so the typed model
		 * has to be materialized as a real <option> first. The manual input
		 * also POSTs under its own name as a no-JS/server-side fallback.
		 */
		var formEl = select.closest('form');
		if (formEl) {
			formEl.addEventListener('submit', function () {
				if (select.value !== '__manual__') { return; }
				var typed = input.value.trim();
				if ('' === typed) { return; }
				var option = select.querySelector('option[data-manual-value="1"]');
				if (!option) {
					option = document.createElement('option');
					option.setAttribute('data-manual-value', '1');
					select.appendChild(option);
				}
				option.value = typed;
				option.textContent = typed;
				select.value = typed;
			});
		}
		select.addEventListener('change', sync);
		sync();
	}

	$$('select[data-manual]').forEach(bindManualModel);

	/* ---------- Repeatable rows (knowledge, products, form fields) ---------- */

	var ROW_SELECTOR = ':scope > .ssc-ki, :scope > .ssc-product, :scope > .ssc-fieldrow';

	function directRows(list) {
		return $$(ROW_SELECTOR, list);
	}

	/**
	 * Next free index for a set of field names: one past the HIGHEST index in
	 * use, never the row count. After a middle row is removed the remaining
	 * names are sparse (e.g. [0], [2]) and a count-based index would collide,
	 * silently overwriting an existing entry when PHP rebuilds the array.
	 */
	function nextIndexFor(names) {
		var max = -1;
		names.forEach(function (name) {
			var found = /\[(\d+)\]/.exec(name || '');
			if (found) { max = Math.max(max, parseInt(found[1], 10)); }
		});
		return max + 1;
	}

	function nextIndex(list) {
		var names = [];
		directRows(list).forEach(function (row) {
			$$('[name]', row).forEach(function (input) { names.push(input.getAttribute('name')); });
		});
		return nextIndexFor(names);
	}

	function rewriteNames(row, idx) {
		$$('[name]', row).forEach(function (input) {
			var name = input.getAttribute('name');
			input.setAttribute('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
		});
	}

	function bindAddButtons() {
		$$('.ssc-ki__add, .ssc-product__add, .ssc-field__add').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var list = $(btn.getAttribute('data-target'));
				if (!list) { return; }
				var first = directRows(list)[0] || list.firstElementChild;
				if (!first) { return; }
				var clone = first.cloneNode(true);
				$$('input[type="text"], input[type="url"], input[type="email"], textarea', clone).forEach(function (i) { i.value = ''; });
				$$('input[type="checkbox"]', clone).forEach(function (i) { i.checked = false; });
				// Identity fields must not be copied: a cloned key/id made two rows share one key.
				$$('input[type="hidden"]', clone).forEach(function (i) {
					if (/\[(key|id)\]$/.test(i.getAttribute('name') || '')) { i.value = ''; }
				});
				$$('select', clone).forEach(function (sel) { sel.selectedIndex = 0; });
				$$('.ssc-product__attrrow', clone).forEach(function (r, i) { if (i > 0) { r.remove(); } });
				var idx = nextIndex(list);
				rewriteNames(clone, idx);
				list.appendChild(clone);
				var focus = $('input[type="text"], input[type="url"]', clone);
				if (focus) { focus.focus(); }
				bindRemove(clone);
			});
		});
	}

	function bindRemove(scope) {
		$$('.ssc-ki__remove', scope || document).forEach(function (btn) {
			if (btn.__sscBound) { return; }
			btn.__sscBound = true;
			btn.addEventListener('click', function () {
				var row = btn.closest('.ssc-ki, .ssc-product, .ssc-fieldrow');
				if (row && row.parentElement) {
					var siblings = $$('.ssc-ki, .ssc-product, .ssc-fieldrow', row.parentElement);
					if (siblings.length > 1 || row.parentElement.children.length > 1) {
						row.remove();
					}
				}
			});
		});
	}

	bindAddButtons();
	bindRemove();

	/* ---------- Form builder: choices only for dropdown / single choice ---------- */

	function syncFieldRow(row) {
		var type = $('select', row);
		var options = $('.ssc-fieldrow__options', row);
		if (!type || !options) { return; }
		options.hidden = !(type.value === 'select' || type.value === 'radio');
	}

	$$('.ssc-fieldrow').forEach(syncFieldRow);
	document.addEventListener('change', function (e) {
		var row = e.target && e.target.closest && e.target.closest('.ssc-fieldrow');
		if (row && e.target.tagName === 'SELECT') { syncFieldRow(row); }
	});
	document.addEventListener('click', function (e) {
		if (e.target && e.target.closest && e.target.closest('.ssc-field__add')) {
			window.setTimeout(function () { $$('.ssc-fieldrow').forEach(syncFieldRow); }, 0);
		}
	});

	/* ---------- Appearance live preview (light + dark) ---------- */

	/*
	 * The preview is the REAL widget (mounted by chatbot.js in preview mode);
	 * form fields are mapped onto its config and repainted live, so the admin
	 * always sees exactly what visitors will get.
	 */
	function bindPreview() {
		var panel = $('#ssc-preview-panel');
		var mount = $('#ssc-live-preview');
		if (!panel || !mount) { return; }
		var overrideTheme = null; // Light/Dark toggle: preview only, not saved.

		var fields = {
			primary_color: 'primaryColor',
			theme_mode: 'themeMode',
			position: 'position',
			direction: 'direction',
			assistant_display_name: 'assistantName',
			avatar_url: 'avatarUrl',
			welcome_title: 'welcomeTitle',
			welcome_text: 'welcomeText',
			disclaimer: 'disclaimer',
			font_size: 'fontSize',
			window_radius: 'windowRadius',
			bubble_radius: 'bubbleRadius',
			user_bubble_color: 'userBubble',
			bot_bubble_color: 'botBubble',
			launcher_size: 'launcherSize'
		};
		var numeric = { font_size: 1, window_radius: 1, bubble_radius: 1, launcher_size: 1 };

		function patch() {
			var out = {};
			Object.keys(fields).forEach(function (id) {
				var el = document.getElementById(id);
				if (!el) { return; }
				var v = (el.value || '').trim();
				if (numeric[id]) { v = parseInt(v, 10) || 0; }
				out[fields[id]] = v;
			});
			if (!out.assistantName) { out.assistantName = panel.getAttribute('data-asst-name') || ''; }
			if (!out.welcomeTitle) { out.welcomeTitle = panel.getAttribute('data-w-title') || ''; }
			if (!out.welcomeText) { out.welcomeText = panel.getAttribute('data-w-text') || ''; }
			if ('auto' === out.direction) { out.direction = panel.getAttribute('data-dir-auto') || 'rtl'; }
			if (overrideTheme) { out.themeMode = overrideTheme; }
			return out;
		}

		function update() {
			if (window.SSCChatbot && window.SSCChatbot.applyConfig) { window.SSCChatbot.applyConfig(patch()); }
		}

		function mountWhenReady(tries) {
			if (window.SSCChatbot && window.SSCChatbot.mountPreview) {
				window.SSCChatbot.mountPreview(mount);
				update();
				return;
			}
			if (tries > 0) { window.setTimeout(function () { mountWhenReady(tries - 1); }, 100); }
		}

		$$('.ssc-preview-toggle [data-pv-theme]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				overrideTheme = btn.getAttribute('data-pv-theme');
				$$('.ssc-preview-toggle [data-pv-theme]').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
				update();
			});
		});

		Object.keys(fields).forEach(function (id) {
			var el = document.getElementById(id);
			if (!el) { return; }
			el.addEventListener('input', update);
			el.addEventListener('change', function () {
				if ('theme_mode' === id) {
					overrideTheme = null;
					var want = 'dark' === el.value ? 'dark' : 'light';
					$$('.ssc-preview-toggle [data-pv-theme]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-pv-theme') === want); });
				}
				update();
			});
		});

		var saved = panel.getAttribute('data-theme-mode') || 'light';
		var want = 'dark' === saved ? 'dark' : 'light';
		$$('.ssc-preview-toggle [data-pv-theme]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-pv-theme') === want); });

		if ('complete' === document.readyState) { mountWhenReady(50); } else { window.addEventListener('load', function () { mountWhenReady(50); }); }
	}

	bindPreview();

	/* ---------- Connection test (wizard + connection page) ---------- */

	function api(route, body) {
		var url = cfg.restUrl.replace(/\/$/, '') + '/' + route;
		return fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.restNonce
			},
			credentials: 'same-origin',
			body: JSON.stringify(body || {})
		}).then(function (res) { return res.json(); });
	}

	function gatherCredentials() {
		var provider = currentProvider() || 'none';
		var fields = { provider: provider };
		var keyInput = $('#' + provider + '_api_key');
		if (keyInput && keyInput.value.trim()) { fields.api_key = keyInput.value.trim(); }

		// One element serves both shapes: a <select> for known models, a text
		// <input> for providers that ship no list. Read the manual field from
		// THIS provider's block, never the first one on the page.
		var modelEl = $('#' + provider + '_model');
		if (modelEl && 'SELECT' === modelEl.tagName) {
			var manual = manualInputFor(modelEl);
			fields.model = (modelEl.value === '__manual__' && manual) ? manual.value.trim() : modelEl.value;
		} else if (modelEl && modelEl.value.trim()) {
			fields.model = modelEl.value.trim();
		}

		var endpointInput = $('#custom_endpoint');
		if ('custom' === provider && endpointInput && endpointInput.value.trim()) { fields.endpoint = endpointInput.value.trim(); }
		var hookInput = $('#ai_webhook_url');
		if ('webhook' === provider && hookInput && hookInput.value.trim()) { fields.endpoint = hookInput.value.trim(); }

		return fields;
	}

	function bindConnectionTest() {
		var btn = $('#ssc-test-connection');
		var out = $('#ssc-test-result');
		if (!btn || !out) { return; }

		btn.addEventListener('click', function () {
			btn.setAttribute('disabled', 'disabled');
			out.className = 'ssc-conn-test__result';
			out.textContent = '…';

			api('test-connection', gatherCredentials()).then(function (res) {
				btn.removeAttribute('disabled');
				if (res && res.ok) {
					out.className = 'ssc-conn-test__result is-ok';
					out.textContent = '✓ ' + (res.message || 'Connection verified');
				} else {
					out.className = 'ssc-conn-test__result is-err';
					var msg = (res && res.message) ? res.message : 'Connection failed';
					if (res && res.code) { msg += ' (' + res.code + ')'; }
					out.textContent = '✕ ' + msg;
				}
			}).catch(function () {
				btn.removeAttribute('disabled');
				out.className = 'ssc-conn-test__result is-err';
				out.textContent = '✕ ' + 'Request failed — check your connection and retry.';
			});
		});
	}

	bindConnectionTest();

	/* ---------- Identity test (wizard review step) ---------- */

	function bindIdentityTest() {
		var btn = $('#ssc-run-identity-test');
		var out = $('#ssc-identity-result');
		if (!btn || !out) { return; }

		btn.addEventListener('click', function () {
			btn.setAttribute('disabled', 'disabled');
			out.innerHTML = '…';

			api('test-identity', {}).then(function (res) {
				btn.removeAttribute('disabled');
				var html = '';
				if (res && res.ok) {
					html += '<div class="ssc-notice ssc-notice--success">' + esc(res.message) + '</div>';
					// Saved server-side already: tick the checklist without a reload.
					document.dispatchEvent(new CustomEvent('ssc:launch-item', { detail: { id: 'identity_test', done: true } }));
				} else {
					html += '<div class="ssc-notice ssc-notice--error">' + esc(res && res.message ? res.message : 'Test failed') + '</div>';
				}
				if (res && res.reply) {
					html += '<blockquote dir="auto">' + esc(String(res.reply).replace(/\*\*/g, '')) + '</blockquote>';
				}
				out.innerHTML = html;
			}).catch(function () {
				btn.removeAttribute('disabled');
				out.innerHTML = '<div class="ssc-notice ssc-notice--error">Request failed — check your connection and retry.</div>';
			});
		});
	}

	function esc(text) {
		var d = document.createElement('div');
		d.textContent = String(text);
		return d.innerHTML;
	}

	bindIdentityTest();

	/* ---------- Wizard preview widget mount ---------- */

	function mountPreviewWidget() {
		var mount = $('#ssc-preview-mount');
		var wcfg = window.SSCChatbotConfig;
		if (!mount || !wcfg || !wcfg.preview || !window.SSCChatbot) { return; }
		if (typeof window.SSCChatbot.mountPreview !== 'function') { return; }
		window.SSCChatbot.mountPreview(mount);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', mountPreviewWidget);
	} else {
		mountPreviewWidget();
	}

	/* ---------- Font family conditional rows ---------- */

	var fontSelect = $('#font_family');
	if (fontSelect) {
		var syncFont = function () {
			$$('[data-show-when]').forEach(function (row) {
				var cond = row.getAttribute('data-show-when') || '';
				var parts = cond.split('=');
				var match = (parts[0] === 'font_family' && parts[1] === fontSelect.value);
				row.hidden = !match;
			});
		};
		fontSelect.addEventListener('change', syncFont);
		syncFont();
	}

	/* ---------- Status auto-submit forms ---------- */

	$$('.ssc-statusform select').forEach(function (sel) {
		sel.addEventListener('change', function () {
			var form = sel.closest('form');
			if (form) { form.submit(); }
		});
	});
})();

/* ---------- Settings tabs (progressive: without JS every card stays visible) ---------- */
(function () {
	'use strict';
	var nav = document.querySelector('.ssc-tabs');
	if (!nav) { return; }
	var sections = Array.prototype.slice.call(document.querySelectorAll('[data-ssc-tab]'));
	var tabs = Array.prototype.slice.call(nav.querySelectorAll('.ssc-tabs__tab'));
	var actions = document.querySelector('.ssc-form__actions');
	var KEY = 'ssc_settings_tab';

	// Hide tabs that have no content (e.g. no module is active).
	tabs = tabs.filter(function (tab) {
		var has = sections.some(function (sec) { return sec.getAttribute('data-ssc-tab') === tab.getAttribute('data-tab'); });
		if (!has) { tab.parentElement.removeChild(tab); }
		return has;
	});
	if (tabs.length < 2) { return; }

	function select(id, focus) {
		var found = tabs.some(function (tab) { return tab.getAttribute('data-tab') === id; });
		if (!found) { id = tabs[0].getAttribute('data-tab'); }
		tabs.forEach(function (tab) {
			var on = tab.getAttribute('data-tab') === id;
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.tabIndex = on ? 0 : -1;
			if (on && focus) { tab.focus(); }
		});
		sections.forEach(function (sec) {
			sec.hidden = sec.getAttribute('data-ssc-tab') !== id;
			sec.setAttribute('role', 'tabpanel');
			sec.setAttribute('aria-labelledby', 'ssc-tab-' + sec.getAttribute('data-ssc-tab'));
		});
		// Data tools have their own buttons; the main save belongs to form tabs.
		if (actions) { actions.hidden = 'data' === id; }
		try { sessionStorage.setItem(KEY, id); } catch (e) { /* storage unavailable */ }
		if (window.history && window.history.replaceState) { window.history.replaceState(null, '', '#' + id); }
	}

	tabs.forEach(function (tab, i) {
		tab.addEventListener('click', function () { select(tab.getAttribute('data-tab'), false); });
		tab.addEventListener('keydown', function (e) {
			var dir = (e.key === 'ArrowRight') ? 1 : (e.key === 'ArrowLeft') ? -1 : 0;
			if (document.dir === 'rtl') { dir = -dir; }
			if (!dir) { return; }
			e.preventDefault();
			var next = tabs[(i + dir + tabs.length) % tabs.length];
			select(next.getAttribute('data-tab'), true);
		});
	});

	var initial = (window.location.hash || '').replace('#', '');
	if (!initial) { try { initial = sessionStorage.getItem(KEY) || ''; } catch (e) { initial = ''; } }
	nav.hidden = false;
	select(initial, false);
})();

/* ---------- Answer scope & web search (AI Connection) ---------- */
(function () {
	'use strict';
	var card = document.getElementById('ssc-scope-card');
	if (!card) { return; }
	var web = document.getElementById('web_search');
	var hint = card.querySelector('.ssc-web-unsupported');
	var provider = document.getElementById('ai_provider');
	var SUPPORTED = { openai: 1, claude: 1, gemini: 1, openrouter: 1 };

	function sync() {
		var scope = card.querySelector('input[name="answer_scope"]:checked');
		var knowledgeOnly = scope && scope.value === 'knowledge';
		var fixed = card.querySelector('.ssc-choices[disabled]'); // Pharma policy owns the scope.
		if (web && !fixed) { web.disabled = !!knowledgeOnly; }
		if (hint) { hint.hidden = !provider || !!SUPPORTED[provider.value]; }
	}
	card.addEventListener('change', sync);
	if (provider) { provider.addEventListener('change', sync); }
	sync();
})();

/* ---------- Launch checklist (wizard review step) ----------
 * The checklist is rendered once by the server, but two items are completed
 * on this very screen: the identity test (AJAX) and the privacy confirmation
 * (a checkbox that is only saved together with Publish). Without live updates
 * the Publish button stayed disabled forever. The server re-validates every
 * item when Publish is submitted.
 */
(function () {
	'use strict';
	var list = document.getElementById('ssc-launch-checklist');
	var publish = document.getElementById('ssc-publish');
	if (!list || !publish) { return; }
	var hint = document.getElementById('ssc-launch-hint');
	var privacy = document.querySelector('#ssc-privacy-ack input[type="checkbox"]');

	function item(id) { return list.querySelector('[data-item="' + id + '"]'); }

	function setDone(id, done) {
		var li = item(id);
		if (!li) { return; }
		li.setAttribute('data-done', done ? '1' : '0');
		li.classList.toggle('is-done', !!done);
		var mark = li.querySelector('.ssc-checklist__mark');
		if (mark) { mark.textContent = done ? '✓' : '○'; }
		var action = li.querySelector('button.ssc-checklist__action');
		if (action) { action.hidden = !!done; }
		refresh();
	}

	function refresh() {
		var missing = [];
		Array.prototype.forEach.call(list.querySelectorAll('[data-item]'), function (li) {
			if (li.getAttribute('data-done') !== '1') {
				var label = li.querySelector('.ssc-checklist__label');
				missing.push(label ? label.textContent.trim() : li.getAttribute('data-item'));
			}
		});
		publish.disabled = missing.length > 0;
		if (hint) {
			hint.hidden = missing.length === 0;
			hint.textContent = missing.length ? (hint.getAttribute('data-prefix') || '') + ' ' + missing.join(' · ') : '';
		}
	}

	function flash(node) {
		if (!node) { return; }
		node.scrollIntoView({ behavior: 'smooth', block: 'center' });
		node.classList.remove('ssc-flash');
		void node.offsetWidth; // Restart the highlight animation.
		node.classList.add('ssc-flash');
	}

	list.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('button.ssc-checklist__action');
		if (!btn) { return; }
		if (btn.getAttribute('data-action') === 'identity') {
			var run = document.getElementById('ssc-run-identity-test');
			flash(document.getElementById('ssc-identity-test'));
			if (run && !run.disabled) { run.click(); }
		} else if (btn.getAttribute('data-action') === 'privacy') {
			flash(document.getElementById('ssc-privacy-ack'));
			if (privacy) { privacy.focus(); }
		}
	});

	document.addEventListener('ssc:launch-item', function (e) {
		if (e.detail && e.detail.id) { setDone(e.detail.id, !!e.detail.done); }
	});

	if (privacy) {
		privacy.addEventListener('change', function () { setDone('privacy', privacy.checked); });
		// Reflect the box as it is now (the browser may restore a ticked state).
		setDone('privacy', privacy.checked);
	} else {
		refresh();
	}
})();
