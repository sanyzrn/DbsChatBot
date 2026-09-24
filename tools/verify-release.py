"""Independently audit the built ZIP before it reaches a customer.

This deliberately does NOT import the build script: it re-derives what a
correct package looks like, so a mistake in build-release.py is caught rather
than mirrored. Exits non-zero on any finding.

Usage:  python tools/verify-release.py [path/to/plugin.zip]
"""

import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SLUG = 'smart-support-chatbot'

# Nothing matching these may appear anywhere in the archive.
FORBIDDEN_PATTERNS = (
    r'(^|/)tests?/',
    r'(^|/)tools/',
    r'(^|/)chat_audit/',
    r'(^|/)dist/',
    r'(^|/)vendor/',
    r'(^|/)node_modules/',
    r'(^|/)\.git',
    r'(^|/)docs/internal/',
    r'(^|/)composer\.(json|lock)$',
    r'(^|/)phpcs\.xml',
    r'(^|/)package(-lock)?\.json$',
    r'\.(test|spec)\.',
    r'wp-config',
    r'\.(env|pem|key|p12|pfx)$',
    r'\.(map|log|orig|rej|bak)$',
    r'(^|/)(\.DS_Store|Thumbs\.db|desktop\.ini)$',
)

# The plugin cannot install or run without these.
REQUIRED = (
    f'{SLUG}/smart-support-chatbot.php',
    f'{SLUG}/uninstall.php',
    f'{SLUG}/index.php',
    f'{SLUG}/readme.txt',
    f'{SLUG}/LICENSE',
    f'{SLUG}/includes/class-ssc-autoloader.php',
    f'{SLUG}/includes/core/class-ssc-plugin.php',
    f'{SLUG}/assets/js/chatbot.js',
    f'{SLUG}/assets/css/chatbot.css',
    f'{SLUG}/blocks/chatbot/block.json',
    f'{SLUG}/languages/smart-support-chatbot.pot',
)


def newest_archive() -> Path:
    archives = sorted((ROOT / 'dist').glob('nexachat-ai-*.zip'), key=lambda p: p.stat().st_mtime)
    if not archives:
        sys.exit('No archive found in dist/. Run tools/build-release.py first.')
    return archives[-1]


def main() -> None:
    archive = Path(sys.argv[1]) if len(sys.argv) > 1 else newest_archive()
    problems = []

    with zipfile.ZipFile(archive) as bundle:
        if bundle.testzip() is not None:
            problems.append('The archive contains a corrupt entry.')
        names = bundle.namelist()
        infos = bundle.infolist()

    for name in names:
        if not name.startswith(f'{SLUG}/'):
            problems.append(f'Escapes the plugin folder: {name}')
        if '..' in name or name.startswith('/'):
            problems.append(f'Unsafe path: {name}')
        for pattern in FORBIDDEN_PATTERNS:
            if re.search(pattern, name):
                problems.append(f'Development file shipped: {name}  (matched /{pattern}/)')

    for required in REQUIRED:
        if required not in names:
            problems.append(f'Missing required file: {required}')

    # A stray secret is worse than a stray file; scan the text we ship.
    secret_like = re.compile(
        r'(sk-[A-Za-z0-9]{20,}|AIza[0-9A-Za-z_\-]{30,}|-----BEGIN [A-Z ]*PRIVATE KEY-----)'
    )
    with zipfile.ZipFile(archive) as bundle:
        for info in infos:
            if info.file_size > 2_000_000 or not info.filename.endswith(
                ('.php', '.js', '.css', '.json', '.txt', '.md', '.pot', '.po')
            ):
                continue
            body = bundle.read(info.filename).decode('utf-8', 'ignore')
            if secret_like.search(body):
                problems.append(f'Possible credential in {info.filename}')

    total = sum(i.file_size for i in infos)
    print(f'{archive.name}: {len(names)} files, {total / 1024:.1f} KB uncompressed')

    if problems:
        print(f'\n{len(problems)} problem(s):', file=sys.stderr)
        for problem in problems:
            print(f'  - {problem}', file=sys.stderr)
        sys.exit(1)

    print('Package is clean: no development files, no stray credentials, all required files present.')


if __name__ == '__main__':
    main()
