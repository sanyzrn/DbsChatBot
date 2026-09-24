"""Build the distributable plugin ZIP from an explicit allowlist.

The archive must contain exactly what a customer installs: no tests, build
tooling, audit material, dependency manifests or internal notes. Everything
is listed positively here, and tools/verify-release.py re-checks the result
independently so a mistake in this file cannot ship silently.

Usage:  python tools/build-release.py
"""

import hashlib
import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SLUG = 'smart-support-chatbot'

# Files shipped from the plugin root.
ROOT_FILES = (
    'smart-support-chatbot.php',
    'index.php',
    'uninstall.php',
    'readme.txt',
    'LICENSE',
)

# Directories shipped in full, minus the exclusions below.
DIRECTORIES = ('assets', 'includes', 'widgets', 'blocks', 'languages', 'docs')

# Paths (relative, POSIX) that never reach a customer even when they sit
# inside a shipped directory.
EXCLUDED_PREFIXES = ('docs/internal/',)
EXCLUDED_SUFFIXES = ('.map', '.log', '.orig', '.rej', '.bak')
EXCLUDED_NAMES = ('.DS_Store', 'Thumbs.db', 'desktop.ini')


def plugin_version() -> str:
    """Read the single source of truth for the version."""
    source = (ROOT / 'smart-support-chatbot.php').read_text(encoding='utf-8')
    match = re.search(r"define\(\s*'SSC_CHATBOT_VERSION',\s*'([^']+)'", source)
    if not match:
        sys.exit('Could not read SSC_CHATBOT_VERSION from the plugin header.')
    return match.group(1)


def is_shipped(relative: str) -> bool:
    name = relative.rsplit('/', 1)[-1]
    if name in EXCLUDED_NAMES or relative.endswith(EXCLUDED_SUFFIXES):
        return False
    return not relative.startswith(EXCLUDED_PREFIXES)


def collect() -> list[Path]:
    files = []
    for name in ROOT_FILES:
        path = ROOT / name
        if not path.is_file():
            sys.exit(f'Required file is missing: {name}')
        files.append(path)
    for directory in DIRECTORIES:
        base = ROOT / directory
        if not base.is_dir():
            sys.exit(f'Required directory is missing: {directory}')
        files.extend(
            path for path in base.rglob('*')
            if path.is_file() and is_shipped(path.relative_to(ROOT).as_posix())
        )
    return sorted(files)


def main() -> None:
    version = plugin_version()
    header = (ROOT / 'smart-support-chatbot.php').read_text(encoding='utf-8')
    declared = re.search(r'^\s*\*\s*Version:\s*(\S+)', header, re.M)
    if declared and declared.group(1) != version:
        sys.exit(
            f'Version mismatch: plugin header says {declared.group(1)}, '
            f'SSC_CHATBOT_VERSION says {version}.'
        )
    stable = re.search(
        r'^Stable tag:\s*(\S+)', (ROOT / 'readme.txt').read_text(encoding='utf-8'), re.M
    )
    if stable and stable.group(1) != version:
        sys.exit(
            f'Version mismatch: readme.txt Stable tag is {stable.group(1)}, '
            f'plugin is {version}.'
        )

    output = ROOT / 'dist'
    output.mkdir(exist_ok=True)
    archive = output / f'nexachat-ai-{version}.zip'
    files = collect()

    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
        for path in files:
            bundle.write(path, f'{SLUG}/' + path.relative_to(ROOT).as_posix())

    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix('.zip.sha256').write_text(
        f'{digest}  {archive.name}\n', encoding='ascii'
    )

    size_kb = archive.stat().st_size / 1024
    print(f'{archive.relative_to(ROOT).as_posix()}')
    print(f'{len(files)} files, {size_kb:.1f} KB')
    print(f'SHA256 {digest}')


if __name__ == '__main__':
    main()
