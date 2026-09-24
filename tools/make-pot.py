"""Regenerate languages/smart-support-chatbot.pot from the source tree.

A dependency-free substitute for `wp i18n make-pot`: it walks the PHP that
ships with the plugin, collects every gettext call for this text domain, and
writes a template with source references and translator comments.

Usage:  python tools/make-pot.py
"""

import re
import sys
from collections import OrderedDict
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DOMAIN = 'smart-support-chatbot'
SCAN = ('includes', 'widgets', 'blocks')
ROOT_FILES = ('smart-support-chatbot.php', 'uninstall.php')

# Functions to collect, mapped to (singular index, plural index, context index).
FUNCTIONS = {
    '__': (0, None, None),
    '_e': (0, None, None),
    'esc_html__': (0, None, None),
    'esc_html_e': (0, None, None),
    'esc_attr__': (0, None, None),
    'esc_attr_e': (0, None, None),
    '_x': (0, None, 1),
    'esc_html_x': (0, None, 1),
    'esc_attr_x': (0, None, 1),
    '_n': (0, 1, None),
    '_nx': (0, 1, 3),
}

CALL = re.compile(r'(?<![\w$>-])(' + '|'.join(map(re.escape, FUNCTIONS)) + r')\s*\(')


def read_php_string(text: str, index: int):
    """Read one PHP string literal starting at `index`; return (value, next index)."""
    quote = text[index]
    if quote not in "'\"":
        return None, index
    out = []
    i = index + 1
    while i < len(text):
        char = text[i]
        if char == '\\':
            nxt = text[i + 1] if i + 1 < len(text) else ''
            if quote == "'":
                out.append(nxt if nxt in ("'", '\\') else '\\' + nxt)
            else:
                out.append({'n': '\n', 't': '\t', 'r': '\r', '"': '"', '\\': '\\', '$': '$'}.get(nxt, '\\' + nxt))
            i += 2
            continue
        if char == quote:
            return ''.join(out), i + 1
        out.append(char)
        i += 1
    return None, index


def split_args(text: str, index: int):
    """Collect the literal-string arguments of a call whose '(' is at `index`."""
    args, depth, i = [], 0, index
    while i < len(text):
        char = text[i]
        if char in "'\"":
            value, nxt = read_php_string(text, i)
            if value is None:
                return args
            if depth == 1:
                # Only a bare literal counts; concatenation is not extractable.
                after = text[nxt:nxt + 40].lstrip()
                before = text[max(0, i - 40):i].rstrip()
                if not after.startswith('.') and not before.endswith('.'):
                    args.append((len(args), value))
                else:
                    args.append((len(args), None))
            i = nxt
            continue
        if char == '(':
            depth += 1
        elif char == ')':
            depth -= 1
            if depth == 0:
                return args
        elif char == ',' and depth == 1:
            pass
        i += 1
    return args


def positional(text: str, index: int):
    """Map comma-separated argument positions to literal values (or None)."""
    values, depth, i, current, buf = {}, 0, index, 0, []
    while i < len(text):
        char = text[i]
        if char in "'\"":
            value, nxt = read_php_string(text, i)
            if value is None:
                break
            before = text[max(0, i - 30):i].rstrip()
            after = text[nxt:nxt + 30].lstrip()
            buf.append(None if (after.startswith('.') or before.endswith('.')) else value)
            i = nxt
            continue
        if char in '([':
            depth += 1
        elif char in ')]':
            depth -= 1
            if depth == 0:
                values[current] = buf[0] if len(buf) == 1 else None
                return values
        elif char == ',' and depth == 1:
            values[current] = buf[0] if len(buf) == 1 else None
            current += 1
            buf = []
        i += 1
    return values


def translator_comment(source: str, call_start: int) -> str:
    """The `translators:` comment immediately preceding a call, if any."""
    head = source[:call_start]
    line_start = head.rfind('\n') + 1
    previous = source[:line_start].rstrip().splitlines()
    if not previous:
        return ''
    last = previous[-1].strip()
    match = re.search(r'translators:\s*(.+?)\s*(?:\*/)?$', last, re.I)
    return match.group(1) if match else ''


