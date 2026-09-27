"""Build the bundled Persian gettext catalog without third-party dependencies.

languages/fa_IR.json maps msgid -> msgstr. Plural entries use the key
"singular\\u0000plural" and a list of forms as the value.
"""
import json
import struct
from pathlib import Path

root = Path(__file__).resolve().parents[1] / 'languages'
messages = json.loads((root / 'fa_IR.json').read_text(encoding='utf-8'))
header = 'Project-Id-Version: NexaChatAI\nLanguage: fa_IR\nContent-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=2; plural=(n > 1);\n'
messages[''] = header
ordered = sorted(messages)


def encoded(value):
    return ('\0'.join(value) if isinstance(value, list) else value).encode('utf-8')


originals = [key.encode('utf-8') for key in ordered]
translated = [encoded(messages[key]) for key in ordered]
offset = 28 + 16 * len(ordered)
tables, chunks = [], []
for values in (originals, translated):
    table = []
    for value in values:
        table.append(struct.pack('<II', len(value), offset))
        chunks.append(value + b'\0')
        offset += len(value) + 1
    tables.append(b''.join(table))
mo = struct.pack('<7I', 0x950412de, 0, len(ordered), 28, 28 + 8 * len(ordered), 0, 0) + b''.join(tables) + b''.join(chunks)
(root / 'nexachat-ai-fa_IR.mo').write_bytes(mo)

quote = lambda value: json.dumps(value, ensure_ascii=False)
entries = []
for key in ordered:
    value = messages[key]
    if '\0' in key:
        singular, plural = key.split('\0', 1)
        forms = '\n'.join(f'msgstr[{i}] {quote(form)}' for i, form in enumerate(value))
        entries.append(f'msgid {quote(singular)}\nmsgid_plural {quote(plural)}\n{forms}')
    else:
        entries.append(f'msgid {quote(key)}\nmsgstr {quote(value)}')
(root / 'nexachat-ai-fa_IR.po').write_text('\n\n'.join(entries) + '\n', encoding='utf-8')
print(f'Built {len(messages) - 1} Persian translations.')
