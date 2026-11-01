/**
 * Vazho — admin interactions.
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

	/* ---------- Provider cards (the <select> stays the source of truth) ---------- */

	(function () {
		var group = $('.ssc-provider-cards');
		var wrap = $('.ssc-provider-select');
		if (!group || !providerSelect) { return; }
		var cards = $$('[data-ssc-provider]', group);
		function paint() {
			cards.forEach(function (card) {
				var on = card.getAttribute('data-ssc-provider') === providerSelect.value;
				card.classList.toggle('is-on', on);
				card.setAttribute('aria-checked', on ? 'true' : 'false');
				card.tabIndex = on ? 0 : -1;
			});
		}
		function choose(card) {
			providerSelect.value = card.getAttribute('data-ssc-provider');
			providerSelect.dispatchEvent(new Event('change', { bubbles: true }));
			paint();
		}
		cards.forEach(function (card, i) {
			card.addEventListener('click', function () { choose(card); });
			card.addEventListener('keydown', function (e) {
				var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
				if (!step) { return; }
				if ('rtl' === window.getComputedStyle(group).direction && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) { step = -step; }
				e.preventDefault();
				var next = cards[(i + step + cards.length) % cards.length];
				next.focus();
				choose(next);
			});
		});
		providerSelect.addEventListener('change', paint);
		group.hidden = false;
		if (wrap) { wrap.classList.add('is-enhanced'); }
		paint();
	})();

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

	/**
	 * Append a blank row cloned from the list's first row (or its <template>
	 * when the list is empty) and return it.
	 */
	function addRow(btn) {
		var list = $(btn.getAttribute('data-target'));
		if (!list) { return null; }
		var first = directRows(list)[0];
		// An empty list has no row to copy: start from the blank <template> row.
		var tpl = btn.getAttribute('data-template') ? $(btn.getAttribute('data-template')) : null;
		if (!first && tpl && tpl.content) { first = tpl.content.firstElementChild; }
		if (!first) { first = list.firstElementChild; }
		if (!first) { return null; }
		var clone = first.cloneNode(true);
		$$('input[type="text"], input[type="url"], input[type="email"], textarea', clone).forEach(function (i) { i.value = ''; });
		$$('input[type="checkbox"]', clone).forEach(function (i) { i.checked = false; });
		// Identity fields must not be copied: a cloned key/id made two rows share one key.
		$$('input[type="hidden"]', clone).forEach(function (i) {
			if (/\[(key|id)\]$/.test(i.getAttribute('name') || '')) { i.value = ''; }
		});
		$$('select', clone).forEach(function (sel) { sel.selectedIndex = 0; });
		$$('.ssc-product__attrrow', clone).forEach(function (r, i) { if (i > 0) { r.remove(); } });
		// Cards: open, with their summary reset to the "new" wording.
		if ('DETAILS' === clone.tagName) { clone.open = true; }
		$$('[data-ssc-kmirror]', clone).forEach(function (node) {
			node.textContent = 'title' === node.getAttribute('data-ssc-kmirror') || 'name' === node.getAttribute('data-ssc-kmirror') ? (node.getAttribute('data-empty') || '') : '';
		});
		$$('[data-ssc-kspecs]', clone).forEach(function (node) { node.hidden = true; });
		if (clone.hasAttribute('data-type')) { clone.setAttribute('data-type', 'general'); }
		var idx = nextIndex(list);
		rewriteNames(clone, idx);
		list.appendChild(clone);
		syncListCount(list);
		var focus = $('input[type="text"], input[type="url"]', clone);
		if (focus) { focus.focus(); }
		bindRemove(clone);
		list.dispatchEvent(new CustomEvent('ssc-rows-changed', { bubbles: true }));
		return clone;
	}

	/** Lists that may be emptied show a friendly empty state through data-count. */
	function syncListCount(list) {
		if (list && list.hasAttribute('data-count')) { list.setAttribute('data-count', String(directRows(list).length)); }
	}

	function bindAddButtons() {
		$$('.ssc-ki__add, .ssc-product__add, .ssc-field__add').forEach(function (btn) {
			btn.addEventListener('click', function () { addRow(btn); });
		});
	}

	function bindRemove(scope) {
		$$('.ssc-ki__remove', scope || document).forEach(function (btn) {
			if (btn.__sscBound) { return; }
			btn.__sscBound = true;
			btn.addEventListener('click', function () {
				var row = btn.closest('.ssc-ki, .ssc-product, .ssc-fieldrow');
				if (row && row.parentElement) {
					var list = row.parentElement;
					var siblings = $$('.ssc-ki, .ssc-product, .ssc-fieldrow', list);
					// The last row stays unless the list may be empty (it then shows an empty state).
					if (list.hasAttribute('data-allow-empty') || siblings.length > 1 || list.children.length > 1) {
						row.remove();
						syncListCount(list);
						list.dispatchEvent(new CustomEvent('ssc-rows-changed', { bubbles: true }));
					}
				}
			});
		});
	}

	bindAddButtons();
	bindRemove();

	// "+ Add attribute" (delegated: product rows are added at runtime).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.ssc-product__attradd') : null;
		if (!btn) { return; }
		var product = btn.closest('.ssc-product');
		var rows = product ? $$('.ssc-product__attrrow', product) : [];
		if (!rows.length) { return; }
		var clone = rows[rows.length - 1].cloneNode(true);
		$$('input', clone).forEach(function (i) { i.value = ''; });
		rows[rows.length - 1].parentElement.appendChild(clone);
		var focus = $('input', clone);
		if (focus) { focus.focus(); }
	});

	/* ---------- Form builder: choices only for dropdown / single choice ---------- */

	function syncFieldRow(row) {
		var type = $('select', row);
		var options = $('.ssc-fieldrow__options', row);
		if (!type || !options) { return; }
		options.hidden = !(type.value === 'select' || type.value === 'radio' || type.value === 'checkboxes');
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

	/* ---------- Side-effect report form: preset vs. per-question switches ---------- */

	(function () {
		var preset = $('#adr_preset');
		var table = $('.ssc-adr-fields');
		if (!preset || !table) { return; }
		var presets = {};
		try { presets = JSON.parse(table.getAttribute('data-presets') || '{}'); } catch (e) { presets = {}; }
		// Saved per-question choices, restored when the admin returns to Custom.
		var saved = {};
		$$('tbody tr', table).forEach(function (row) {
			saved[row.getAttribute('data-key')] = { on: $('.ssc-adr-on', row).checked, req: $('.ssc-adr-req', row).checked };
		});

		function apply() {
			var mode = preset.value;
			var custom = 'custom' === mode;
			table.classList.toggle('is-preset', !custom);
			$$('tbody tr', table).forEach(function (row) {
				if (row.hasAttribute('data-locked')) { return; }
				var key = row.getAttribute('data-key');
				var on = $('.ssc-adr-on', row);
				var req = $('.ssc-adr-req', row);
				if (custom) {
					on.checked = saved[key].on;
					req.checked = saved[key].req;
				} else {
					// Shown for information; still posted, so Custom starts from here.
					on.checked = 'full' === mode || (presets[mode] || []).indexOf(key) !== -1;
				}
				[on, req].forEach(function (box) {
					box.setAttribute('aria-readonly', custom ? 'false' : 'true');
					box.tabIndex = custom ? 0 : -1;
				});
			});
		}

		table.addEventListener('click', function (e) {
			// Outside Custom mode the switches are read-only (not disabled: disabled boxes are not submitted).
			if (e.target.matches && e.target.matches('input[type="checkbox"]') && 'custom' !== preset.value) { e.preventDefault(); }
		});
		table.addEventListener('change', function (e) {
			var row = e.target.closest && e.target.closest('tr');
			if (!row || 'custom' !== preset.value) { return; }
			var key = row.getAttribute('data-key');
			saved[key] = { on: $('.ssc-adr-on', row).checked, req: $('.ssc-adr-req', row).checked };
		});
		preset.addEventListener('change', apply);
		apply();
	})();

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
			launcher_size: 'launcherSize',
			launcher_style: 'launcherStyle'
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

	/* ---------- Button position: live drawings of where it will sit ---------- */

	/*
	 * Two small screens (phone with a bottom menu, desktop) redraw as the
	 * numbers change. The phone shows the same rule the widget applies on the
	 * page: the larger of the configured distance and "clear of the bar".
	 */
	function bindPositionMocks() {
		var box = $('#ssc-posbox');
		if (!box) { return; }
		// The drawn menu bar in real pixels: 64px tall, floating 8px above the edge.
		// The widget keeps 12px clear of a bar it detects.
		var BAR_GAP = 8;
		var BAR_HEIGHT = 64;
		var CLEARANCE = 12;

		function num(id, fallback) {
			var el = document.getElementById(id);
			var n = el ? parseInt(el.value, 10) : NaN;
			return isNaN(n) || n < 0 ? fallback : n;
		}

		function draw(mock) {
			var phone = 'phone' === mock.getAttribute('data-ssc-posmock');
			var screen = $('.ssc-posmock__screen', mock);
			var dot = $('.ssc-posmock__dot', mock);
			if (!screen || !dot) { return; }
			var scale = screen.clientWidth / parseFloat(mock.getAttribute('data-vw') || '390');
			var size = num('launcher_size', 60);
			var x = phone ? num('launcher_offset_x_mobile', 18) : num('launcher_offset_x', 22);
			var y = phone ? num('launcher_offset_y_mobile', 18) : num('launcher_offset_y', 22);
			var covering = false;
			if (phone) {
				var avoid = $('#launcher_avoid_bars');
				var clear = BAR_GAP + BAR_HEIGHT + CLEARANCE;
				if (avoid && avoid.checked) {
					y = Math.max(y, clear);
				} else {
					covering = y < BAR_GAP + BAR_HEIGHT && y + size > BAR_GAP;
				}
			}
			var px = function (value) { return Math.round(value * scale) + 'px'; };
			// Physical sides, like the widget: "left" is always the left edge.
			var left = $('#position') && 'left' === $('#position').value;
			dot.style.width = dot.style.height = Math.max(8, Math.round(size * scale)) + 'px';
			dot.style.bottom = px(y);
			dot.style.left = left ? px(x) : 'auto';
			dot.style.right = left ? 'auto' : px(x);
			dot.classList.toggle('is-covering', covering);
			var bar = $('.ssc-posmock__bar', mock);
			if (bar) {
				bar.style.height = px(BAR_HEIGHT);
				bar.style.bottom = px(BAR_GAP);
			}
		}

		function redraw() { $$('[data-ssc-posmock]', box).forEach(draw); }

		['launcher_offset_x', 'launcher_offset_y', 'launcher_offset_x_mobile', 'launcher_offset_y_mobile', 'launcher_avoid_bars', 'launcher_size', 'position'].forEach(function (id) {
			var el = document.getElementById(id);
			if (!el) { return; }
			el.addEventListener('input', redraw);
			el.addEventListener('change', redraw);
		});
		$$('[data-ssc-pos-preset]', box).forEach(function (btn) {
			btn.addEventListener('click', function () {
				var field = document.getElementById('launcher_offset_y_mobile');
				if (!field) { return; }
				field.value = btn.getAttribute('data-ssc-pos-preset');
				redraw();
			});
		});
		redraw();
	}

	bindPositionMocks();

	/* ---------- Hubs: tiles that open one section at a time ---------- */

	/*
	 * A hub is a row of tiles (role=tab) and sections (role=tabpanel). Used by
	 * Business Knowledge, Settings, AI Connection and more. Without script
	 * every section stays visible (the tile row is hidden until we start).
	 *   root          [data-ssc-hub="name"] (+ data-initial)
	 *   tile          [data-ssc-hubtab="id"]
	 *   section       [data-ssc-hubpanel="id"]; data-ssc-form marks sections
	 *                 inside the page's main form (the save bar shows for them)
	 *   save bar      [data-ssc-hubsave]; hidden input [data-ssc-hubfield] carries
	 *                 the open section through a save
	 *   jump link     [data-ssc-hubgo="id"]
	 *   unsaved note  [data-ssc-hubdirty]
	 */
	function bindHub(root) {
		var nav = $('.ssc-htiles', root);
		var tabs = $$('[data-ssc-hubtab]', root);
		var panels = $$('[data-ssc-hubpanel]', root);
		var saveBar = $('[data-ssc-hubsave]', root);
		var panelField = $('[data-ssc-hubfield]', root);
		var dirtyNote = $('[data-ssc-hubdirty]', root);
		var key = 'ssc_hub_' + (root.getAttribute('data-ssc-hub') || 'page');
		var dirty = false;

		function panelById(id) {
			return panels.filter(function (p) { return p.getAttribute('data-ssc-hubpanel') === id; })[0] || null;
		}

		// A tile without a section (e.g. a module that is off) is dropped.
		tabs = tabs.filter(function (tab) {
			if (panelById(tab.getAttribute('data-ssc-hubtab'))) { return true; }
			if (tab.parentElement) { tab.parentElement.removeChild(tab); }
			return false;
		});

		function show(id) {
			var target = panelById(id) || panels[0];
			if (!target) { return; }
			id = target.getAttribute('data-ssc-hubpanel');
			tabs.forEach(function (tab) {
				var on = tab.getAttribute('data-ssc-hubtab') === id;
				tab.setAttribute('aria-selected', on ? 'true' : 'false');
				tab.tabIndex = on ? 0 : -1;
				tab.classList.toggle('is-active', on);
			});
			panels.forEach(function (panel) { panel.hidden = panel !== target; });
			if (panelField) { panelField.value = id; }
			if (saveBar) { saveBar.hidden = !target.hasAttribute('data-ssc-form'); }
			try { window.sessionStorage.setItem(key, id); } catch (e0) { /* storage may be blocked */ }
			try { window.history.replaceState(null, '', '#' + id); } catch (e) { /* the hash is a convenience only */ }
			root.dispatchEvent(new CustomEvent('ssc-hub-show', { detail: { id: id } }));
		}

		if (nav) { nav.hidden = false; }
		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () { show(tab.getAttribute('data-ssc-hubtab')); });
			tab.addEventListener('keydown', function (e) {
				var keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
				var step = keys[e.key];
				if (!step && 'Home' !== e.key && 'End' !== e.key) { return; }
				if (nav && 'rtl' === window.getComputedStyle(nav).direction && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) { step = -step; }
				var at = tabs.indexOf(tab);
				var next = 'Home' === e.key ? 0 : 'End' === e.key ? tabs.length - 1 : (at + step + tabs.length) % tabs.length;
				e.preventDefault();
				tabs[next].focus();
				show(tabs[next].getAttribute('data-ssc-hubtab'));
			});
		});
		$$('[data-ssc-hubgo]', root).forEach(function (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				show(link.getAttribute('data-ssc-hubgo'));
				if (nav && nav.scrollIntoView) { nav.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
			});
		});

		function markDirty() {
			if (dirty) { return; }
			dirty = true;
			if (dirtyNote) { dirtyNote.hidden = false; }
		}

		// Unsaved edits in the page's main form(s): a note, and a prompt before leaving.
		$$('form[data-ssc-hubform], #ssc-kform', root).forEach(function (form) {
			form.addEventListener('input', markDirty);
			form.addEventListener('change', markDirty);
			form.addEventListener('submit', function () { dirty = false; });
		});
		// A field that fails validation inside a hidden section or a folded card cannot be focused, so the
		// browser would refuse to save without saying why: open whatever holds it. ("invalid" does not bubble.)
		root.addEventListener('invalid', function (e) {
			var field = e.target;
			if (!field || !field.closest) { return; }
			var panel = field.closest('[data-ssc-hubpanel]');
			if (panel && panel.hidden) { show(panel.getAttribute('data-ssc-hubpanel')); }
			var fold = field.parentElement;
			while (fold && fold !== root) {
				if ('DETAILS' === fold.tagName) { fold.open = true; }
				fold = fold.parentElement;
			}
		}, true);
		window.addEventListener('beforeunload', function (e) {
			if (!dirty) { return undefined; }
			e.preventDefault();
			e.returnValue = '';
			return '';
		});

		/** A link to something inside a section (e.g. #mod-voice): open the section, unfold the card, scroll to it. */
		function openAnchor(id) {
			var node = id ? document.getElementById(id) : null;
			var panel = node && node.closest ? node.closest('[data-ssc-hubpanel]') : null;
			if (!panel || !root.contains(panel)) { return false; }
			show(panel.getAttribute('data-ssc-hubpanel'));
			var fold = node;
			while (fold && fold !== root) {
				if ('DETAILS' === fold.tagName) { fold.open = true; }
				fold = fold.parentElement;
			}
			window.setTimeout(function () { if (node.scrollIntoView) { node.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }, 60);
			return true;
		}

		var wanted = (window.location.hash || '').replace('#', '');
		if (!panelById(wanted)) { openAnchor(wanted); }
		if (!panelById(wanted)) {
			wanted = '';
			try { wanted = window.sessionStorage.getItem(key) || ''; } catch (e2) { wanted = ''; }
			// A remembered tab only wins when the server did not ask for a particular one.
			if (root.getAttribute('data-initial-forced') === '1' || !panelById(wanted)) { wanted = root.getAttribute('data-initial') || ''; }
		}
		if (!root.querySelector('.is-active[data-ssc-hubtab]')) {
			show(panelById(wanted) ? wanted : (root.getAttribute('data-initial') || ''));
		}
		window.addEventListener('hashchange', function () {
			var id = (window.location.hash || '').replace('#', '');
			if (panelById(id)) { show(id); } else { openAnchor(id); }
		});

		var api = { show: show, panelById: panelById, markDirty: markDirty, tabs: tabs, panels: panels };
		root.__sscHub = api;
		return api;
	}

	/* Knowledge entries: how much of the reading budget is used, and which entries the assistant will not see. Mirrors SSC_Knowledge::plan_knowledge(). */
	(function bindKnowledgeBudget() {
		var meter = $('[data-ssc-kbudget]');
		var list = $('#ssc-ki-list');
		if (!meter || !list) { return; }
		var limit = parseInt(meter.getAttribute('data-limit'), 10) || 4000;
		var fmt = meter.getAttribute('data-fmt') || '%1$s of %2$s';
		var text = $('[data-ssc-kbudget-text]', meter);
		var bar = $('.ssc-kbudget__bar span', meter);
		var warn = $('[data-ssc-kbudget-warn]', meter);
		function chars(value) { return Array.from(value.trim()).length; }
		function paint() {
			var used = 0;
			var over = 0;
			$$('.ssc-ki', list).forEach(function (row) {
				var area = $('textarea[data-ssc-kcontent]', row);
				var badge = $('[data-ssc-kstate]', row);
				var n = area ? chars(area.value) : 0;
				var state = '';
				if (n > 0) {
					if (used >= limit) { state = 'out'; }
					else if (used + n > limit) { state = 'cut'; used = limit; }
					else { used += n; }
				}
				if (state) { over++; }
				row.classList.toggle('is-unseen', state === 'out');
				if (badge) {
					badge.hidden = !state;
					badge.textContent = state ? (badge.getAttribute('data-' + state) || '') : '';
				}
			});
			var nf = new Intl.NumberFormat(document.documentElement.lang || undefined);
			if (text) { text.textContent = fmt.replace('%1$s', nf.format(used)).replace('%2$s', nf.format(limit)); }
			if (bar) { bar.style.width = Math.min(100, Math.round(100 * used / limit)) + '%'; }
			meter.classList.toggle('is-over', over > 0);
			if (warn) { warn.hidden = over === 0; }
		}
		list.addEventListener('input', paint);
		list.addEventListener('ssc-rows-changed', paint);
		paint();
	})();

	/* Wizard, knowledge step: quick-start topics and a progress ring that follows what is typed. */
	(function bindWizardKnowledge() {
		var root = $('[data-ssc-wk]');
		var list = $('#ssc-ki-list');
		var addBtn = $('.ssc-ki__add[data-target="#ssc-ki-list"]');
		if (!root || !list || !addBtn) { return; }
		var goal = parseInt(root.getAttribute('data-goal'), 10) || 3;
		var ring = $('.ssc-ring', root);
		var ringVal = ring ? $('.ssc-ring__val', ring) : null;
		var count = $('[data-ssc-wkcount]', root);
		var starters = $('.ssc-wk__starters', root);
		var template = count ? count.textContent : '';
		var counts = template.match(/\d+/g) || [];

		function rows() { return $$('.ssc-ki', list); }
		function filled() {
			return rows().filter(function (row) {
				var area = $('textarea', row);
				return area && area.value.trim() !== '';
			}).length;
		}
		function paint() {
			var n = filled();
			var pct = Math.min(100, Math.round(100 * n / goal));
			if (ring) {
				ring.style.setProperty('--p', String(pct));
				ring.classList.toggle('is-ok', pct >= 100);
				ring.setAttribute('aria-label', pct + '%');
			}
			if (ringVal) { ringVal.textContent = pct + '%'; }
			// Reuse the server-rendered sentence: it holds "N of M" as its first two numbers.
			if (count && counts.length >= 2) {
				var seen = 0;
				count.textContent = template.replace(/\d+/g, function () {
					seen += 1;
					return String(1 === seen ? Math.min(n, goal) : goal);
				});
			}
			$$('[data-ssc-wkstart]', root).forEach(function (btn) {
				var used = rows().some(function (row) {
					var title = $('input[type="text"]', row);
					return title && title.value.trim() === btn.getAttribute('data-title');
				});
				btn.classList.toggle('is-on', used);
				btn.setAttribute('aria-pressed', used ? 'true' : 'false');
			});
		}

		if (starters) {
			starters.hidden = false;
			$$('[data-ssc-wkstart]', starters).forEach(function (btn) {
				btn.addEventListener('click', function () {
					var existing = rows().filter(function (row) {
						var title = $('input[type="text"]', row);
						return title && title.value.trim() === btn.getAttribute('data-title');
					})[0];
					var row = existing || rows().filter(function (r) {
						var title = $('input[type="text"]', r);
						var area = $('textarea', r);
						return title && area && title.value.trim() === '' && area.value.trim() === '';
					})[0] || addRow(addBtn);
					if (!row) { return; }
					var type = $('select', row);
					var title = $('input[type="text"]', row);
					if (type) { type.value = btn.getAttribute('data-type'); }
					if (title && !existing) { title.value = btn.getAttribute('data-title'); }
					var area = $('textarea', row);
					if (area) { area.focus(); }
					if (row.scrollIntoView) { row.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
					paint();
				});
			});
		}
		list.addEventListener('input', paint);
		list.addEventListener('change', paint);
		list.addEventListener('ssc-rows-changed', paint);
		list.addEventListener('click', function (e) {
			if (e.target && e.target.closest && e.target.closest('.ssc-ki__remove')) { window.setTimeout(paint, 0); }
		});
		paint();
	})();

	/* Modules: filter the cards by status (the chips stay hidden without script). */
	(function bindModuleFilter() {
		var bar = $('[data-ssc-modfilter]');
		if (!bar) { return; }
		var cards = $$('[data-ssc-modstatus]');
		var groups = $$('[data-ssc-modcat]');
		var none = $('[data-ssc-modempty]');
		bar.hidden = false;
		function apply(which) {
			cards.forEach(function (card) {
				card.hidden = 'all' !== which && card.getAttribute('data-ssc-modstatus') !== which;
			});
			var shown = 0;
			groups.forEach(function (group) {
				var any = $$('[data-ssc-modstatus]', group).some(function (card) { return !card.hidden; });
				group.hidden = !any;
				if (any) { shown += 1; }
			});
			if (none) { none.hidden = shown > 0 || !groups.length; }
		}
		$$('[data-ssc-modshow]', bar).forEach(function (chip) {
			chip.addEventListener('click', function () {
				$$('[data-ssc-modshow]', bar).forEach(function (c) { c.classList.toggle('is-on', c === chip); c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
				apply(chip.getAttribute('data-ssc-modshow'));
			});
		});
	})();

	/* A "use this" button next to a field copies its suggested text into the field. */
	$$('[data-ssc-fill]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var target = document.getElementById(btn.getAttribute('data-ssc-fill'));
			if (!target) { return; }
			target.value = btn.getAttribute('data-text') || '';
			target.dispatchEvent(new Event('input', { bubbles: true }));
			target.focus();
		});
	});

	/* The brand strip on the Appearance screen follows the colour and the name as they are typed. */
	(function bindBrandStrip() {
		var strip = $('#ssc-abrand');
		var color = $('#primary_color');
		var name = $('#assistant_display_name');
		if (!strip) { return; }
		var label = $('[data-ssc-abrand="name"]', strip);
		var fallback = label ? label.textContent : '';
		if (color) { color.addEventListener('input', function () { strip.style.setProperty('--brand', color.value); }); }
		if (name && label) { name.addEventListener('input', function () { label.textContent = name.value.trim() || fallback; }); }
	})();

	$$('[data-ssc-hub]').forEach(bindHub);

	/* ---------- Business Knowledge: live progress, examples ---------- */

	/*
	 * Progress rings follow what is typed. The rules mirror SSC_Knowledge_Score
	 * (PHP), which draws the first paint.
	 */
	function bindKnowledgeHub() {
		var root = $('#ssc-kh');
		if (!root || !root.__sscHub) { return; }
		var hub = root.__sscHub;
		var tabs = hub.tabs;
		var panels = hub.panels;
		var form = $('#ssc-kform');
		var fmt = {};
		var levels = [];
		var weights = {};
		try { fmt = JSON.parse(root.getAttribute('data-fmt') || '{}'); } catch (e1) { fmt = {}; }
		try { levels = JSON.parse(root.getAttribute('data-levels') || '[]'); } catch (e2) { levels = []; }
		try { weights = JSON.parse(root.getAttribute('data-weights') || '{}'); } catch (e3) { weights = {}; }
		var extraKnowledge = '1' === root.getAttribute('data-extra');
		var panelById = hub.panelById;
		var markDirty = hub.markDirty;

		function text(template, a, b) {
			return String(template || '').replace('%1$d', a).replace('%2$d', b).replace('%d', a);
		}

		/* ----- progress ----- */

		var ratios = {};
		// Sections that are not edited here keep the ratio the server drew.
		tabs.forEach(function (tab) {
			var id = tab.getAttribute('data-ssc-hubtab');
			var ring = $('[data-ssc-kring="' + id + '"] .ssc-ring', root);
			ratios[id] = ring ? (parseFloat(ring.style.getPropertyValue('--p')) || 0) / 100 : 0;
		});

		function filled(el) { return '' !== String(el.value || '').trim(); }

		function rowsOf(panel) {
			var list = $('.ssc-klist', panel);
			return list ? $$(':scope > .ssc-ki, :scope > .ssc-product', list) : [];
		}

		function setRing(holder, percent) {
			var ring = holder ? $('.ssc-ring', holder) : null;
			if (!ring) { return; }
			ring.style.setProperty('--p', String(percent));
			var value = $('.ssc-ring__val', ring);
			if (value) { value.textContent = percent + '%'; }
			ring.classList.toggle('is-ok', percent >= 100);
			ring.classList.toggle('is-low', percent <= 0);
		}

		function measure(panel) {
			var inputs = $$('[data-ssc-score]', panel);
			var done = inputs.filter(filled).length;
			var total = inputs.length;
			var target = parseInt(panel.getAttribute('data-ssc-target') || '0', 10);
			var count = null;
			if (target) {
				count = rowsOf(panel).filter(function (row) {
					var probe = $('[data-ssc-kcontent]', row) || $('[data-ssc-kname]', row);
					return probe && filled(probe);
				}).length;
				done += Math.min(count, target);
				total += target;
			}
			return { done: done, total: total, count: count, ratio: total ? done / total : 0, fields: inputs.length ? inputs.filter(filled).length : 0 };
		}

		function statusFor(id, m) {
			if (null !== m.count) {
				var one = { facts: 'entries_one', products: 'products_one' }[id];
				var many = { facts: 'entries', products: 'products' }[id];
				if (0 === m.count) { return fmt.empty || ''; }
				return 1 === m.count ? (fmt[one] || '') : text(fmt[many], m.count);
			}
			return 0 === m.done ? (fmt.none || '') : text(fmt.fields, m.done, m.total);
		}

		function paint() {
			var counts = { facts: 0, products: 0 };
			var orgFilled = false;
			panels.forEach(function (panel) {
				if (!panel.hasAttribute('data-ssc-form')) { return; }
				var id = panel.getAttribute('data-ssc-hubpanel');
				var m = measure(panel);
				ratios[id] = m.ratio;
				setRing($('[data-ssc-kring="' + id + '"]', root), Math.round(100 * m.ratio));
				var status = $('[data-ssc-kstatus="' + id + '"]', root);
				if (status) { status.textContent = statusFor(id, m); }
				if (null !== m.count) { counts[id] = m.count; }
			});
			var org = $('#b_org_name');
			orgFilled = !!org && filled(org);
			var sum = 0;
			var earned = 0;
			Object.keys(weights).forEach(function (id) {
				sum += weights[id];
				earned += weights[id] * (ratios[id] || 0);
			});
			var percent = sum > 0 ? Math.round(100 * earned / sum) : 0;
			setRing($('[data-ssc-kring="overall"]', root), percent);
			var level = levels[0];
			levels.forEach(function (candidate) { if (percent >= candidate.min) { level = candidate; } });
			if (level) {
				var label = $('[data-ssc-klevel]', root);
				var line = $('[data-ssc-kline]', root);
				if (label) { label.textContent = level.label; }
				if (line) { line.textContent = level.line; }
			}
			var ready = orgFilled && (counts.facts > 0 || counts.products > 0 || extraKnowledge);
			var note = $('[data-ssc-kready]', root);
			if (note) {
				note.classList.toggle('is-ready', ready);
				var msg = $('span', note);
				if (msg) { msg.textContent = ready ? (fmt.ready_yes || '') : (fmt.ready_no || ''); }
			}
		}

		/* ----- cards: titles, excerpts, types ----- */

		function cardOf(node) { return node.closest ? node.closest('.ssc-kcard') : null; }

		function mirror(card, kind, value, fallbackToEmpty) {
			var node = card ? $('[data-ssc-kmirror="' + kind + '"]', card) : null;
			if (!node) { return; }
			if (fallbackToEmpty && '' === value) { value = node.getAttribute('data-empty') || ''; }
			node.textContent = value;
		}

		function specCount(card) {
			var badge = $('[data-ssc-kspecs]', card);
			if (!badge) { return; }
			var n = $$('.ssc-product__attrrow', card).filter(function (row) {
				var inputs = $$('input', row);
				return inputs.length > 1 && filled(inputs[0]) && filled(inputs[1]);
			}).length;
			badge.hidden = 0 === n;
			badge.textContent = 1 === n ? (badge.getAttribute('data-one') || '') : text(badge.getAttribute('data-many'), n);
		}

		if (form) {
			form.addEventListener('input', function (e) {
				var t = e.target;
				var card = cardOf(t);
				if (t.hasAttribute && t.hasAttribute('data-ssc-ktitle')) { mirror(card, 'title', t.value.trim(), true); }
				if (t.hasAttribute && t.hasAttribute('data-ssc-kname')) { mirror(card, 'name', t.value.trim(), true); }
				if (t.hasAttribute && t.hasAttribute('data-ssc-kcontent')) { mirror(card, 'content', t.value.trim().replace(/\s+/g, ' ').slice(0, 90), false); }
				if (card && t.closest && t.closest('.ssc-product__attrs')) { specCount(card); }
				paint();
			});
			form.addEventListener('change', function (e) {
				var t = e.target;
				if (t.hasAttribute && t.hasAttribute('data-ssc-ktype')) {
					var card = cardOf(t);
					if (card) { card.setAttribute('data-type', t.value); }
				}
			});
		}
		root.addEventListener('ssc-rows-changed', function () { markDirty(); paint(); });

		/* ----- examples ----- */

		$$('[data-ssc-kstarter]', root).forEach(function (chip) {
			chip.addEventListener('click', function () {
				var add = $('#ssc-hpanel-facts .ssc-ki__add');
				var row = add ? addRow(add) : null;
				if (!row) { return; }
				var title = $('[data-ssc-ktitle]', row);
				var content = $('[data-ssc-kcontent]', row);
				var type = $('[data-ssc-ktype]', row);
				if (title) { title.value = chip.getAttribute('data-title') || ''; }
				if (type) { type.value = chip.getAttribute('data-type') || 'general'; }
				row.setAttribute('data-type', chip.getAttribute('data-type') || 'general');
				if (content) {
					content.setAttribute('placeholder', chip.getAttribute('data-hint') || '');
					content.focus();
				}
				mirror(row, 'title', (title && title.value) || '', true);
				markDirty();
				paint();
			});
		});

		/* ----- start ----- */

		paint();
	}

	bindKnowledgeHub();

	/* ---------- Export & Import: password field follows the keys tile ---------- */

	function bindTransferTiles() {
		$$('[data-ssc-xwhen]').forEach(function (field) {
			var form = field.closest('form');
			var tile = form ? $('[data-ssc-xsection="' + field.getAttribute('data-ssc-xwhen') + '"]', form) : null;
			if (!tile) { return; }
			var input = $('input', field);
			function sync() {
				field.hidden = !tile.checked;
				if (input) {
					if (tile.checked) { input.setAttribute('required', 'required'); } else { input.removeAttribute('required'); }
				}
			}
			tile.addEventListener('change', sync);
			sync();
		});
	}

	bindTransferTiles();

	/* ---------- Copy a text area (support information) ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target && e.target.closest ? e.target.closest('[data-ssc-copy]') : null;
		if (!btn) { return; }
		var source = $(btn.getAttribute('data-ssc-copy'));
		if (!source) { return; }
		var label = btn.textContent;
		function done() {
			btn.textContent = btn.getAttribute('data-copied') || label;
			window.setTimeout(function () { btn.textContent = label; }, 1800);
		}
		source.focus();
		source.select();
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(source.value).then(done, function () {
				try { document.execCommand('copy'); done(); } catch (err) { /* the text stays selected for a manual copy */ }
			});
		} else {
			try { document.execCommand('copy'); done(); } catch (err) { /* the text stays selected for a manual copy */ }
		}
	});

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

			var sent = gatherCredentials();
			api('test-connection', sent).then(function (res) {
				btn.removeAttribute('disabled');
				// The summary at the top follows the latest test of the SAVED connection (a test of a key that is only typed in does not change it).
				var hero = $('[data-ssc-connhero]');
				var heroTitle = hero ? $('h2', hero) : null;
				var heroSub = hero ? $('.ssc-hhero__sub', hero) : null;
				var forSaved = !sent.api_key;
				if (res && res.ok) {
					out.className = 'ssc-conn-test__result is-ok';
					out.textContent = '✓ ' + (res.message || 'Connection verified');
					if (hero && forSaved && heroTitle && heroSub) {
						heroTitle.textContent = hero.getAttribute('data-ok-title') || heroTitle.textContent;
						heroSub.textContent = hero.getAttribute('data-ok-sub') || heroSub.textContent;
					}
				} else {
					out.className = 'ssc-conn-test__result is-err';
					var msg = (res && res.message) ? res.message : 'Connection failed';
					if (res && res.code) { msg += ' (' + res.code + ')'; }
					if (res && res.request_id) { msg += ' [' + res.request_id + ']'; }
					out.textContent = '✕ ' + msg;
					if (hero && forSaved && heroTitle && heroSub) {
						heroTitle.textContent = hero.getAttribute('data-fail-title') || heroTitle.textContent;
						heroSub.textContent = (res && res.message) ? res.message : '';
					}
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
