"""Build an installable ZIP from an explicit allowlist, excluding test data."""
import hashlib
import re
import zipfile
from pathlib import Path

root = Path(__file__).resolve().parents[1]
version = re.search(r"define\( 'SSC_CHATBOT_VERSION', '([^']+)'", (root / 'smart-support-chatbot.php').read_text(encoding='utf-8')).group(1)
output = root / 'dist'
output.mkdir(exist_ok=True)
archive = output / f'nexachat-ai-{version}.zip'
files = [root / name for name in ('smart-support-chatbot.php', 'index.php', 'uninstall.php', 'readme.txt')]
for directory in ('assets', 'includes', 'widgets', 'blocks', 'languages', 'docs'):
    files.extend(p for p in (root / directory).rglob('*') if p.is_file())
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
    for path in sorted(files):
        bundle.write(path, 'smart-support-chatbot/' + path.relative_to(root).as_posix())
with zipfile.ZipFile(archive) as bundle:
    assert bundle.testzip() is None
    assert all(name.startswith('smart-support-chatbot/') and '..' not in name for name in bundle.namelist())
    assert not any('/tests/' in name or 'wp-config' in name or '/dist/' in name for name in bundle.namelist())
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix('.zip.sha256').write_text(f'{digest}  {archive.name}\n', encoding='ascii')
print(f'{archive}\n{len(files)} files, {archive.stat().st_size} bytes\nSHA256 {digest}')
