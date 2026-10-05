"""Decide what the Release workflow should publish.

The workflow runs for three reasons:

* a person starts it by hand with a tag (``workflow_dispatch``);
* a person pushes a ``v*`` tag;
* CI finished on ``main`` (``workflow_run``): the release then publishes itself,
  but only for a commit whose CI run succeeded, and only when that commit carries
  a plugin version that has not been released yet.

So a merge that does not change the version publishes nothing; a merge that
bumps it (header, constant, Stable tag, the README version badge and a changelog section) is released as
soon as CI is green. Run with the event details in the environment (see main());
decide() is pure so it can be tested without GitHub.
"""
import os
import re
import subprocess
import sys
from pathlib import Path

TAG_PATTERN = re.compile(r'^v\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.\-]+)?$')
ROOT = Path(__file__).resolve().parents[1]


def read_versions(root=ROOT):
    """The version in the plugin header, the constant, readme.txt's Stable tag and the README version badge."""
    plugin = (root / 'nexachat-ai.php').read_text(encoding='utf-8')
    readme = (root / 'readme.txt').read_text(encoding='utf-8')
    header = re.search(r'^\s*\*\s*Version:\s*(\S+)', plugin, re.M)
    constant = re.search(r"define\(\s*'SSC_CHATBOT_VERSION',\s*'([^']+)'", plugin)
    stable = re.search(r'^Stable tag:\s*(\S+)', readme, re.M)
    badge_text = (root / 'README.md').read_text(encoding='utf-8')
    badge = re.search(r'img\.shields\.io/badge/version-([0-9][0-9.]*)-', badge_text)
    return {
        'header': header.group(1) if header else '',
        'constant': constant.group(1) if constant else '',
        'stable': stable.group(1) if stable else '',
        'badge': badge.group(1) if badge else '',
    }


def has_changelog(version, root=ROOT):
    """Does readme.txt have a changelog section for the version (it becomes the release notes)?"""
    readme = (root / 'readme.txt').read_text(encoding='utf-8')
    body = readme.split('== Changelog ==', 1)[-1]
    return re.search(r'^= ' + re.escape(version) + r' =\s*\n(.*?)(?=^= |\Z)', body, re.S | re.M) is not None


def decide(event, ref_type, ref_name, input_tag, versions, existing_tags,
           run_conclusion='', run_event='', run_branch='', changelog=True):
    """Return (publish, tag, reason). Raises ValueError when the request itself is wrong."""
    if event == 'workflow_dispatch':
        tag = (input_tag or '').strip()
        if not TAG_PATTERN.match(tag):
            raise ValueError('"%s" is not a version tag such as v1.2.3.' % tag)
        return True, tag, 'started by hand for %s' % tag
    if event == 'push' and ref_type == 'tag':
        if not TAG_PATTERN.match(ref_name):
            raise ValueError('"%s" is not a version tag such as v1.2.3.' % ref_name)
        return True, ref_name, 'tag %s was pushed' % ref_name
    if event == 'workflow_run':
        if run_conclusion != 'success':
            return False, '', 'CI did not succeed (%s): nothing is published' % (run_conclusion or 'unknown')
        if run_event != 'push' or run_branch != 'main':
            return False, '', 'the CI run was not a push to main: nothing is published'
        version = versions.get('constant', '')
        if not version:
            raise ValueError('SSC_CHATBOT_VERSION was not found in nexachat-ai.php.')
        tag = 'v' + version
        if tag in existing_tags:
            return False, tag, 'version %s is already released: nothing to publish' % version
        # A new version: it has to be complete before anything is published.
        problems = []
        if versions.get('header') != version:
            problems.append('the plugin header says %s' % (versions.get('header') or 'nothing'))
        if versions.get('stable') != version:
            problems.append('readme.txt "Stable tag" says %s' % (versions.get('stable') or 'nothing'))
        if versions.get('badge') != version:
            problems.append('the README version badge says %s' % (versions.get('badge') or 'nothing'))
        if not changelog:
            problems.append('readme.txt has no "= %s =" changelog section' % version)
        if problems:
            raise ValueError('Version %s is new but not ready to release: %s.' % (version, '; '.join(problems)))
        return True, tag, 'CI passed on main and %s is a new version' % version
    return False, '', 'nothing to do for the event "%s"' % event


def existing_tags():
    out = subprocess.run(['git', 'tag', '--list', 'v*'], cwd=ROOT, capture_output=True, text=True, check=True)
    return set(out.stdout.split())


def main():
    env = os.environ.get
    versions = read_versions()
    try:
        publish, tag, reason = decide(
            env('EVENT_NAME', ''), env('REF_TYPE', ''), env('REF_NAME', ''), env('INPUT_TAG', ''),
            versions, existing_tags(),
            env('RUN_CONCLUSION', ''), env('RUN_EVENT', ''), env('RUN_BRANCH', ''),
            has_changelog(versions.get('constant', '')) if versions.get('constant') else False,
        )
    except ValueError as error:
        print('::error::%s' % error)
        return 1
    print('%s: %s' % ('Publishing ' + tag if publish else 'Not publishing', reason))
    out = env('GITHUB_OUTPUT')
    if out:
        with open(out, 'a', encoding='utf-8') as handle:
            handle.write('publish=%s\ntag=%s\n' % ('true' if publish else 'false', tag))
    return 0


if __name__ == '__main__':
    sys.exit(main())
