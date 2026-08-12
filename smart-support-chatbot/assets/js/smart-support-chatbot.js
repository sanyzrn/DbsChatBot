/**
 * دستیار هوشمند گفتگو — منطق فرانت‌اند (وانیلا جاوااسکریپت).
 * معماری تک‌صفحه‌ای گفتگو‌محور: همه‌چیز در یک رشته چت (حباب‌ها + چیپس گزینه‌ها + کارت فرم + ورودی ثابت).
 */
( function () {
	'use strict';

	var cfg = window.SSCChatbotConfig || {};
	if ( ! cfg.ajaxUrl ) {
		return;
	}

	/* ---------- آیکون‌ها (Lucide, inline SVG) ---------- */
	function svg( paths, size ) {
		size = size || 24;
		// آیکون‌ها تزئینی‌اند: از درخت دسترس‌پذیری پنهان می‌شوند تا صفحه‌خوان آن‌ها را نخواند.
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size +
			'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
			'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + paths + '</svg>';
	}
	var ICON = {
		message: function ( s ) { return svg( '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>', s ); },
		x: function ( s ) { return svg( '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>', s ); },
		bot: function ( s ) { return svg( '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>', s ); },
		send: function ( s ) { return svg( '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>', s ); },
		loader: function ( s ) { return svg( '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>', s ); },
		sparkles: function ( s ) { return svg( '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>', s ); },
		user: function ( s ) { return svg( '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>', s ); },
		package: function ( s ) { return svg( '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M3.3 7 12 12l8.7-5"/><path d="M12 22V12"/>', s ); },
		chevronLeft: function ( s ) { return svg( '<path d="m15 18-6-6 6-6"/>', s ); },
		arrowRight: function ( s ) { return svg( '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>', s ); },
		building: function ( s ) { return svg( '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>', s ); },
		activity: function ( s ) { return svg( '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>', s ); },
		headphones: function ( s ) { return svg( '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zm0 0a9 9 0 0 1 18 0m0 0v3a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2z"/>', s ); },
		check: function ( s ) { return svg( '<path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/>', s ); },
		phone: function ( s ) { return svg( '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/>', s ); },
		fileText: function ( s ) { return svg( '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>', s ); },
		refresh: function ( s ) { return svg( '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/>', s ); },
		mic: function ( s ) { return svg( '<path d="M12 19v3"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><rect x="9" y="2" width="6" height="13" rx="3"/>', s ); },
		volume: function ( s ) { return svg( '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="M16 9a5 5 0 0 1 0 6"/><path d="M19.364 18.364a9 9 0 0 0 0-12.728"/>', s ); },
		stopCircle: function ( s ) { return svg( '<circle cx="12" cy="12" r="10"/><rect x="9" y="9" width="6" height="6" rx="1"/>', s ); },
		star: function ( s ) { return svg( '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.12 2.12 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.12 2.12 0 0 0-1.973 0L6.69 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.453 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16z"/>', s ); },
		copy: function ( s ) { return svg( '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>', s ); },
		alert: function ( s ) { return svg( '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>', s ); }
	};

	/* ---------- پیکربندی ---------- */
	var products    = ( cfg.products || [] ).filter( function ( p ) { return p && p.id; } );
	var companyInfo = { id: cfg.companyId || 'company', name: cfg.companyName || '' };
	var quickReplies = ( cfg.quickReplies || [] ).filter( function ( q ) { return q && q.label && q.question; } );
	var adrOptions  = cfg.adrOptions || { severity: [], outcome: [], reporter_type: [] };
	var labels      = cfg.labels || {};
	var formFields  = ( cfg.formFields || [] ).filter( function ( f ) { return f && f.key && f.label; } );
	var show        = cfg.show || { company: true, products: true, adr: true, consult: true };
	var i18n        = cfg.i18n || {};
	function t( key, fallback ) { return ( i18n && i18n[ key ] ) ? i18n[ key ] : fallback; }

	// شناسهٔ پایدار نشست (برای محدودیت per-session — مستقل از IP).
	var clientId = ( function () {
		try {
			var k = 'nfx_cid', v = localStorage.getItem( k );
			if ( ! v ) {
				v = 'c' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 10 );
				localStorage.setItem( k, v );
			}
			return v;
		} catch ( e ) { return ''; }
	} )();

	// قابلیت‌های صوتی (Web Speech API) — فقط در مرورگرهای پشتیبان.
	var SpeechRec   = window.SpeechRecognition || window.webkitSpeechRecognition || null;
	var canSpeak    = ( 'speechSynthesis' in window ) && !! window.SpeechSynthesisUtterance;
	var voiceIn     = !! ( cfg.voiceEnabled && SpeechRec );
	var voiceOut    = !! ( cfg.voiceEnabled && canSpeak );

	/* ---------- وضعیت (تک‌رشته‌ای) ---------- */
	var state = {
		isOpen: false,
		started: false,
		items: [],            // {kind:'bot'|'user'|'form'|'success', content, noHistory, ...}
		chips: [],            // گزینه‌های فعال فعلی
		selectedProduct: null, // محصول فعال برای گفتگوی AI (null = عمومی)
		isLoading: false,
		form: emptyForm()
	};

	function emptyForm() {
		return {
			kind: '', productName: '', hp: '',
			name: '', phone: '', description: '',
			reporterType: '', severity: '', outcome: '', batchNumber: '', concomitantDrugs: '',
			extra: {}
		};
	}

	/* ---------- ابزارها ---------- */
	function el( tag, cls, html ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( html != null ) { n.innerHTML = html; }
		return n;
	}

	function escapeHtml( str ) {
		var d = document.createElement( 'div' );
		d.textContent = str == null ? '' : str;
		return d.innerHTML;
	}

	// تبدیل عناصر درون‌خطی Markdown (روی متنِ از قبل escape‌شده).
	function mdInline( s ) {
		// کد درون‌خطی.
		s = s.replace( /`([^`]+)`/g, function ( m, c ) { return '<code>' + c + '</code>'; } );
		// لینک [متن](آدرس) — فقط http/https/mailto/tel.
		s = s.replace( /\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+|tel:[^\s)]+)\)/g, function ( m, t, u ) {
			return '<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + t + '</a>';
		} );
		// پررنگ.
		s = s.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
		s = s.replace( /__([^_]+)__/g, '<strong>$1</strong>' );
		// مورب.
		s = s.replace( /(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>' );
		return s;
	}

	// رندر Markdown کامل (تیتر، لیست، نقل‌قول، خط جداکننده، پاراگراف) با escape امن.
	function boldify( raw ) {
		var text = escapeHtml( raw == null ? '' : String( raw ) );
		var lines = text.split( /\r?\n/ );
		var html = '';
		var para = [];
		var listType = null;
		function flushPara() {
			if ( para.length ) {
				html += '<p>' + para.map( mdInline ).join( '<br>' ) + '</p>';
				para = [];
			}
		}
		function closeList() {
			if ( listType ) { html += '</' + listType + '>'; listType = null; }
		}
		for ( var i = 0; i < lines.length; i++ ) {
			var t = lines[ i ].trim();
			if ( t === '' ) { flushPara(); closeList(); continue; }
			if ( /^(-{3,}|_{3,}|\*{3,})$/.test( t ) ) { flushPara(); closeList(); html += '<hr>'; continue; }
			var h = t.match( /^(#{1,6})\s+(.*)$/ );
			if ( h ) { flushPara(); closeList(); html += '<div class="nfx-md-h nfx-md-h' + Math.min( h[ 1 ].length, 4 ) + '">' + mdInline( h[ 2 ] ) + '</div>'; continue; }
			if ( /^>\s?/.test( t ) ) { flushPara(); closeList(); html += '<blockquote>' + mdInline( t.replace( /^>\s?/, '' ) ) + '</blockquote>'; continue; }
			var ul = t.match( /^[-*+]\s+(.*)$/ );
			if ( ul ) { flushPara(); if ( listType !== 'ul' ) { closeList(); html += '<ul>'; listType = 'ul'; } html += '<li>' + mdInline( ul[ 1 ] ) + '</li>'; continue; }
			var ol = t.match( /^\d+[.)]\s+(.*)$/ );
			if ( ol ) { flushPara(); if ( listType !== 'ol' ) { closeList(); html += '<ol>'; listType = 'ol'; } html += '<li>' + mdInline( ol[ 1 ] ) + '</li>'; continue; }
			closeList();
			para.push( t );
		}
		flushPara();
		closeList();
		return html;
	}

	var toastTimer = null;
	function toast( msg, isError ) {
		var t = document.getElementById( 'nfx-toast' );
		if ( ! t ) {
			t = el( 'div', 'nfx-toast' );
			t.id = 'nfx-toast';
			document.body.appendChild( t );
		}
		t.className = 'nfx-toast' + ( isError ? ' nfx-toast--error' : '' );
		t.textContent = msg;
		void t.offsetWidth;
		t.classList.add( 'is-show' );
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () { t.classList.remove( 'is-show' ); }, 4000 );
	}

	// نگاشت اکشن‌های قدیمی admin-ajax به مسیرهای REST.
	var REST_ROUTE = {
		ssc_chatbot_chat: 'chat',
		ssc_chatbot_submit: 'submit',
		ssc_chatbot_feedback: 'feedback',
		ssc_chatbot_suggest: 'suggest',
		ssc_chatbot_csat: 'csat'
	};

	function parseResponse( r ) {
		return r.text().then( function ( txt ) {
			var json = null;
			try { json = JSON.parse( txt ); } catch ( e ) { json = null; }
			// خطای REST شکل { code, message, data:{status} } دارد؛ به شکل مشترک AJAX تبدیل می‌شود.
			if ( json && ! r.ok && json.message && ! json.data ) {
				json = { success: false, data: { message: json.message } };
			} else if ( json && ! r.ok && json.message && json.data && json.data.status ) {
				json = { success: false, data: { message: json.message } };
			}
			return { ok: r.ok, status: r.status, json: json, raw: txt };
		} );
	}

	// مسیر قدیمی admin-ajax — به‌عنوان fallback وقتی REST در دسترس نیست
	// (برخی سایت‌ها REST را با افزونهٔ امنیتی می‌بندند).
	function ajaxLegacy( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		body.append( 'cid', clientId );
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		} ).then( parseResponse );
	}

	function ajax( action, data ) {
		var route = REST_ROUTE[ action ];
		if ( ! cfg.restUrl || ! route ) {
			return ajaxLegacy( action, data );
		}
		var body = new URLSearchParams();
		body.append( 'cid', clientId );
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );

		return fetch( cfg.restUrl + route, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-WP-Nonce': cfg.restNonce
			},
			body: body
		} ).then( function ( r ) {
			// اگر REST مسدود یا در دسترس نباشد، یک‌بار به admin-ajax برمی‌گردیم.
			if ( 404 === r.status || 501 === r.status ) {
				return ajaxLegacy( action, data );
			}
			return parseResponse( r );
		} ).catch( function () {
			return ajaxLegacy( action, data );
		} );
	}

	function errorMessage( res ) {
		if ( res && res.json && res.json.data && res.json.data.message ) {
			return res.json.data.message;
		}
		if ( res && ( res.raw === '-1' || res.raw === '0' || res.status === 403 ) ) {
			return t( 'sessionExpired', 'نشست شما منقضی شده است. لطفاً صفحه را تازه‌سازی (Refresh) کنید و دوباره تلاش کنید.' );
		}
		return t( 'connectionError', 'خطا در ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کنید و دوباره تلاش کنید.' );
	}

	function productName( id ) {
		if ( id === companyInfo.id ) { return companyInfo.name; }
		var p = products.filter( function ( x ) { return x.id === id; } )[ 0 ];
		return p ? p.name : '';
	}
	function productById( id ) {
		return products.filter( function ( x ) { return x.id === id; } )[ 0 ];
	}

	/* ---------- ریشه و ظاهر ---------- */
	var root = document.getElementById( 'smart-support-chatbot-root' );
	if ( ! root ) {
		root = el( 'div', 'nfx-root' );
		root.id = 'smart-support-chatbot-root';
		root.setAttribute( 'dir', 'rtl' );
		document.body.appendChild( root );
	}
	root.classList.add( cfg.position === 'left' ? 'nfx-pos-left' : 'nfx-pos-right' );
	applyTheme();
	applyColors();

	function applyTheme() {
		var mode = cfg.themeMode || 'light';
		if ( mode === 'dark' ) {
			root.setAttribute( 'data-theme', 'dark' );
		} else if ( mode === 'auto' ) {
			root.setAttribute( 'data-theme', document.documentElement.getAttribute( 'data-theme' ) === 'dark' ? 'dark' : 'light' );
		} else {
			root.setAttribute( 'data-theme', 'light' );
		}
	}
	function applyColors() {
		if ( cfg.primaryColor ) { root.style.setProperty( '--nfx-primary', cfg.primaryColor ); }
		if ( cfg.primaryHover ) { root.style.setProperty( '--nfx-primary-hover', cfg.primaryHover ); }
		if ( cfg.buttonSize ) { root.style.setProperty( '--nfx-btn-size', parseInt( cfg.buttonSize, 10 ) + 'px' ); }
		if ( cfg.iconSize ) { root.style.setProperty( '--nfx-icon-size', parseInt( cfg.iconSize, 10 ) + 'px' ); }
		if ( cfg.buttonRadius != null && cfg.buttonRadius !== '' ) { root.style.setProperty( '--nfx-btn-radius', parseInt( cfg.buttonRadius, 10 ) + '%' ); }
		// فونت و استایل پنجره.
		if ( cfg.fontStack ) { root.style.setProperty( '--nfx-font', cfg.fontStack ); }
		if ( cfg.fontSize ) { root.style.setProperty( '--nfx-font-size', parseInt( cfg.fontSize, 10 ) + 'px' ); }
		if ( cfg.windowWidth ) { root.style.setProperty( '--nfx-win-width', parseInt( cfg.windowWidth, 10 ) + 'px' ); }
		if ( cfg.windowRadius != null && cfg.windowRadius !== '' ) { root.style.setProperty( '--nfx-win-radius', parseInt( cfg.windowRadius, 10 ) + 'px' ); }
		if ( cfg.bubbleRadius != null && cfg.bubbleRadius !== '' ) { root.style.setProperty( '--nfx-bubble-radius', parseInt( cfg.bubbleRadius, 10 ) + 'px' ); }
		if ( cfg.userBubble ) { root.style.setProperty( '--nfx-user-bubble', cfg.userBubble ); }
		if ( cfg.botBubble ) { root.style.setProperty( '--nfx-bot-bubble', cfg.botBubble ); }
	}
	var iconPx = parseInt( cfg.iconSize, 10 ) || 28;
	// عنصری که پیش از باز شدن دیالوگ فوکوس داشت (برای بازگرداندن فوکوس هنگام بستن).
	var lastFocused = null;

	/* ---------- اسکلت ---------- */
	var WIN_ID = 'nfx-window-' + Math.random().toString( 36 ).slice( 2, 8 );
	var TITLE_ID = WIN_ID + '-title';

	var toggle = el( 'button', 'nfx-toggle' );
	toggle.type = 'button';
	toggle.setAttribute( 'aria-label', t( 'openChat', 'باز کردن گفتگو' ) );
	toggle.setAttribute( 'aria-expanded', 'false' );
	toggle.setAttribute( 'aria-controls', WIN_ID );
	root.appendChild( toggle );

	var win = el( 'div', 'nfx-window is-closed' );
	// معناشناسی دیالوگ: صفحه‌خوان باید بداند این یک پنجرهٔ گفتگوی مودال است.
	win.id = WIN_ID;
	win.setAttribute( 'role', 'dialog' );
	win.setAttribute( 'aria-modal', 'true' );
	win.setAttribute( 'aria-labelledby', TITLE_ID );
	win.setAttribute( 'aria-hidden', 'true' );
	root.appendChild( win );

	toggle.addEventListener( 'click', function () {
		state.isOpen = ! state.isOpen;
		renderToggle();
		win.classList.toggle( 'is-open', state.isOpen );
		win.classList.toggle( 'is-closed', ! state.isOpen );
		win.setAttribute( 'aria-hidden', state.isOpen ? 'false' : 'true' );
		toggle.setAttribute( 'aria-expanded', state.isOpen ? 'true' : 'false' );
		if ( state.isOpen ) {
			lastFocused = document.activeElement;
			if ( state.proactiveDismiss ) { state.proactiveDismiss(); }
			// ابتدا تلاش برای ادامهٔ گفتگوی قبلی؛ در غیر این صورت شروع تازه.
			if ( ! state.started && ! restoreThread() ) { startConversation(); }
			renderWindow();
			focusFirst();
		}
	} );

	/* ---------- مدیریت فوکوس (الگوی ARIA Dialog) ---------- */
	var FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	function focusables() {
		return Array.prototype.filter.call(
			win.querySelectorAll( FOCUSABLE ),
			function ( n ) { return n.offsetParent !== null || n === document.activeElement; }
		);
	}

	// با باز شدن، فوکوس وارد پنجره می‌شود تا کاربر کیبورد/صفحه‌خوان گم نشود.
	function focusFirst() {
		setTimeout( function () {
			var list = focusables();
			if ( list.length ) {
				try { list[ 0 ].focus( { preventScroll: true } ); } catch ( e ) { list[ 0 ].focus(); }
			}
		}, 60 );
	}

	// تلهٔ فوکوس: Tab نباید از دیالوگ مودال بیرون برود.
	win.addEventListener( 'keydown', function ( e ) {
		if ( 'Tab' !== e.key || ! state.isOpen ) { return; }
		var list = focusables();
		if ( ! list.length ) { return; }
		var first = list[ 0 ];
		var last  = list[ list.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	} );

	function renderToggle() {
		toggle.classList.toggle( 'is-open', state.isOpen );
		toggle.setAttribute( 'aria-label', state.isOpen ? t( 'closeChat', 'بستن گفتگو' ) : t( 'openChat', 'باز کردن گفتگو' ) );
		if ( state.isOpen ) {
			toggle.innerHTML = ICON.x( iconPx );
		} else if ( cfg.buttonIconUrl ) {
			toggle.innerHTML =
				'<img src="' + escapeHtml( cfg.buttonIconUrl ) + '" alt="" class="nfx-toggle__img" />' +
				'<span class="nfx-toggle__dot"><span></span><span></span></span>';
		} else {
			toggle.innerHTML =
				'<span class="nfx-toggle__ping">' + ICON.message( iconPx ) + '</span>' +
				ICON.message( iconPx ) +
				'<span class="nfx-toggle__dot"><span></span><span></span></span>';
		}
	}
	renderToggle();

	function closeChat() {
		if ( ! state.isOpen ) { return; }
		state.isOpen = false;
		renderToggle();
		win.classList.add( 'is-closed' );
		win.classList.remove( 'is-open' );
		win.setAttribute( 'aria-hidden', 'true' );
		toggle.setAttribute( 'aria-expanded', 'false' );
		// بازگرداندن فوکوس به جایی که کاربر از آن آمده بود (الزام الگوی دیالوگ).
		var back = lastFocused && document.contains( lastFocused ) ? lastFocused : toggle;
		lastFocused = null;
		try { back.focus( { preventScroll: true } ); } catch ( e ) { try { back.focus(); } catch ( e2 ) {} }
		// توقف خواندن صوتی در حال اجرا.
		if ( voiceOut ) { try { window.speechSynthesis.cancel(); resetSpeakBtn(); } catch ( e ) {} }
	}

	// بستن با کلیک بیرون از چت‌بات.
	// نکته: در فاز capture بررسی می‌شود تا «قبل از» رندر دوبارهٔ پنجره اجرا شود؛
	// در غیر این صورت عنصرِ کلیک‌شده با re-render از DOM حذف شده و اشتباهاً «بیرون» تشخیص داده می‌شود.
	document.addEventListener( 'click', function ( e ) {
		if ( state.isOpen && ! root.contains( e.target ) ) {
			closeChat();
		}
	}, true );

	// بستن با کلید Escape.
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) { closeChat(); }
	} );

	/* ---------- پایداری گفتگو بین صفحات ---------- */
	// پیش‌تر با هر ناوبری، کل گفتگو پاک می‌شد. اکنون رشتهٔ گفتگو در sessionStorage
	// نگه داشته می‌شود (فقط تا پایان نشست مرورگر و با انقضای زمانی).
	var STORE_KEY = 'nfx_thread_v1';
	var STORE_TTL = 2 * 60 * 60 * 1000; // ۲ ساعت.
	var STORE_MAX = 50;                 // سقف پیام‌های ذخیره‌شده.

	function saveThread() {
		try {
			// آیتم‌های گذرا ذخیره نمی‌شوند: کارت فرم (نیمه‌پرشده) و نظرسنجی.
			var items = state.items.filter( function ( it ) {
				return it.kind !== 'form' && it.kind !== 'csat';
			} ).slice( -STORE_MAX ).map( function ( it ) {
				return {
					kind: it.kind,
					content: it.content,
					noHistory: !! it.noHistory,
					logId: it.logId || 0,
					logToken: it.logToken || '',
					rated: !! it.rated,
					userQuestion: it.userQuestion || '',
					product: it.kind === 'product' ? it.product : undefined,
					seen: true // پیام‌های بازیابی‌شده دوباره انیمیشن ورود نمی‌گیرند.
				};
			} );
			if ( ! items.length ) { sessionStorage.removeItem( STORE_KEY ); return; }
			sessionStorage.setItem( STORE_KEY, JSON.stringify( {
				at: Date.now(),
				product: state.selectedProduct,
				csatDone: !! state.csatDone,
				items: items
			} ) );
		} catch ( e ) { /* حافظه پر یا غیرفعال — پایداری اختیاری است. */ }
	}

	function clearThread() {
		try { sessionStorage.removeItem( STORE_KEY ); } catch ( e ) {}
	}

	// تلاش برای بازیابی گفتگوی قبلی. در صورت موفقیت true برمی‌گرداند.
	function restoreThread() {
		try {
			var raw = sessionStorage.getItem( STORE_KEY );
			if ( ! raw ) { return false; }
			var data = JSON.parse( raw );
			if ( ! data || ! data.items || ! data.items.length ) { return false; }
			if ( ! data.at || ( Date.now() - data.at ) > STORE_TTL ) { clearThread(); return false; }

			state.items = data.items;
			state.selectedProduct = data.product || null;
			state.csatDone = !! data.csatDone;
			state.started = true;
			state.form = emptyForm();

			// چیپس‌ها تابع دارند و ذخیره نمی‌شوند؛ متناسب با زمینه بازسازی می‌شوند.
			if ( state.selectedProduct && state.selectedProduct !== companyInfo.id ) {
				setProductQuickReplies();
			} else {
				state.chips = [];
			}
			return true;
		} catch ( e ) {
			clearThread();
			return false;
		}
	}

	/* ---------- پیام دعوت هوشمند ---------- */
	( function setupProactive() {
		if ( ! cfg.proactiveEnabled ) { return; }
		try { if ( sessionStorage.getItem( 'nfx_proactive' ) === 'dismissed' ) { return; } } catch ( e ) {}
		var shown = false, autoTimer = null, outsideHandler = null;
		function dismiss() {
			try { sessionStorage.setItem( 'nfx_proactive', 'dismissed' ); } catch ( e ) {}
			if ( autoTimer ) { clearTimeout( autoTimer ); autoTimer = null; }
			if ( outsideHandler ) { document.removeEventListener( 'click', outsideHandler, true ); outsideHandler = null; }
			var elx = state.proactiveEl;
			state.proactiveEl = null;
			if ( elx ) {
				elx.classList.remove( 'is-show' );
				setTimeout( function () { if ( elx && elx.parentNode ) { elx.parentNode.removeChild( elx ); } }, 300 );
			}
		}
		state.proactiveDismiss = dismiss;
		function showTeaser() {
			if ( shown || state.isOpen ) { return; }
			shown = true;
			var tip = el( 'div', 'nfx-proactive' );
			tip.innerHTML = '<span class="nfx-proactive__text">' + escapeHtml( cfg.proactiveText || 'سوالی دارید؟' ) + '</span>';
			tip.addEventListener( 'click', function () { dismiss(); toggle.click(); } );
			root.appendChild( tip );
			requestAnimationFrame( function () { tip.classList.add( 'is-show' ); } );
			state.proactiveEl = tip;
			// بسته‌شدن خودکار پس از چند ثانیه.
			autoTimer = setTimeout( dismiss, 8000 );
			// بستن با کلیک در هر جای دیگر صفحه.
			outsideHandler = function ( e ) {
				if ( state.proactiveEl && ! state.proactiveEl.contains( e.target ) ) { dismiss(); }
			};
			setTimeout( function () { if ( outsideHandler ) { document.addEventListener( 'click', outsideHandler, true ); } }, 50 );
		}
		var delay = Math.max( 2, parseInt( cfg.proactiveDelay, 10 ) || 12 ) * 1000;
		setTimeout( showTeaser, delay );
		// قصد خروج (دسکتاپ).
		document.addEventListener( 'mouseout', function ( e ) {
			if ( ! e.relatedTarget && e.clientY <= 0 ) { showTeaser(); }
		} );
	} )();

	/* ---------- افزودن آیتم به رشته ---------- */
	function pushBot( content, opts ) {
		opts = opts || {};
		state.items.push( { kind: 'bot', content: content, noHistory: ! ! opts.noHistory } );
	}
	function pushUser( content, opts ) {
		opts = opts || {};
		state.items.push( { kind: 'user', content: content, noHistory: ! ! opts.noHistory } );
	}

	/* ---------- جریان گفتگو ---------- */
	function startConversation() {
		state.started = true;
		state.items = [];
		state.selectedProduct = null;
		state.csatDone = false;
		state.form = emptyForm();
		clearThread(); // «شروع دوباره» یعنی گفتگوی ذخیره‌شده هم پاک شود.
		var wtext = stripTags( ( cfg.welcomeText || 'چطور می‌تونم کمکتون کنم؟' ).replace( /<br\s*\/?>/gi, '\n' ) );
		pushBot( ( cfg.welcomeTitle ? cfg.welcomeTitle + '\n' : '' ) + wtext, { noHistory: true } );
		showMainOptions();
	}

	function stripTags( s ) {
		var d = document.createElement( 'div' );
		d.innerHTML = s;
		return d.textContent || d.innerText || '';
	}

	function showMainOptions() {
		var chips = [];
		if ( show.company ) {
			chips.push( chip( 'blue', ICON.building( 18 ), labels.companyTitle || 'سوال در مورد شرکت', chooseCompany ) );
		}
		if ( show.products ) {
			chips.push( chip( 'green', ICON.package( 18 ), labels.productsTitle || 'سوال در مورد محصولات', chooseProducts ) );
		}
		if ( show.adr ) {
			chips.push( chip( 'red', ICON.activity( 18 ), labels.adrTitle || 'ثبت عوارض', chooseAdr ) );
		}
		if ( show.consult ) {
			chips.push( chip( 'purple', ICON.headphones( 18 ), labels.consultTitle || 'درخواست مشاوره', chooseConsult ) );
		}
		state.chips = chips;
	}

	function chip( color, icon, label, onClick ) {
		return { color: color, icon: icon, label: label, onClick: onClick };
	}

	function chooseCompany() {
		state.chips = [];
		pushUser( labels.companyTitle || 'سوال در مورد شرکت', { noHistory: true } );
		state.selectedProduct = companyInfo.id;
		pushBot( 'بسیار خب! در مورد **' + ( companyInfo.name || 'شرکت' ) + '** چه سوالی دارید؟', { noHistory: true } );
		render();
	}

	function chooseProducts() {
		state.chips = [];
		pushUser( labels.productsTitle || 'سوال در مورد محصولات', { noHistory: true } );
		pushBot( 'درباره‌ی کدام محصول سوال دارید؟', { noHistory: true } );
		state.chips = products.map( function ( p ) {
			return chip( 'plain', ICON.package( 18 ), p.name, function () { selectProduct( p.id ); } );
		} );
		render();
	}

	function selectProduct( id ) {
		state.chips = [];
		pushUser( productName( id ), { noHistory: true } );
		state.selectedProduct = id;
		var p = productById( id );
		if ( p && ( p.image || p.summary || p.brochure ) ) {
			state.items.push( { kind: 'product', product: p, noHistory: true } );
		}
		pushBot( 'بسیار خب! در مورد **' + productName( id ) + '** چه سوالی دارید؟ 💊', { noHistory: true } );
		setProductQuickReplies();
		render();
	}

	function setProductQuickReplies() {
		if ( ! cfg.quickRepliesEnabled ) { state.chips = []; return; }
		var prod = productById( state.selectedProduct );
		var chips = quickReplies.map( function ( qr ) {
			return chip( 'pill', '', qr.label, function () { sendMessage( qr.question ); } );
		} );
		if ( prod && prod.brochure ) {
			chips.push( chip( 'brochure', ICON.fileText( 13 ), cfg.brochureLabel || 'مشاهده بروشور', function () {
				window.open( prod.brochure, '_blank', 'noopener' );
			} ) );
		}
		state.chips = chips;
	}

	// چیپس‌های پیگیری هوشمند (سوالات مرتبط از بانک).
	function setSuggestionChips( list ) {
		var chips = list.map( function ( q ) {
			return chip( 'pill', '', q, function () { sendMessage( q ); } );
		} );
		var prod = productById( state.selectedProduct );
		if ( prod && prod.brochure ) {
			chips.push( chip( 'brochure', ICON.fileText( 13 ), cfg.brochureLabel || 'مشاهده بروشور', function () {
				window.open( prod.brochure, '_blank', 'noopener' );
			} ) );
		}
		state.chips = chips;
	}

	// پیشنهاد واگذاری به کارشناس انسانی.
	function offerHandoff() {
		if ( cfg.handoffText ) { pushBot( cfg.handoffText, { noHistory: true } ); }
		state.chips = [ chip( 'purple', ICON.headphones( 18 ), t( 'handoffBtn', 'گفتگو با کارشناس انسانی' ), function () { chooseConsult(); } ) ];
	}

	function chooseAdr() {
		state.chips = [];
		pushUser( labels.adrTitle || 'ثبت عوارض', { noHistory: true } );
		pushBot( 'عارضه مربوط به کدام دارو بوده است؟', { noHistory: true } );
		state.chips = products.map( function ( p ) {
			return chip( 'plain', ICON.activity( 18 ), p.name, function () { openAdrForm( p.id ); } );
		} );
		render();
	}

	function openAdrForm( id ) {
		state.chips = [];
		pushUser( productName( id ), { noHistory: true } );
		state.form = emptyForm();
		state.form.kind = 'adr';
		state.form.productName = productName( id );
		state.formOpenedAt = Date.now();
		state.items.push( { kind: 'form', formKind: 'adr', noHistory: true } );
		render();
	}

	function chooseConsult() {
		state.chips = [];
		pushUser( labels.consultTitle || 'درخواست مشاوره', { noHistory: true } );
		state.form = emptyForm();
		state.form.kind = 'consult';
		state.formOpenedAt = Date.now();
		state.items.push( { kind: 'form', formKind: 'consult', noHistory: true } );
		render();
	}

	function doRestart() {
		startConversation();
		render();
	}

	function hadRealConversation() {
		return state.items.some( function ( it ) { return it.kind === 'user' && ! it.noHistory; } );
	}

	function restart() {
		// پیش از بازگشت به منو، در صورت داشتن گفتگوی واقعی، نظرسنجی رضایت را نشان بده.
		if ( cfg.csatEnabled && ! state.csatDone && hadRealConversation() ) {
			state.csatDone = true;
			state.chips = [];
			state.items.push( { kind: 'csat', noHistory: true } );
			render();
			return;
		}
		doRestart();
	}

	/* ---------- نظرسنجی رضایت پایان گفتگو (CSAT) ---------- */
	function renderCsatCard( it ) {
		var card  = el( 'div', 'nfx-msg nfx-msg--bot' );
		var inner = el( 'div', 'nfx-csat' );
		if ( it.rated ) {
			inner.classList.add( 'is-done' );
			inner.innerHTML = '<div class="nfx-csat__thanks">' + escapeHtml( t( 'csatThanks', 'سپاس از امتیاز شما 🙏' ) ) + '</div>';
			card.innerHTML = '<span class="nfx-msg__avatar">' + ICON.bot( 16 ) + '</span>';
			card.appendChild( inner );
			return card;
		}
		inner.appendChild( el( 'div', 'nfx-csat__title', escapeHtml( t( 'csatTitle', 'گفتگوی ما چطور بود؟' ) ) ) );
		var stars = el( 'div', 'nfx-csat__stars' );
		for ( var n = 1; n <= 5; n++ ) {
			( function ( score ) {
				var b = el( 'button', 'nfx-csat__star' );
				b.type = 'button';
				b.setAttribute( 'aria-label', String( score ) );
				b.innerHTML = ICON.star( 22 );
				b.addEventListener( 'mouseenter', function () { highlightStars( stars, score ); } );
				b.addEventListener( 'mouseleave', function () { highlightStars( stars, 0 ); } );
				b.addEventListener( 'click', function () { sendCsat( it, score ); } );
				stars.appendChild( b );
			} )( n );
		}
		inner.appendChild( stars );
		var skip = el( 'button', 'nfx-csat__skip', escapeHtml( t( 'csatSkip', 'رد کردن' ) ) );
		skip.type = 'button';
		skip.addEventListener( 'click', function () { doRestart(); } );
		inner.appendChild( skip );
		card.innerHTML = '<span class="nfx-msg__avatar">' + ICON.bot( 16 ) + '</span>';
		card.appendChild( inner );
		return card;
	}
	function highlightStars( stars, n ) {
		var kids = stars.children;
		for ( var i = 0; i < kids.length; i++ ) {
			kids[ i ].classList.toggle( 'is-on', i < n );
		}
	}
	function sendCsat( it, score ) {
		it.rated = true;
		ajax( 'ssc_chatbot_csat', { score: score } );
		state.chips = [ chip( 'plain', ICON.refresh( 16 ), t( 'mainMenu', 'منوی اصلی' ), doRestart ) ];
		render();
	}

	/* ---------- ارسال پیام (چت AI) ---------- */
	function sendMessage( text ) {
		if ( ! text || ! text.trim() || state.isLoading ) { return; }

		// اگر هنوز موضوعی انتخاب نشده، به دستیار عمومی وصل شو.
		if ( ! state.selectedProduct ) {
			state.selectedProduct = companyInfo.id;
		}

		// تاریخچه واقعی (پیام‌های ناوبری حذف می‌شوند).
		var history = state.items
			.filter( function ( it ) { return ( it.kind === 'bot' || it.kind === 'user' ) && ! it.noHistory; } )
			.map( function ( it ) { return { role: it.kind === 'bot' ? 'assistant' : 'user', content: it.content }; } );
		while ( history.length && history[ 0 ].role === 'assistant' ) { history.shift(); }

		state.chips = [];
		pushUser( text );
		state.isLoading = true;
		render();

		ajax( 'ssc_chatbot_chat', {
			message: text,
			product: state.selectedProduct,
			history: JSON.stringify( history )
		} ).then( function ( res ) {
			state.isLoading = false;
			var reply, logId = 0, logToken = '', suggestions = [], handoff = false;
			if ( res.ok && res.json && res.json.success ) {
				reply = res.json.data.reply || 'متاسفانه مشکلی در دریافت پاسخ پیش آمد.';
				logId = res.json.data.log_id || 0;
				logToken = res.json.data.log_token || '';
				suggestions = res.json.data.suggestions || [];
				handoff = ! ! res.json.data.handoff;
			} else {
				reply = errorMessage( res );
			}
			pushBot( reply );
			// تنظیم بازخورد و افکت تایپ روی همین پیام.
			var botItem = state.items[ state.items.length - 1 ];
			botItem.userQuestion = text; // برای تشخیص ارتباط پاسخ با عوارض.
			if ( logId && cfg.feedbackEnabled ) { botItem.logId = logId; botItem.logToken = logToken; }
			if ( cfg.typewriter ) { botItem.animate = true; state.pendingAnimate = botItem; }

			// انتخاب چیپس‌های پس از پاسخ: واگذاری به کارشناس > پیگیری هوشمند > پاسخ‌های آماده محصول.
			if ( handoff && cfg.handoffEnabled ) {
				offerHandoff();
			} else if ( suggestions.length && cfg.suggestionsEnabled ) {
				setSuggestionChips( suggestions );
			} else if ( state.selectedProduct && state.selectedProduct !== companyInfo.id ) {
				setProductQuickReplies();
			}
			render();
		} ).catch( function () {
			state.isLoading = false;
			pushBot( 'خطا در ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کنید.' );
			render();
		} );
	}

	/* ---------- ارسال فرم ---------- */
	function submitForm() {
		if ( state.isLoading ) { return; }
		var isAdr = state.form.kind === 'adr';
		// موافقت با حریم خصوصی (در صورت فعال بودن) الزامی است.
		if ( cfg.consentEnabled && ! state.form.consent ) {
			toast( t( 'consentRequired', 'برای ادامه، موافقت با حریم خصوصی الزامی است.' ), true );
			return;
		}
		state.isLoading = true;
		render();

		var payload = {
			type: isAdr ? 'گزارش عوارض دارویی' : 'درخواست مشاوره',
			name: state.form.name,
			phone: state.form.phone,
			description: state.form.description,
			nfx_hp: state.form.hp || '',
			nfx_elapsed: Date.now() - ( state.formOpenedAt || 0 ),
			extra: JSON.stringify( state.form.extra || {} )
		};
		if ( isAdr ) {
			payload.product = state.form.productName;
			payload.reporter_type = state.form.reporterType;
			payload.severity = state.form.severity;
			payload.outcome = state.form.outcome;
			payload.batch_number = state.form.batchNumber;
			payload.concomitant_drugs = state.form.concomitantDrugs;
		}

		ajax( 'ssc_chatbot_submit', payload ).then( function ( res ) {
			state.isLoading = false;
			if ( res.ok && res.json && res.json.success ) {
				// حذف کارت فرم و نمایش کارت موفقیت.
				state.items = state.items.filter( function ( it ) { return it.kind !== 'form'; } );
				state.items.push( { kind: 'success', noHistory: true } );
				state.form = emptyForm();
				state.chips = [ chip( 'plain', ICON.refresh( 16 ), 'بازگشت به منوی اصلی', restart ) ];
			} else {
				toast( errorMessage( res ), true );
			}
			render();
		} ).catch( function () {
			state.isLoading = false;
			toast( 'متاسفانه در ثبت اطلاعات مشکلی پیش آمد. لطفاً اتصال اینترنت را بررسی و مجدداً تلاش کنید.', true );
			render();
		} );
	}

	/* ---------- رندر ---------- */
	function render() { renderWindow(); }

	function renderWindow() {
		win.innerHTML = '';
		win.appendChild( buildHeader() );

		var body   = el( 'div', 'nfx-body' );
		var thread = el( 'div', 'nfx-chat' );
		// ناحیهٔ زنده روی خودِ رشتهٔ گفتگو (نه کل ریشه) تا فقط پیام‌های تازه اعلام شوند.
		thread.setAttribute( 'role', 'log' );
		thread.setAttribute( 'aria-live', 'polite' );
		thread.setAttribute( 'aria-relevant', 'additions text' );

		// آخرین پیام کاربر (برای نشان «ارسال شد»).
		state._lastUser = null;
		state.items.forEach( function ( it ) { if ( it.kind === 'user' ) { state._lastUser = it; } } );

		state.items.forEach( function ( it ) { thread.appendChild( renderItem( it ) ); } );

		if ( state.isLoading ) { thread.appendChild( typingEl() ); }
		if ( state.chips.length && ! state.isLoading ) { thread.appendChild( renderChips( state.chips ) ); }

		body.appendChild( thread );
		win.appendChild( body );
		win.appendChild( buildFooter() );

		scrollToBottom();
		maybeTypewriter();
		saveThread();
	}

	/* ---------- اکشن‌های پیام ربات: صدا (TTS) + بازخورد + اقدام‌های پاسخ ---------- */
	function botActions( it ) {
		var hasFeedback = it.logId && cfg.feedbackEnabled;
		var hasSpeak    = voiceOut && ! it.noHistory;
		var hasAnswer   = ! ! it.logId; // اقدام‌های زیر پاسخ‌های واقعی (کپی/بروشور/تماس/گزارش).
		if ( ! hasFeedback && ! hasSpeak && ! hasAnswer ) { return null; }
		var wrap = el( 'div', 'nfx-bot-actions' );
		if ( hasSpeak ) {
			var sp = el( 'button', 'nfx-act-btn nfx-speak' );
			sp.type = 'button';
			sp.title = t( 'speak', 'شنیدن پاسخ' );
			sp.setAttribute( 'aria-label', sp.title );
			sp.innerHTML = ICON.volume( 15 );
			sp.addEventListener( 'click', function () { toggleSpeak( it, sp ); } );
			wrap.appendChild( sp );
		}
		if ( hasFeedback ) {
			if ( it.rated ) {
				wrap.appendChild( el( 'span', 'nfx-feedback__thanks', 'ممنون از بازخورد شما 🙏' ) );
			} else {
				var up = el( 'button', 'nfx-act-btn nfx-fb-btn' ); up.type = 'button'; up.title = 'مفید بود'; up.innerHTML = '👍';
				var dn = el( 'button', 'nfx-act-btn nfx-fb-btn' ); dn.type = 'button'; dn.title = 'مفید نبود'; dn.innerHTML = '👎';
				up.addEventListener( 'click', function () { sendFeedback( it, 1 ); } );
				dn.addEventListener( 'click', function () { sendFeedback( it, -1 ); } );
				wrap.appendChild( up ); wrap.appendChild( dn );
			}
		}
		if ( hasAnswer ) {
			if ( wrap.children.length ) { wrap.appendChild( el( 'span', 'nfx-act-sep' ) ); }

			// کپی پاسخ.
			var cp = el( 'button', 'nfx-act-btn nfx-act-copy' );
			cp.type = 'button';
			cp.title = t( 'copy', 'کپی پاسخ' );
			cp.setAttribute( 'aria-label', cp.title );
			cp.innerHTML = ICON.copy( 15 );
			cp.addEventListener( 'click', function () { copyAnswer( it, cp ); } );
			wrap.appendChild( cp );

			// بروشور محصول جاری.
			var prod = productById( state.selectedProduct );
			if ( prod && prod.brochure ) {
				var br = el( 'a', 'nfx-act-btn nfx-act-pill' );
				br.href = prod.brochure; br.target = '_blank'; br.rel = 'noopener noreferrer';
				br.title = t( 'brochureBtn', 'بروشور' );
				br.innerHTML = ICON.fileText( 15 ) + '<span class="nfx-act-lbl">' + escapeHtml( t( 'brochureBtn', 'بروشور' ) ) + '</span>';
				wrap.appendChild( br );
			}

			// تماس با ما.
			if ( cfg.supportPhone ) {
				var cl = el( 'a', 'nfx-act-btn nfx-act-pill' );
				cl.href = 'tel:' + String( cfg.supportPhone ).replace( /\s/g, '' );
				cl.title = t( 'callUs', 'تماس با ما' );
				cl.innerHTML = ICON.phone( 15 ) + '<span class="nfx-act-lbl">' + escapeHtml( t( 'callUs', 'تماس با ما' ) ) + '</span>';
				wrap.appendChild( cl );
			}

			// گزارش فوری عارضه — فقط زیر پاسخ‌های مرتبط با عوارض.
			if ( show.adr && isAdrRelevant( it ) ) {
				var ur = el( 'button', 'nfx-act-btn nfx-act-pill nfx-act-urgent' );
				ur.type = 'button';
				ur.title = t( 'urgentReport', 'گزارش فوری عارضه' );
				ur.innerHTML = ICON.alert( 15 ) + '<span class="nfx-act-lbl">' + escapeHtml( t( 'urgentReport', 'گزارش فوری عارضه' ) ) + '</span>';
				ur.addEventListener( 'click', urgentAdr );
				wrap.appendChild( ur );
			}
		}
		return wrap;
	}

	function copyAnswer( it, btn ) {
		var text = stripTags( boldify( it.content ).replace( /<br\s*\/?>/gi, '\n' ) );
		function done() {
			btn.classList.add( 'is-done' );
			btn.innerHTML = ICON.check( 15 );
			toast( t( 'copied', 'کپی شد ✓' ) );
			setTimeout( function () { btn.classList.remove( 'is-done' ); btn.innerHTML = ICON.copy( 15 ); }, 1500 );
		}
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done, function () { fallbackCopy( text ); done(); } );
		} else {
			fallbackCopy( text ); done();
		}
	}
	function fallbackCopy( text ) {
		try {
			var ta = document.createElement( 'textarea' );
			ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild( ta ); ta.focus(); ta.select();
			document.execCommand( 'copy' );
			document.body.removeChild( ta );
		} catch ( e ) {}
	}
	function urgentAdr() {
		var pid = state.selectedProduct;
		if ( pid && pid !== companyInfo.id && productById( pid ) ) { openAdrForm( pid ); }
		else { chooseAdr(); }
	}
	// تشخیص هوشمند: آیا پاسخ به عوارض دارویی مربوط است؟ (تا دکمهٔ گزارش فوری بی‌جا نیاید)
	function isAdrRelevant( it ) {
		var hay = ( ( it.userQuestion || '' ) + ' ' + ( it.content || '' ) )
			.replace( /ي/g, 'ی' ).replace( /ك/g, 'ک' );
		var kws = [ 'عارضه', 'عوارض', 'جانبی', 'حساسیت', 'آلرژی', 'واکنش', 'مسموم', 'تهوع', 'سرگیجه', 'خارش', 'کهیر', 'تورم', 'بثورات', 'تب', 'استفراغ', 'اسهال', 'درد بعد', 'بعد از مصرف', 'بعد مصرف' ];
		for ( var i = 0; i < kws.length; i++ ) {
			if ( hay.indexOf( kws[ i ] ) !== -1 ) { return true; }
		}
		return false;
	}
	function sendFeedback( it, rating ) {
		it.rated = true;
		ajax( 'ssc_chatbot_feedback', { log_id: it.logId, log_token: it.logToken || '', rating: rating } );
		render();
	}

	/* ---------- خواندن پاسخ با صدا (Text-to-Speech) ---------- */
	var speakingBtn = null;
	var ttsVoices = [];
	function loadVoices() {
		try { ttsVoices = window.speechSynthesis.getVoices() || []; } catch ( e ) { ttsVoices = []; }
	}
	if ( voiceOut ) {
		loadVoices();
		try { window.speechSynthesis.addEventListener( 'voiceschanged', loadVoices ); } catch ( e ) {
			try { window.speechSynthesis.onvoiceschanged = loadVoices; } catch ( e2 ) {}
		}
	}
	function pickVoice() {
		if ( ! ttsVoices.length ) { loadVoices(); }
		var fa = ttsVoices.filter( function ( v ) { return /fa|persian|farsi/i.test( ( v.lang || '' ) + ' ' + ( v.name || '' ) ); } )[ 0 ];
		if ( fa ) { return fa; }
		// در نبود صدای فارسی، عربی نزدیک‌ترین گزینه است؛ وگرنه هر صدای موجود.
		var ar = ttsVoices.filter( function ( v ) { return /^ar/i.test( v.lang || '' ); } )[ 0 ];
		return ar || ttsVoices[ 0 ] || null;
	}
	function toggleSpeak( it, btn ) {
		if ( ! voiceOut ) { return; }
		// اگر همین پیام در حال خواندن است، متوقف کن.
		if ( speakingBtn === btn && ( window.speechSynthesis.speaking || window.speechSynthesis.pending ) ) {
			window.speechSynthesis.cancel();
			resetSpeakBtn();
			return;
		}
		window.speechSynthesis.cancel();
		resetSpeakBtn();

		var text = stripTags( boldify( it.content ).replace( /<br\s*\/?>/gi, '\n' ) );
		var u = new window.SpeechSynthesisUtterance( text );
		var v = pickVoice();
		if ( v ) { u.voice = v; u.lang = v.lang; } else { u.lang = 'fa-IR'; }
		u.rate = 1;
		u.onend = resetSpeakBtn;
		u.onerror = resetSpeakBtn;

		speakingBtn = btn;
		btn.classList.add( 'is-speaking' );
		btn.title = t( 'speakStop', 'توقف صدا' );
		btn.innerHTML = ICON.stopCircle( 15 );

		// رفع باگ کروم: speak بلافاصله بعد از cancel گاهی اجرا نمی‌شود → با تاخیر کوتاه.
		setTimeout( function () {
			try { window.speechSynthesis.speak( u ); } catch ( e ) { resetSpeakBtn(); return; }
			// اگر مرورگر صدایی نداشت و چیزی پخش نشد، دکمه را آزاد کن.
			setTimeout( function () {
				if ( speakingBtn === btn && ! window.speechSynthesis.speaking && ! window.speechSynthesis.pending ) {
					resetSpeakBtn();
				}
			}, 900 );
		}, 60 );
	}
	function resetSpeakBtn() {
		if ( speakingBtn ) {
			speakingBtn.classList.remove( 'is-speaking' );
			speakingBtn.title = t( 'speak', 'شنیدن پاسخ' );
			speakingBtn.innerHTML = ICON.volume( 15 );
			speakingBtn = null;
		}
	}
	// توقف خواندن هنگام بسته‌شدن چت.
	if ( voiceOut ) {
		window.addEventListener( 'beforeunload', function () { try { window.speechSynthesis.cancel(); } catch ( e ) {} } );
	}

	/* ---------- افکت تایپ تدریجی ---------- */
	function maybeTypewriter() {
		var item = state.pendingAnimate;
		if ( ! item ) { return; }
		var bubble = win.querySelector( '.nfx-msg__bubble.nfx-anim' );
		if ( ! bubble ) { state.pendingAnimate = null; return; }
		state.pendingAnimate = null;
		item.animated = true;
		var fullHtml = boldify( item.content );
		var plain = item.content;
		bubble.classList.remove( 'nfx-anim' );
		bubble.textContent = '';
		var len = plain.length;
		var tick = 25;
		var perTick = Math.max( 1, Math.ceil( len / ( 1000 / tick ) ) );
		var i = 0;
		var timer = setInterval( function () {
			if ( ! win.contains( bubble ) ) { clearInterval( timer ); return; }
			i += perTick;
			bubble.textContent = plain.slice( 0, i );
			scrollToBottom();
			if ( i >= len ) {
				clearInterval( timer );
				bubble.innerHTML = fullHtml;
				var prow = bubble.parentNode;
				if ( prow && ! prow.querySelector( '.nfx-bot-actions' ) ) {
					var acts = botActions( item );
					if ( acts ) { prow.appendChild( acts ); }
				}
				scrollToBottom();
			}
		}, tick );
	}

	function buildHeader() {
		var h = el( 'div', 'nfx-header' );
		h.appendChild( el( 'div', 'nfx-header__icon', ICON.bot( 24 ) ) );
		var texts = el( 'div', 'nfx-header__texts' );
		var hTitle = el( 'h3', 'nfx-header__title', escapeHtml( cfg.headerTitle || 'دستیار هوشمند' ) );
		hTitle.id = TITLE_ID; // مرجع aria-labelledby دیالوگ.
		texts.appendChild( hTitle );
		var isOffline = ( cfg.online === false );
		texts.appendChild( el( 'p', 'nfx-header__sub' + ( isOffline ? ' is-offline' : '' ), '<i></i>' + escapeHtml( cfg.statusText || 'آنلاین' ) ) );
		h.appendChild( texts );

		var restartBtn = el( 'button', 'nfx-header__restart', ICON.refresh( 18 ) );
		restartBtn.setAttribute( 'aria-label', 'شروع دوباره' );
		restartBtn.title = 'منوی اصلی';
		restartBtn.addEventListener( 'click', restart );
		h.appendChild( restartBtn );

		h.appendChild( el( 'div', 'nfx-header__sparkle', ICON.sparkles( 96 ) ) );
		return h;
	}

	// کلاس انیمیشن ورود (فقط یک‌بار برای هر پیام تازه).
	function enterCls( it ) {
		if ( it.seen ) { return ''; }
		it.seen = true;
		return ' nfx-msg--enter';
	}

	function renderItem( it ) {
		if ( it.kind === 'bot' ) {
			var b = el( 'div', 'nfx-msg nfx-msg--bot' + enterCls( it ) );
			var willAnimate = it.animate && ! it.animated;
			b.innerHTML = '<span class="nfx-msg__avatar">' + ICON.bot( 16 ) + '</span>' +
				'<div class="nfx-msg__bubble' + ( willAnimate ? ' nfx-anim' : '' ) + '">' + boldify( it.content ) + '</div>';
			// اکشن‌های پیام (صدا + بازخورد + اقدام‌ها) — پس از اتمام انیمیشن اضافه می‌شوند.
			if ( ! willAnimate ) {
				var acts = botActions( it );
				if ( acts ) { b.appendChild( acts ); }
			}
			return b;
		}
		if ( it.kind === 'user' ) {
			var u = el( 'div', 'nfx-msg nfx-msg--user' + enterCls( it ) );
			u.innerHTML = '<span class="nfx-msg__avatar">' + ICON.user( 16 ) + '</span>' +
				'<div class="nfx-msg__bubble">' + escapeHtml( it.content ) + '</div>';
			// نشان «ارسال شد» زیر آخرین پیام کاربر.
			if ( it === state._lastUser ) {
				u.appendChild( el( 'div', 'nfx-msg__status', '<span class="nfx-tick">✓✓</span> ' + escapeHtml( t( 'sent', 'ارسال شد' ) ) ) );
			}
			return u;
		}
		if ( it.kind === 'product' ) {
			return renderProductCard( it.product, it );
		}
		if ( it.kind === 'form' ) {
			return renderFormCard( it.formKind );
		}
		if ( it.kind === 'success' ) {
			return renderSuccessCard();
		}
		if ( it.kind === 'csat' ) {
			return renderCsatCard( it );
		}
		return el( 'div' );
	}

	/* ---------- کارت محصول (تصویر + خلاصه + بروشور) ---------- */
	function renderProductCard( p, it ) {
		var card  = el( 'div', 'nfx-msg nfx-msg--bot' + ( it ? enterCls( it ) : '' ) );
		var inner = el( 'div', 'nfx-prodcard' );
		var html  = '';
		if ( p.image ) {
			html += '<div class="nfx-prodcard__media"><img src="' + escapeHtml( p.image ) + '" alt="' + escapeHtml( p.name ) + '" loading="lazy"></div>';
		}
		html += '<div class="nfx-prodcard__body">';
		html += '<div class="nfx-prodcard__name">' + ICON.package( 15 ) + '<span>' + escapeHtml( p.name ) + '</span></div>';
		if ( p.summary ) { html += '<div class="nfx-prodcard__sum">' + escapeHtml( p.summary ) + '</div>'; }
		html += '</div>';
		inner.innerHTML = html;
		if ( p.brochure ) {
			var b = el( 'a', 'nfx-prodcard__btn' );
			b.href = p.brochure; b.target = '_blank'; b.rel = 'noopener noreferrer';
			b.innerHTML = ICON.fileText( 14 ) + '<span>' + escapeHtml( cfg.brochureLabel || 'مشاهده بروشور' ) + '</span>';
			inner.appendChild( b );
		}
		card.innerHTML = '<span class="nfx-msg__avatar">' + ICON.bot( 16 ) + '</span>';
		card.appendChild( inner );
		return card;
	}

	function typingEl() {
		var t = el( 'div', 'nfx-msg nfx-msg--bot' );
		t.innerHTML = '<span class="nfx-msg__avatar">' + ICON.bot( 16 ) + '</span>' +
			'<div class="nfx-typing"><span></span><span></span><span></span></div>';
		return t;
	}

	function renderChips( chips ) {
		var wrap = el( 'div', 'nfx-chipset' );
		chips.forEach( function ( c ) {
			var btn;
			if ( c.color === 'pill' || c.color === 'brochure' ) {
				btn = el( 'button', 'nfx-qr-chip' + ( c.color === 'brochure' ? ' nfx-qr-chip--brochure' : '' ) );
				btn.innerHTML = ( c.icon || '' ) + '<span>' + escapeHtml( c.label ) + '</span>';
			} else {
				btn = el( 'button', 'nfx-opt-chip nfx-opt-chip--' + c.color );
				btn.innerHTML = ( c.icon ? '<span class="nfx-opt-chip__icon">' + c.icon + '</span>' : '' ) +
					'<span class="nfx-opt-chip__label">' + escapeHtml( c.label ) + '</span>' +
					'<span class="nfx-opt-chip__chevron">' + ICON.chevronLeft( 16 ) + '</span>';
			}
			btn.type = 'button';
			btn.addEventListener( 'click', c.onClick );
			wrap.appendChild( btn );
		} );
		// چیپس‌های pill در یک ردیف افقی.
		if ( chips.length && ( chips[ 0 ].color === 'pill' || chips[ 0 ].color === 'brochure' ) ) {
			wrap.classList.add( 'nfx-chipset--pills' );
		}
		return wrap;
	}

	/* ---------- کارت فرم داخل چت ---------- */
	function renderFormCard( kind ) {
		var isAdr = kind === 'adr';
		var card  = el( 'div', 'nfx-msg nfx-msg--bot' );
		var inner = el( 'div', 'nfx-formcard' );

		var title = isAdr ? 'فرم ثبت گزارش عارضه' : 'فرم درخواست مشاوره';
		inner.appendChild( el( 'div', 'nfx-formcard__title', ( isAdr ? ICON.activity( 16 ) : ICON.headphones( 16 ) ) + '<span>' + escapeHtml( title ) + '</span>' ) );

		if ( isAdr && state.form.productName ) {
			inner.appendChild( el( 'div', 'nfx-formcard__note', 'داروی مرتبط: <b>' + escapeHtml( state.form.productName ) + '</b>' ) );
		}

		var form = el( 'form', 'nfx-form' );

		// تله Honeypot (نامرئی) — کاربر واقعی پر نمی‌کند.
		var hp = el( 'input', 'nfx-hp' );
		hp.type = 'text';
		hp.name = 'nfx_hp';
		hp.tabIndex = -1;
		hp.setAttribute( 'autocomplete', 'off' );
		hp.setAttribute( 'aria-hidden', 'true' );
		hp.value = state.form.hp || '';
		hp.addEventListener( 'input', function () { state.form.hp = hp.value; } );
		form.appendChild( hp );

		form.appendChild( field( 'name', ICON.user( 14 ) + ' نام و نام خانوادگی', 'text', 'مثلا: علی احمدی', false, true ) );
		form.appendChild( field( 'phone', ICON.phone( 14 ) + ' شماره تماس', 'tel', '0912...', true, true ) );

		// فیلدهای استاندارد عوارض دارویی فقط در حالت «داروسازی».
		var pharma = ( cfg.businessMode === 'pharma' );
		if ( isAdr && pharma && adrOptions.reporter_type && adrOptions.reporter_type.length ) {
			form.appendChild( selectField( 'reporterType', ICON.user( 14 ) + ' نوع گزارش‌دهنده', adrOptions.reporter_type, 'انتخاب کنید...' ) );
		}

		var descLabel = isAdr ? ' شرح عارضه مشاهده‌شده' : ' موضوع و خلاصه درخواست';
		var descPh    = isAdr ? 'علائم و مشکلاتی که پس از مصرف دارو پیش آمد را با جزئیات بنویسید...' : 'به‌صورت خلاصه بنویسید در چه موردی نیاز به مشاوره دارید...';
		var fDesc   = el( 'div', 'nfx-field' );
		var descId  = nextFieldId( 'description' );
		var descLab = el( 'label', 'nfx-field__label', ICON.fileText( 14 ) + escapeHtml( descLabel ) );
		descLab.setAttribute( 'for', descId );
		fDesc.appendChild( descLab );
		var ta = el( 'textarea', 'nfx-textarea' );
		ta.id = descId;
		ta.setAttribute( 'aria-required', 'true' );
		ta.rows = 3; ta.required = true; ta.placeholder = descPh; ta.value = state.form.description;
		ta.addEventListener( 'input', function () { state.form.description = ta.value; } );
		fDesc.appendChild( ta );
		form.appendChild( fDesc );

		if ( isAdr && pharma ) {
			var grid = el( 'div', 'nfx-grid2' );
			if ( adrOptions.severity && adrOptions.severity.length ) {
				grid.appendChild( selectField( 'severity', ICON.activity( 14 ) + ' شدت', adrOptions.severity, 'انتخاب...' ) );
			}
			if ( adrOptions.outcome && adrOptions.outcome.length ) {
				grid.appendChild( selectField( 'outcome', ICON.check( 14 ) + ' پیامد', adrOptions.outcome, 'انتخاب...' ) );
			}
			if ( grid.children.length ) { form.appendChild( grid ); }
			form.appendChild( field( 'batchNumber', ICON.fileText( 14 ) + ' شماره سری ساخت — اختیاری', 'text', 'روی بسته محصول', true, false ) );
		}

		// فیلدهای سفارشی تعریف‌شده از پنل مدیریت (فرم‌ساز پویا).
		formFields.forEach( function ( fdef ) {
			form.appendChild( extraField( fdef ) );
		} );

		// موافقت با حریم خصوصی (اختیاری/قابل‌فعال‌سازی از پنل).
		if ( cfg.consentEnabled ) {
			var cf  = el( 'div', 'nfx-consent' );
			var lab = el( 'label', 'nfx-consent__main' );
			var cb  = el( 'input' );
			cb.type = 'checkbox';
			cb.className = 'nfx-consent__cb';
			cb.checked = ! ! state.form.consent;
			cb.addEventListener( 'change', function () { state.form.consent = cb.checked; } );
			var sp = el( 'span' );
			sp.textContent = cfg.consentText || '';
			lab.appendChild( cb );
			lab.appendChild( sp );
			cf.appendChild( lab );
			if ( cfg.consentLink ) {
				var a = el( 'a', 'nfx-consent__link' );
				a.href = cfg.consentLink;
				a.target = '_blank';
				a.rel = 'noopener noreferrer';
				a.textContent = t( 'privacy', 'سیاست حریم خصوصی' );
				cf.appendChild( a );
			}
			form.appendChild( cf );
		}

		var btn = el( 'button', 'nfx-submit ' + ( isAdr ? 'nfx-submit--red' : 'nfx-submit--purple' ) );
		btn.type = 'submit'; btn.disabled = state.isLoading;
		btn.innerHTML = ( state.isLoading ? '<span class="nfx-spin">' + ICON.loader( 18 ) + '</span>' : ICON.check( 18 ) ) +
			'<span>' + ( isAdr ? 'ثبت گزارش عوارض' : 'ثبت درخواست' ) + '</span>';
		form.appendChild( btn );

		form.addEventListener( 'submit', function ( e ) { e.preventDefault(); submitForm(); } );
		inner.appendChild( form );
		card.innerHTML = '<span class="nfx-msg__avatar">' + ICON.bot( 16 ) + '</span>';
		card.appendChild( inner );
		return card;
	}

	// شناسهٔ یکتا برای پیوند برنامه‌ای label به کنترل (label/for) — لازمهٔ صفحه‌خوان.
	var fieldSeq = 0;
	function nextFieldId( key ) {
		fieldSeq++;
		return 'nfx-f-' + key + '-' + fieldSeq;
	}

	function field( key, labelHtml, type, ph, ltr, required ) {
		var f  = el( 'div', 'nfx-field' );
		var id = nextFieldId( key );
		var lab = el( 'label', 'nfx-field__label', labelHtml );
		lab.setAttribute( 'for', id );
		f.appendChild( lab );
		var inp = el( 'input', 'nfx-input' + ( ltr ? ' nfx-input--ltr' : '' ) );
		inp.id = id;
		inp.type = type;
		inp.required = ( required !== false );
		if ( inp.required ) { inp.setAttribute( 'aria-required', 'true' ); }
		inp.placeholder = ph;
		inp.value = state.form[ key ];
		inp.addEventListener( 'input', function () { state.form[ key ] = inp.value; } );
		f.appendChild( inp );
		return f;
	}

	function selectField( key, labelHtml, options, ph ) {
		var f  = el( 'div', 'nfx-field' );
		var id = nextFieldId( key );
		var lab = el( 'label', 'nfx-field__label', labelHtml );
		lab.setAttribute( 'for', id );
		f.appendChild( lab );
		var sel = el( 'select', 'nfx-input nfx-select' );
		sel.id = id;
		var o0 = el( 'option' ); o0.value = ''; o0.textContent = ph || 'انتخاب کنید...'; sel.appendChild( o0 );
		options.forEach( function ( opt ) {
			var o = el( 'option' ); o.value = opt; o.textContent = opt;
			if ( state.form[ key ] === opt ) { o.selected = true; }
			sel.appendChild( o );
		} );
		sel.addEventListener( 'change', function () { state.form[ key ] = sel.value; } );
		f.appendChild( sel );
		return f;
	}

	// رندر یک فیلد سفارشی فرم‌ساز بر اساس نوع آن؛ مقدار در state.form.extra[key] نگه داشته می‌شود.
	function extraField( fdef ) {
		var key   = fdef.key;
		var label = escapeHtml( fdef.label );
		var f     = el( 'div', 'nfx-field' );
		var id    = nextFieldId( 'extra-' + key );

		if ( 'checkbox' === fdef.type ) {
			var lab = el( 'label', 'nfx-consent__main' );
			var cb  = el( 'input' );
			cb.type = 'checkbox';
			cb.id = id;
			cb.className = 'nfx-consent__cb';
			cb.required = ! ! fdef.required;
			cb.checked = ! ! state.form.extra[ key ];
			cb.addEventListener( 'change', function () { state.form.extra[ key ] = cb.checked; } );
			var sp = el( 'span', '', label );
			lab.appendChild( cb );
			lab.appendChild( sp );
			f.className = 'nfx-consent';
			f.appendChild( lab );
			return f;
		}

		if ( 'radio' === fdef.type ) {
			f.appendChild( el( 'div', 'nfx-field__label', label ) );
			var opts = fdef.options || [];
			var grp  = el( 'div', 'nfx-radio-group' );
			opts.forEach( function ( opt, idx ) {
				var rlab = el( 'label', 'nfx-radio-option' );
				var r    = el( 'input' );
				r.type = 'radio';
				r.name = id;
				r.required = ! ! fdef.required && 0 === idx;
				r.checked = ( state.form.extra[ key ] === opt );
				r.addEventListener( 'change', function () { state.form.extra[ key ] = opt; } );
				rlab.appendChild( r );
				rlab.appendChild( el( 'span', '', escapeHtml( opt ) ) );
				grp.appendChild( rlab );
			} );
			f.appendChild( grp );
			return f;
		}

		if ( 'select' === fdef.type ) {
			return selectFieldFor( state.form.extra, key, label, fdef.options || [], fdef.placeholder );
		}

		var lab2 = el( 'label', 'nfx-field__label', label );
		lab2.setAttribute( 'for', id );
		f.appendChild( lab2 );

		if ( 'textarea' === fdef.type ) {
			var ta = el( 'textarea', 'nfx-textarea' );
			ta.id = id;
			ta.rows = 3;
			ta.required = ! ! fdef.required;
			ta.placeholder = fdef.placeholder || '';
			ta.value = state.form.extra[ key ] || '';
			ta.addEventListener( 'input', function () { state.form.extra[ key ] = ta.value; } );
			f.appendChild( ta );
			return f;
		}

		var inputType = ( 'tel' === fdef.type || 'email' === fdef.type || 'number' === fdef.type ) ? fdef.type : 'text';
		var inp = el( 'input', 'nfx-input' + ( 'tel' === inputType || 'number' === inputType ? ' nfx-input--ltr' : '' ) );
		inp.id = id;
		inp.type = inputType;
		inp.required = ! ! fdef.required;
		inp.placeholder = fdef.placeholder || '';
		inp.value = state.form.extra[ key ] || '';
		inp.addEventListener( 'input', function () { state.form.extra[ key ] = inp.value; } );
		f.appendChild( inp );
		return f;
	}

	// مثل selectField ولی مقدار را در یک شیء دلخواه (state.form.extra) به‌جای state.form می‌نویسد.
	function selectFieldFor( store, key, labelHtml, options, ph ) {
		var f  = el( 'div', 'nfx-field' );
		var id = nextFieldId( 'extra-' + key );
		var lab = el( 'label', 'nfx-field__label', labelHtml );
		lab.setAttribute( 'for', id );
		f.appendChild( lab );
		var sel = el( 'select', 'nfx-input nfx-select' );
		sel.id = id;
		var o0 = el( 'option' ); o0.value = ''; o0.textContent = ph || 'انتخاب کنید...'; sel.appendChild( o0 );
		options.forEach( function ( opt ) {
			var o = el( 'option' ); o.value = opt; o.textContent = opt;
			if ( store[ key ] === opt ) { o.selected = true; }
			sel.appendChild( o );
		} );
		sel.addEventListener( 'change', function () { store[ key ] = sel.value; } );
		f.appendChild( sel );
		return f;
	}

	function renderSuccessCard() {
		var card = el( 'div', 'nfx-msg nfx-msg--bot' );
		var inner = el( 'div', 'nfx-success-card' );
		inner.innerHTML =
			'<div class="nfx-success-card__icon">' + ICON.check( 28 ) + '</div>' +
			'<div class="nfx-success-card__title">ثبت موفقیت‌آمیز بود</div>' +
			'<div class="nfx-success-card__text">اطلاعات شما با موفقیت ثبت شد. کارشناسان ما در اسرع وقت با شما تماس می‌گیرند.</div>';
		card.innerHTML = '<span class="nfx-msg__avatar">' + ICON.bot( 16 ) + '</span>';
		card.appendChild( inner );
		return card;
	}

	/* ---------- ورودی ثابت پایین ---------- */
	var acTimer = null;
	function buildFooter() {
		var foot = el( 'form', 'nfx-chat-foot' );
		var wrap = el( 'div', 'nfx-input-wrap' );
		var input = el( 'input' );
		input.type = 'text';
		input.placeholder = t( 'inputPlaceholder', 'پیام خود را بنویسید...' );
		input.disabled = state.isLoading;
		input.setAttribute( 'autocomplete', 'off' );
		// placeholder جایگزین label نیست؛ برچسب برنامه‌ای صریح لازم است.
		input.setAttribute( 'aria-label', t( 'inputLabel', 'متن پیام' ) );

		// لیست تکمیل خودکار (از بانک) — الگوی combobox.
		var ac = el( 'div', 'nfx-ac' );
		ac.setAttribute( 'role', 'listbox' );
		ac.setAttribute( 'aria-label', t( 'suggestionsHint', 'سوالات مرتبط:' ) );
		ac.style.display = 'none';
		function hideAc() { ac.style.display = 'none'; ac.innerHTML = ''; }
		function chooseAc( q ) { hideAc(); input.value = ''; sendMessage( q ); }

		// دکمه میکروفون (ورودی صوتی).
		var micBtn = null;
		if ( voiceIn ) {
			micBtn = el( 'button', 'nfx-mic' );
			micBtn.type = 'button';
			micBtn.title = t( 'mic', 'گفتن با صدا' );
			micBtn.setAttribute( 'aria-label', micBtn.title );
			micBtn.innerHTML = ICON.mic( 18 );
			micBtn.disabled = state.isLoading;
			micBtn.addEventListener( 'click', function () { startDictation( input, micBtn ); } );
		}

		var sendBtn = el( 'button', 'nfx-send' );
		sendBtn.setAttribute( 'aria-label', t( 'send', 'ارسال پیام' ) );
		sendBtn.type = 'submit';
		sendBtn.disabled = state.isLoading;
		sendBtn.innerHTML = state.isLoading ? '<span class="nfx-spin">' + ICON.loader( 18 ) + '</span>' : ICON.send( 18 );

		wrap.appendChild( input );
		if ( micBtn ) { wrap.appendChild( micBtn ); }
		wrap.appendChild( sendBtn );
		wrap.appendChild( ac );
		foot.appendChild( wrap );
		// نوار پایین: جملهٔ سلب مسئولیت.
		var meta = el( 'div', 'nfx-foot-meta' );
		meta.appendChild( el( 'span', 'nfx-disclaimer', cfg.disclaimer ? escapeHtml( cfg.disclaimer ) : '' ) );
		foot.appendChild( meta );

		if ( cfg.autocompleteEnabled ) {
			input.addEventListener( 'input', function () {
				var term = input.value.trim();
				clearTimeout( acTimer );
				if ( term.length < 2 ) { hideAc(); return; }
				acTimer = setTimeout( function () {
					ajax( 'ssc_chatbot_suggest', {
						term: term,
						product: state.selectedProduct || companyInfo.id
					} ).then( function ( res ) {
						var items = ( res.json && res.json.success && res.json.data.items ) || [];
						if ( ! items.length || input.value.trim().length < 2 ) { hideAc(); return; }
						ac.innerHTML = '';
						items.forEach( function ( q ) {
							var item = el( 'button', 'nfx-ac__item' );
							item.type = 'button';
							item.textContent = q;
							item.addEventListener( 'mousedown', function ( e ) { e.preventDefault(); chooseAc( q ); } );
							ac.appendChild( item );
						} );
						ac.style.display = 'block';
					} ).catch( function () { hideAc(); } );
				}, 250 );
			} );
			input.addEventListener( 'blur', function () { setTimeout( hideAc, 150 ); } );
		}

		foot.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			hideAc();
			var val = input.value;
			if ( ! val.trim() ) { return; }
			input.value = '';
			sendMessage( val );
		} );
		// فوکوس خودکار فقط در حالت گفتگوی آزاد (نه روی گزینه‌ها/فرم/کارت‌ها — تا کیبورد موبایل ناخواسته باز نشود).
		var hasForm  = state.items.some( function ( it ) { return it.kind === 'form'; } );
		var lastItem = state.items[ state.items.length - 1 ];
		var lastKind = lastItem ? lastItem.kind : '';
		if ( ! state.isLoading && ! state.chips.length && ! hasForm && state.items.length && 'csat' !== lastKind && 'success' !== lastKind ) {
			setTimeout( function () { try { input.focus( { preventScroll: true } ); } catch ( e ) { input.focus(); } }, 60 );
		}
		return foot;
	}

	/* ---------- ورودی صوتی (Speech-to-Text) ---------- */
	var recognizer = null;
	function startDictation( input, micBtn ) {
		if ( ! voiceIn ) { return; }
		// کلیک دوباره هنگام شنیدن = توقف.
		if ( recognizer ) {
			try { recognizer.stop(); } catch ( e ) {}
			return;
		}
		try {
			recognizer = new SpeechRec();
		} catch ( e ) { recognizer = null; return; }
		recognizer.lang = 'fa-IR';
		recognizer.interimResults = true;
		recognizer.continuous = false;
		var base = input.value ? ( input.value + ' ' ) : '';
		micBtn.classList.add( 'is-listening' );
		micBtn.title = t( 'micListening', 'در حال شنیدن…' );
		recognizer.onresult = function ( ev ) {
			var finalText = '', interim = '';
			for ( var i = 0; i < ev.results.length; i++ ) {
				var r = ev.results[ i ];
				if ( r.isFinal ) { finalText += r[ 0 ].transcript; }
				else { interim += r[ 0 ].transcript; }
			}
			input.value = ( base + finalText + interim ).trim();
		};
		function stop() {
			micBtn.classList.remove( 'is-listening' );
			micBtn.title = t( 'mic', 'گفتن با صدا' );
			recognizer = null;
			try { input.focus( { preventScroll: true } ); } catch ( e ) {}
		}
		recognizer.onerror = stop;
		recognizer.onend = stop;
		try { recognizer.start(); } catch ( e ) { stop(); }
	}

	function scrollToBottom() {
		var body = win.querySelector( '.nfx-body' );
		if ( body ) { body.scrollTop = body.scrollHeight; }
	}

} )();
