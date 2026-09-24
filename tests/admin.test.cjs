/**
 * Admin UI regressions.
 *
 * Both behaviours here shipped broken and produced silent data loss rather
 * than a visible error, so they are pinned by test:
 *   1. Relative selectors ("> .row") throw SyntaxError in every browser, which
 *      killed the "Add row" buttons for knowledge, products and form fields.
 *   2. A repeated row must never reuse an index that is still in the form.
 */
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/admin.js'), 'utf8').replace(/\r\n/g, '\n');

function load(from, to, context = {}) {
  vm.createContext(context);
  vm.runInContext(source.slice(source.indexOf(from), source.indexOf(to, source.indexOf(from))), context);
  return context;
}

test('Every CSS selector in the admin script is valid on its own', () => {
  // querySelector rejects a leading combinator: ":scope" is mandatory.
  const selectors = [...source.matchAll(/querySelectorAll?\(\s*'([^']*)'/g)].map((m) => m[1]);
  const fromHelpers = [...source.matchAll(/\$\$?\(\s*'([^']*)'/g)].map((m) => m[1]);
  const all = [...selectors, ...fromHelpers, ...[...source.matchAll(/ROW_SELECTOR\s*=\s*'([^']*)'/g)].map((m) => m[1])];

  assert.ok(all.length > 10, 'selectors were extracted');
  for (const selector of all) {
    assert.ok(
      !/^\s*[>+~]/.test(selector),
      `"${selector}" starts with a combinator and throws SyntaxError; prefix it with :scope`
    );
  }
});

test('Repeated rows never reuse an index that still exists in the form', () => {
  const { nextIndexFor } = load('	function nextIndexFor(', '	function nextIndex(');

  assert.equal(nextIndexFor([]), 0, 'first row starts at zero');
  assert.equal(nextIndexFor(['ki[0][title]', 'ki[0][content]']), 1, 'sequential rows append');

  // The regression: rows 0 and 2 remain after deleting the middle one. A
  // count-based index would return 2 and clobber the surviving third row.
  const sparse = ['ki[0][title]', 'ki[0][content]', 'ki[2][title]', 'ki[2][content]'];
  assert.equal(nextIndexFor(sparse), 3, 'sparse indexes append past the highest, not the count');

  assert.equal(nextIndexFor(['products[10][id]', 'products[2][id]']), 11, 'out-of-order names still resolve');
  assert.equal(nextIndexFor(['bare_field', 'another']), 0, 'names without an index are ignored');
});

test('A typed model id is materialized as a real option before submit', () => {
  // Assigning an unlisted value to a <select> yields '' (selectedIndex -1),
  // which is how the manual model silently saved as empty.
  const submitHandlers = [];
  const options = [];

  const makeOption = () => {
    const option = { value: '', textContent: '', attrs: {}, setAttribute(k, v) { this.attrs[k] = v; } };
    options.push(option);
    return option;
  };

  const select = {
    value: '__manual__',
    tagName: 'SELECT',
    children: [],
    getAttribute: (name) => (name === 'data-manual' ? '1' : null),
    // Faithful <select> semantics: unknown value => ''.
    querySelector: () => options.find((o) => o.attrs['data-manual-value'] === '1') || null,
    appendChild(option) { this.children.push(option); },
    addEventListener() {},
    closest: (sel) => (sel === 'form' ? form : field),
  };
  Object.defineProperty(select, 'value', {
    get() { return this._value; },
    set(v) { this._value = this.children.some((o) => o.value === v) || v === '__manual__' ? v : ''; },
  });
  select._value = '__manual__';

  const input = { value: '  my-private-model-v2  ', hidden: false, focus() {}, removeAttribute() {}, setAttribute() {} };
  const field = { querySelector: () => input };
  const form = { addEventListener: (event, fn) => { if (event === 'submit') { submitHandlers.push(fn); } } };

  const context = { document: { createElement: makeOption } };
  const { bindManualModel } = load('	function manualInputFor(', '	$$(\'select[data-manual]\')', context);

  bindManualModel(select);
  assert.equal(submitHandlers.length, 1, 'a submit handler was registered');

  submitHandlers[0]();
  assert.equal(select.value, 'my-private-model-v2', 'the trimmed model survives submit');
  assert.equal(select.children.length, 1, 'exactly one synthetic option is added');

  // Submitting twice must reuse the same option rather than stacking them up.
  submitHandlers[0]();
  assert.equal(select.children.length, 1, 'resubmitting reuses the synthetic option');
});
