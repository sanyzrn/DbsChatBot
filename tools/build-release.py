"""Build the distributable plugin ZIP from an explicit allowlist.

The archive must contain exactly what a customer installs: no tests, build
tooling, audit material, dependency manifests or internal notes. Everything
is listed positively here, and tools/verify-release.py re-checks the result
independently so a mistake in this file cannot ship silently.

Usage:  python tools/build-release.py
        python tools/build-release.py --rtl-license /path/to/RTL_License_<hash>.php [--out DIR]

The second form builds the ZIP sold on RTL-Theme: the same files plus the
marketplace licence file, placed unchanged in the plugin folder, under the name
nexachat-ai-<version>-rtl.zip. The licence file belongs to that marketplace
product and stays out of the repository and out of the public ZIP.
"""

import argparse
import hashlib
import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SLUG = 'nexachat-ai'

# Files shipped from the plugin root.
ROOT_FILES = (
    'nexachat-ai.php',
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

# Every entry is stamped with this instead of its mtime, so the archive is
# byte-identical across machines and checkouts. The value is arbitrary; it only
# has to be constant and valid for the ZIP format (which cannot store < 1980).
FIXED_TIMESTAMP = (1980, 1, 1, 0, 0, 0)


def plugin_version() -> str:
    """Read the single source of truth for the version."""
    source = (ROOT / 'nexachat-ai.php').read_text(encoding='utf-8')
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


RTL_LICENSE_NAME = re.compile(r'^RTL_License_[0-9a-f]{8,}\.php$')


def main() -> None:
    parser = argparse.ArgumentParser(description='Build the distributable plugin ZIP.')
    parser.add_argument('--rtl-license', metavar='FILE', help='marketplace licence file to include (builds the -rtl ZIP)')
    parser.add_argument('--out', metavar='DIR', help='output directory (default: dist/)')
    args = parser.parse_args()

    license_path = None
    if args.rtl_license:
        license_path = Path(args.rtl_license)
        if not license_path.is_file() or not RTL_LICENSE_NAME.match(license_path.name):
            sys.exit('--rtl-license must point to the unchanged RTL_License_<hash>.php file.')
        loader = (ROOT / 'nexachat-ai.php').read_text(encoding='utf-8')
        if license_path.name not in loader:
            sys.exit(f'nexachat-ai.php does not load {license_path.name}; update the loader to this file name first.')

    version = plugin_version()
    header = (ROOT / 'nexachat-ai.php').read_text(encoding='utf-8')
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

    output = Path(args.out) if args.out else ROOT / 'dist'
    output.mkdir(parents=True, exist_ok=True)
    archive = output / (f'nexachat-ai-{version}-rtl.zip' if license_path else f'nexachat-ai-{version}.zip')
    # (archive name, source path); the licence file sits next to the main plugin file, byte for byte.
    entries = [(f'{SLUG}/' + path.relative_to(ROOT).as_posix(), path) for path in collect()]
    if license_path:
        entries.append((f'{SLUG}/{license_path.name}', license_path))
        entries.sort(key=lambda entry: entry[0])
    files = [path for _, path in entries]

    # Reproducible: the archive must depend only on file names and contents, so
    # that anyone can rebuild a tag and confirm the published checksum. A ZIP
    # normally embeds each file's mtime, which differs between a working copy
    # and a fresh checkout and would make every build produce different bytes.
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
        for name, path in entries:
            info = zipfile.ZipInfo(name, date_time=FIXED_TIMESTAMP)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16  # Regular file, consistent mode.
            bundle.writestr(info, path.read_bytes())

    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix('.zip.sha256').write_text(
        f'{digest}  {archive.name}\n', encoding='ascii'
    )

    size_kb = archive.stat().st_size / 1024
    print(archive.relative_to(ROOT).as_posix() if archive.is_relative_to(ROOT) else archive)
    print(f'{len(files)} files, {size_kb:.1f} KB')
    print(f'SHA256 {digest}')


if __name__ == '__main__':
    main()