def escape(value: str) -> str:
    return (
        value.replace('\\', '\\\\')
        .replace('"', '\\"')
        .replace('\n', '\\n')
        .replace('\t', '\\t')
    )


def collect():
    entries = OrderedDict()
    files = [ROOT / name for name in ROOT_FILES]
    for directory in SCAN:
        files.extend(sorted((ROOT / directory).rglob('*.php')))

    for path in files:
        if not path.is_file():
            continue
        source = path.read_text(encoding='utf-8')
        relative = path.relative_to(ROOT).as_posix()
        for match in CALL.finditer(source):
            name = match.group(1)
            sing_i, plural_i, ctx_i = FUNCTIONS[name]
            args = positional(source, match.end() - 1)
            singular = args.get(sing_i)
            if not singular:
                continue
            # Only this plugin's own domain belongs in the template.
            domain_slot = {'_n': 3, '_nx': 4, '_x': 2, 'esc_html_x': 2, 'esc_attr_x': 2}.get(name, 1)
            if args.get(domain_slot) not in (DOMAIN, None):
                continue
            plural = args.get(plural_i) if plural_i is not None else None
            context = args.get(ctx_i) if ctx_i is not None else None
            line = source[:match.start()].count('\n') + 1
            key = (context, singular, plural)
            entry = entries.setdefault(
                key,
                {'context': context, 'singular': singular, 'plural': plural, 'refs': [], 'comment': ''},
            )
            entry['refs'].append(f'{relative}:{line}')
            if not entry['comment']:
                entry['comment'] = translator_comment(source, match.start())
    return entries


def version() -> str:
    header = (ROOT / 'smart-support-chatbot.php').read_text(encoding='utf-8')
    found = re.search(r"define\(\s*'SSC_CHATBOT_VERSION',\s*'([^']+)'", header)
    return found.group(1) if found else '0.0.0'


def main() -> None:
    entries = collect()
    if not entries:
        sys.exit('No translatable strings found — check the scan paths.')

    stamp = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S+00:00')
    out = [
        '# NexaChatAI translation template.',
        f'# Copyright (C) {datetime.now(timezone.utc).year} DbsStudio',
        '# This file is distributed under the GPLv2 or later.',
        'msgid ""',
        'msgstr ""',
        f'"Project-Id-Version: NexaChatAI {version()}\\n"',
        '"Report-Msgid-Bugs-To: https://github.com/sanyzrn/DbsChatBot/issues\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        f'"POT-Creation-Date: {stamp}\\n"',
        '"X-Generator: tools/make-pot.py\\n"',
        '"X-Domain: smart-support-chatbot\\n"',
        '"Plural-Forms: nplurals=2; plural=(n > 1);\\n"',
        '',
    ]

    for entry in entries.values():
        if entry['comment']:
            out.append(f'#. translators: {entry["comment"]}')
        for ref in sorted(set(entry['refs'])):
            out.append(f'#: {ref}')
        if entry['context']:
            out.append(f'msgctxt "{escape(entry["context"])}"')
        out.append(f'msgid "{escape(entry["singular"])}"')
        if entry['plural']:
            out.append(f'msgid_plural "{escape(entry["plural"])}"')
            out.append('msgstr[0] ""')
            out.append('msgstr[1] ""')
        else:
            out.append('msgstr ""')
        out.append('')

    target = ROOT / 'languages' / f'{DOMAIN}.pot'
    target.write_text('\n'.join(out), encoding='utf-8', newline='\n')
    plurals = sum(1 for e in entries.values() if e['plural'])
    print(f'{target.relative_to(ROOT).as_posix()}: {len(entries)} strings ({plurals} with plurals)')


if __name__ == '__main__':
    main()
