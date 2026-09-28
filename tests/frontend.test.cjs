const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/chatbot.js'), 'utf8').replace(/\r\n/g, '\n');
function load(from, to, context = {}) {
  vm.createContext(context);
  vm.runInContext(source.slice(source.indexOf(from), source.indexOf(to, source.indexOf(from))), context);
  return context;
}
test('SSE parser survives every possible split, CRLF, and Persian UTF-8 text', () => {
  const wire = 'event: delta\r\ndata: {"text":"سلام"}\r\n\r\nevent: done\ndata: {"reply":"سلام"}\n\n';
  for (let split = 1; split < wire.length; split++) {
    const received = [];
    const { sseParser } = load('        function sseParser(', '        function sendChatStream(');
    const parse = sseParser((event, data) => received.push([event, data]));
    parse(wire.slice(0, split)); parse(wire.slice(split));
    assert.equal(received.length, 2); assert.equal(received[0][0], 'delta'); assert.equal(received[1][1].reply, 'سلام');
  }
});
test('SSE parser handles one character per network chunk and ignores comments', () => {
  const events = [];
  const { sseParser } = load('        function sseParser(', '        function sendChatStream(');
  const parse = sseParser((name, data) => events.push([name, data]));
  for (const char of ': heartbeat\nevent: done\ndata: {\ndata: "reply":"OK"}\n\n') parse(char);
  assert.equal(events[0][1].reply, 'OK');
});
test('Escaping protects quote-bearing URLs and model output from HTML injection', () => {
  const document = { createElement() { return { textContent: '', get innerHTML() { return this.textContent.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); } }; } };
  const { md } = load('        function esc(', '        function uid(', { document });
  assert.ok(md('[x](https://example.org/"onmouseover="alert)') .includes('&quot;'));
  assert.ok(!md('<img src=x onerror=alert(1)>').includes('<img'));
  assert.ok(!md('[x](javascript:alert)').includes('<a'));
});
function memoryContext(persist, days, extra = {}) {
  const local = {}, session = {};
  const storage = (bag) => ({ getItem: (k) => (k in bag ? bag[k] : null), setItem: (k, v) => { bag[k] = String(v); }, removeItem: (k) => { delete bag[k]; } });
  const window = { crypto: require('node:crypto').webcrypto, localStorage: storage(local), sessionStorage: storage(session) };
  const ctx = Object.assign({ cfg: { memory: { persist, days } }, state: { persist: true }, window, Uint8Array, Math, JSON, Date, Number }, extra);
  load('        var MEM = cfg.memory', '        /** Conversation transcript persisted', ctx);
  vm.runInContext(source.slice(source.indexOf('        var CONV_KEY'), source.indexOf('        /* ------------------------------------------------------------------ *\n         * DOM construction')), ctx);
  return { ctx, local, session };
}
test('Conversation ids are 128-bit hex, reused across visits and replaced on reset', () => {
  const { ctx, local } = memoryContext(true, 7);
  const first = ctx.getConv();
  assert.match(first, /^[a-f0-9]{32}$/);
  assert.equal(JSON.parse(local.ssc_conv_v1).id, first, 'kept in local storage: closing the tab does not lose it');
  ctx.convId = null;
  assert.equal(ctx.getConv(), first, 'a new page load continues the same conversation');
  ctx.resetConv();
  assert.notEqual(ctx.getConv(), first);
});
test('A remembered conversation expires after the configured days', () => {
  const { ctx, local } = memoryContext(true, 2);
  const first = ctx.getConv();
  local.ssc_conv_v1 = JSON.stringify({ id: first, at: Date.now() - 3 * 24 * 3600 * 1000 });
  ctx.convId = null;
  assert.notEqual(ctx.getConv(), first);
});
test('Health conversations stay out of long-lived browser storage', () => {
  const { ctx, local, session } = memoryContext(false, 1);
  ctx.state.persist = false;
  ctx.getConv();
  assert.deepEqual(Object.keys(local), []);
  assert.deepEqual(Object.keys(session), []);
});
test('A failed POST is never automatically replayed via AJAX', async () => {
  const calls = [];
  const context = { cfg: { restUrl: '/rest/', ajaxUrl: '/ajax/' }, URLSearchParams, getCid: () => 'test', AbortController, setTimeout, clearTimeout,
    fetch: async (url) => { calls.push(url); throw new Error('connection lost after commit'); } };
  const { transport } = load('        function request(', '        function chatRoute(', context);
  await assert.rejects(transport('submit', { name: 'Test' }));
  assert.deepEqual(calls, ['/rest/submit']);
});
test('Explicit missing route permits one legacy fallback; 403 and 429 do not', async () => {
  for (const status of [403, 429, 404]) {
    const calls = [];
    const context = { cfg: { restUrl: '/rest/', ajaxUrl: '/ajax/' }, URLSearchParams, getCid: () => 'test', AbortController, setTimeout, clearTimeout,
      fetch: async (url) => { calls.push(url); return { ok: false, status, json: async () => ({ code: status === 404 ? 'rest_no_route' : 'ssc_denied', message: 'Denied' }) }; } };
    const { transport } = load('        function request(', '        function chatRoute(', context);
    await transport('submit', {});
    assert.equal(calls.length, status === 404 ? 2 : 1);
  }
});
test('Markdown answers render lists, headings and code without allowing markup', () => {
  const document = { createElement() { return { textContent: '', get innerHTML() { return this.textContent.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); } }; } };
  const { md } = load('        function esc(', '        function uid(', { document });
  const html = md('### Title\n- one `x<y`\n- two\n\n1. first\n2. second\nSee https://example.org/a.');
  assert.ok(html.includes('<ul><li>one <code>x&lt;y</code></li><li>two</li></ul>'));
  assert.ok(html.includes('<ol><li>first</li><li>second</li></ol>'));
  assert.ok(html.includes('<p class="ssc-md-h"><strong>Title</strong></p>'));
  assert.ok(html.includes('<a href="https://example.org/a"'), 'bare URL linked without trailing period');
  assert.ok(!md('- <script>x</script>').includes('<script'));
});
test('Only real conversation turns are persisted (welcome never duplicates)', () => {
  let stored = null;
  const state = { persist: true, product: null, items: [
    { kind: 'bot', text: 'Welcome', history: false, transient: true },
    { kind: 'bot', text: 'Which one?', history: false },
    { kind: 'user', text: 'Hi', history: true },
    { kind: 'bot', text: 'Hello!', history: true },
  ] };
  const memSet = (k, v) => { if (k === 't') { stored = JSON.parse(v); } };
  const { saveThread } = load('        function saveThread(', '        function loadThread(', { state, memSet, THREAD_KEY: 't', CONV_KEY: 'c', convId: null, JSON, Date });
  saveThread();
  assert.deepEqual(stored.items.map((i) => i.t), ['Hi', 'Hello!']);
});

test('The launcher can close the window it opened', () => {
  // Passing toggleWindow straight to addEventListener hands it the click event,
  // which read as "force open": the launcher opened the chat but never closed it.
  assert.ok(!/addEventListener\(\s*'click'\s*,\s*toggleWindow\s*\)/.test(source), 'toggleWindow must not be a bare event listener');
  assert.match(source, /'boolean' === typeof force/, 'only a real boolean may force a state');
});

test('Text on custom colours is picked by contrast', () => {
  const { inkFor, luminance } = load('        /** Relative luminance', '        /**\n         * Apply the colour');
  assert.equal(inkFor('#ffffff'), '#101322', 'white bubble gets dark text');
  assert.equal(inkFor('#fde047'), '#101322', 'yellow brand colour gets dark text');
  assert.equal(inkFor('#b61615'), '#fff', 'dark red gets white text');
  assert.equal(inkFor('#16203a'), '#fff', 'navy gets white text');
  assert.equal(inkFor('#fff'), '#101322', 'short hex is understood');
  assert.equal(luminance('rgb(1,2,3)'), -1, 'unknown formats are reported, not guessed');
});
