"""Build the bundled Persian gettext catalog without third-party dependencies."""
import json
import struct
from pathlib import Path

root = Path(__file__).resolve().parents[1] / 'languages'
messages = json.loads((root / 'fa_IR.json').read_text(encoding='utf-8'))
messages[''] = 'Project-Id-Version: NexaChatAI\nLanguage: fa_IR\nContent-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=2; plural=(n > 1);\n'
ordered = sorted(messages)
originals = [key.encode('utf-8') for key in ordered]
translated = [messages[key].encode('utf-8') for key in ordered]
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
(root / 'smart-support-chatbot-fa_IR.mo').write_bytes(mo)
quote = lambda value: json.dumps(value, ensure_ascii=False)
(root / 'smart-support-chatbot-fa_IR.po').write_text('\n\n'.join('msgid ' + quote(key) + '\nmsgstr ' + quote(messages[key]) for key in ordered) + '\n', encoding='utf-8')
print(f'Built {len(messages)-1} Persian translations.')
