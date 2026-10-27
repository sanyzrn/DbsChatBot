"""The marketplace ZIP carries the RTL-Theme licence file; the public ZIP never does."""
import re
import subprocess
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
NAME = 'RTL_License_c5f56181a1bd41bb.php'


def run(*args):
    return subprocess.run([sys.executable, *args], cwd=ROOT, capture_output=True, text=True)


class MarketplaceBuild(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.out = Path(self.tmp.name) / 'out'
        self.license = Path(self.tmp.name) / NAME
        self.license.write_bytes(b'<?php // stand-in for the encoded licence file\n\x00\xff')

    def build(self, *extra):
        return run('tools/build-release.py', '--out', str(self.out), *extra)

    def test_public_zip_has_no_licence_file(self):
        done = self.build()
        self.assertEqual(done.returncode, 0, done.stderr)
        archive = next(self.out.glob('nexachat-ai-*.zip'))
        self.assertFalse(archive.name.endswith('-rtl.zip'))
        self.assertFalse([n for n in zipfile.ZipFile(archive).namelist() if 'RTL_License' in n])
        self.assertEqual(run('tools/verify-release.py', str(archive)).returncode, 0)

    def test_marketplace_zip_carries_the_file_unchanged_next_to_the_main_file(self):
        done = self.build('--rtl-license', str(self.license))
        self.assertEqual(done.returncode, 0, done.stderr)
        archive = next(self.out.glob('nexachat-ai-*-rtl.zip'))
        bundle = zipfile.ZipFile(archive)
        self.assertEqual(bundle.read(f'nexachat-ai/{NAME}'), self.license.read_bytes())
        self.assertIn('nexachat-ai/nexachat-ai.php', bundle.namelist())
        verified = run('tools/verify-release.py', '--rtl', str(archive))
        self.assertEqual(verified.returncode, 0, verified.stderr)

    def test_the_public_check_rejects_a_zip_that_holds_the_licence(self):
        self.build('--rtl-license', str(self.license))
        archive = next(self.out.glob('nexachat-ai-*-rtl.zip'))
        self.assertNotEqual(run('tools/verify-release.py', str(archive)).returncode, 0)

    def test_the_marketplace_check_rejects_a_zip_without_it(self):
        self.build()
        archive = next(self.out.glob('nexachat-ai-*.zip'))
        self.assertNotEqual(run('tools/verify-release.py', '--rtl', str(archive)).returncode, 0)

    def test_a_renamed_or_unknown_licence_file_is_refused(self):
        wrong = Path(self.tmp.name) / 'RTL_License_0000000000000000.php'
        wrong.write_bytes(b'x')
        self.assertNotEqual(self.build('--rtl-license', str(wrong)).returncode, 0, 'the loader names a different file')
        renamed = Path(self.tmp.name) / 'license.php'
        renamed.write_bytes(b'x')
        self.assertNotEqual(self.build('--rtl-license', str(renamed)).returncode, 0)

    def test_the_loader_guards_against_a_missing_ionCube_loader_and_other_php_versions(self):
        main = (ROOT / 'nexachat-ai.php').read_text(encoding='utf-8')
        loader = re.search(r"if \( PHP_VERSION_ID.*?\) \{\n\trequire_once \$nexachatai_rtl_license;", main, re.S)
        self.assertTrue(loader, 'the licence file must be loaded behind a guard')
        for needle in ("extension_loaded( 'ionCube Loader' )", 'is_file(', 'PHP_VERSION_ID >= 80100', 'PHP_VERSION_ID < 80300'):
            self.assertIn(needle, loader.group(0))

    def test_no_licence_file_is_tracked_or_left_in_the_repository_root(self):
        tracked = subprocess.run(['git', 'ls-files'], cwd=ROOT, capture_output=True, text=True).stdout
        self.assertNotIn('RTL_License_', tracked)


if __name__ == '__main__':
    unittest.main()
