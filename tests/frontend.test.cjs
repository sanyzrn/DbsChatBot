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
test('Conversation ids are 128-bit hex, reused within a tab and replaced on reset', () => {
  const store = {};
  const sessionStorage = { getItem: (k) => store[k] || null, setItem: (k, v) => { store[k] = v; }, removeItem: (k) => { delete store[k]; } };
  const window = { crypto: require('node:crypto').webcrypto };
  const ctx = load('        var CONV_KEY', '        /* ------------------------------------------------------------------ *\n         * DOM construction', { state: { persist: true }, sessionStorage, window, Uint8Array, Math });
  const first = ctx.getConv();
  assert.match(first, /^[a-f0-9]{32}$/);
  assert.equal(ctx.getConv(), first);
  ctx.resetConv();
  assert.notEqual(ctx.getConv(), first);
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
  const sessionStorage = { setItem(k, v) { stored = JSON.parse(v); } };
  const { saveThread } = load('        function saveThread(', '        function loadThread(', { state, sessionStorage, THREAD_KEY: 't', JSON, Date });
  saveThread();
  assert.deepEqual(stored.items.map((i) => i.t), ['Hi', 'Hello!']);
});
