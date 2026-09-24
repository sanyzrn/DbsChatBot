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

	/* ---------- Appearance live preview (light + dark) ---------- */

	function bindPreview() {
		var panel = $('#ssc-preview-panel');
		if (!panel) { return; }

		var pv = $('#ssc-pv');
		var stage = $('#ssc-preview-stage');
		var launcher = $('#ssc-pv-launcher');
		var avatar = $('#ssc-pv-avatar');
		var overrideTheme = null; // null = follow theme_mode select

		var map = {
			primary_color: null,
			theme_mode: null,
			position: null,
			direction: null,
			assistant_display_name: null,
			avatar_url: null,
			welcome_title: null,
			welcome_text: null,
			disclaimer: null,
			font_size: null,
			window_radius: null,
			bubble_radius: null,
			user_bubble_color: null,
			bot_bubble_color: null,
			launcher_size: null
		};
		Object.keys(map).forEach(function (id) { map[id] = document.getElementById(id); });

		function val(id, fallback) {
			var el = map[id];
			if (!el) { return fallback; }
			var v = (el.value || '').trim();
			return v === '' ? fallback : v;
		}

		function resolvedTheme() {
			if (overrideTheme) { return overrideTheme; }
			var mode = val('theme_mode', panel.getAttribute('data-theme-mode') || 'light');
			if ('auto' === mode) {
				return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
			}
			return mode === 'dark' ? 'dark' : 'light';
		}

		function update() {
			if (!pv) { return; }
			var primary = val('primary_color', panel.getAttribute('data-primary') || '#b61615');
			var theme = resolvedTheme();
			var dir = val('direction', panel.getAttribute('data-dir') || 'rtl');
			if ('auto' === dir) { dir = 'rtl'; }
			var pos = val('position', panel.getAttribute('data-pos') || 'right');
			var name = val('assistant_display_name', panel.getAttribute('data-asst-name') || '');
			var org = panel.getAttribute('data-org-name') || '';
			var wtitle = val('welcome_title', panel.getAttribute('data-w-title') || '');
			var wtext = val('welcome_text', panel.getAttribute('data-w-text') || '');
			var foot = val('disclaimer', panel.getAttribute('data-disclaimer') || '');
			var avatarUrl = val('avatar_url', '');
			var fontSize = val('font_size', '14');
			var winR = val('window_radius', '24');
			var bubR = val('bubble_radius', '16');
			var userBub = val('user_bubble_color', primary);
			var botBub = val('bot_bubble_color', '');
			var launcherSize = val('launcher_size', '60');

			pv.setAttribute('data-theme', theme);
			pv.setAttribute('dir', dir);
			pv.style.setProperty('--pv-primary', primary);
			pv.style.setProperty('--pv-font-size', fontSize + 'px');
			pv.style.setProperty('--pv-win-radius', winR + 'px');
			pv.style.setProperty('--pv-bubble-radius', bubR + 'px');
			pv.style.setProperty('--pv-user-bubble', userBub || primary);
			if (botBub) {
				pv.style.setProperty('--pv-bot-bubble', botBub);
			} else {
				pv.style.removeProperty('--pv-bot-bubble');
			}

			if (stage) { stage.setAttribute('data-preview-theme', theme); }

			if (launcher) {
				launcher.setAttribute('data-pos', pos);
				launcher.style.setProperty('--pv-primary', primary);
				var px = Math.round(parseInt(launcherSize, 10) || 60);
				// Scale mock launcher to fit the 340px panel.
				var mock = Math.max(40, Math.min(56, Math.round(px * 0.85)));
				launcher.style.width = mock + 'px';
				launcher.style.height = mock + 'px';
			}

			if (avatar) {
				if (avatarUrl) {
					avatar.classList.add('has-img');
					avatar.innerHTML = '<img src="" alt="" />';
					var img = avatar.querySelector('img');
					img.src = avatarUrl;
				} else {
					avatar.classList.remove('has-img');
					avatar.innerHTML = '';
					avatar.style.background = primary;
				}
			}

			var nameEl = $('#ssc-pv-name');
			if (nameEl) { nameEl.textContent = name; }
			var statusEl = $('#ssc-pv-status');
			if (statusEl) { statusEl.textContent = org; }
			var wt = $('#ssc-pv-wtitle');
			if (wt) { wt.textContent = wtitle; }
			var wx = $('#ssc-pv-wtext');
			if (wx) { wx.textContent = wtext; }
			var ft = $('#ssc-pv-foot');
			if (ft) { ft.textContent = foot; }
		}

		// Theme toggle buttons (preview only — does not change the saved setting).
		$$('.ssc-preview-toggle [data-pv-theme]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				overrideTheme = btn.getAttribute('data-pv-theme');
				$$('.ssc-preview-toggle [data-pv-theme]').forEach(function (b) {
					b.classList.toggle('is-on', b === btn);
				});
				update();
			});
		});

		// Sync toggle to the theme_mode select when the user changes the real setting.
		if (map.theme_mode) {
			map.theme_mode.addEventListener('change', function () {
				overrideTheme = null;
				var mode = map.theme_mode.value;
				var want = ('dark' === mode) ? 'dark' : 'light';
				$$('.ssc-preview-toggle [data-pv-theme]').forEach(function (b) {
					b.classList.toggle('is-on', b.getAttribute('data-pv-theme') === want);
				});
				update();
			});
		}

		Object.keys(map).forEach(function (id) {
			var el = map[id];
			if (!el) { return; }
			el.addEventListener('input', update);
			el.addEventListener('change', update);
		});

		// Init toggle state from the saved theme. 'auto' keeps following the OS
		// preference (overrideTheme stays null) so the preview matches reality.
		(function initToggle() {
			var mode = panel.getAttribute('data-theme-mode') || 'light';
			overrideTheme = ('auto' === mode) ? null : (('dark' === mode) ? 'dark' : 'light');
			var want = resolvedTheme();
			$$('.ssc-preview-toggle [data-pv-theme]').forEach(function (b) {
				b.classList.toggle('is-on', b.getAttribute('data-pv-theme') === want);
			});
		})();

		update();
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
				} else {
					html += '<div class="ssc-notice ssc-notice--error">' + esc(res && res.message ? res.message : 'Test failed') + '</div>';
				}
				if (res && res.reply) {
					html += '<blockquote>' + esc(res.reply) + '</blockquote>';
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
