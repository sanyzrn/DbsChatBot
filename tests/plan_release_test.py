"""Tests for tools/plan-release.py: which runs publish a release. Run: python tests/plan_release_test.py"""
import importlib.util
import unittest
from pathlib import Path

spec = importlib.util.spec_from_file_location('plan_release', Path(__file__).resolve().parents[1] / 'tools' / 'plan-release.py')
plan = importlib.util.module_from_spec(spec)
spec.loader.exec_module(plan)

GOOD = {'header': '1.7.0', 'constant': '1.7.0', 'stable': '1.7.0'}
TAGS = {'v1.5.0', 'v1.6.0'}


def after_ci(versions=GOOD, tags=TAGS, conclusion='success', event='push', branch='main', changelog=True):
    return plan.decide('workflow_run', 'branch', 'main', '', versions, tags, conclusion, event, branch, changelog)


class PlanRelease(unittest.TestCase):
    def test_new_version_with_green_ci_is_published(self):
        publish, tag, _ = after_ci()
        self.assertTrue(publish)
        self.assertEqual('v1.7.0', tag)

    def test_a_merge_without_a_version_bump_publishes_nothing(self):
        publish, tag, reason = after_ci({'header': '1.6.0', 'constant': '1.6.0', 'stable': '1.6.0'})
        self.assertFalse(publish)
        self.assertIn('already released', reason)

    def test_red_or_cancelled_ci_publishes_nothing(self):
        for conclusion in ('failure', 'cancelled', 'timed_out', ''):
            self.assertFalse(after_ci(conclusion=conclusion)[0], conclusion)

    def test_only_a_push_to_main_counts(self):
        self.assertFalse(after_ci(event='pull_request')[0])
        self.assertFalse(after_ci(branch='ccr-some-branch')[0])

    def test_a_half_finished_bump_fails_loudly_instead_of_publishing(self):
        for versions, words in (
            ({'header': '1.6.0', 'constant': '1.7.0', 'stable': '1.7.0'}, 'plugin header'),
            ({'header': '1.7.0', 'constant': '1.7.0', 'stable': '1.6.0'}, 'Stable tag'),
        ):
            with self.assertRaises(ValueError) as caught:
                after_ci(versions)
            self.assertIn(words, str(caught.exception))
        with self.assertRaises(ValueError) as caught:
            after_ci(changelog=False)
        self.assertIn('changelog', str(caught.exception))

    def test_manual_and_tag_runs_always_publish_their_tag(self):
        self.assertEqual((True, 'v1.6.0'), plan.decide('workflow_dispatch', 'branch', 'main', 'v1.6.0', GOOD, TAGS)[:2])
        self.assertEqual((True, 'v1.7.0'), plan.decide('push', 'tag', 'v1.7.0', '', GOOD, TAGS)[:2])

    def test_a_bad_tag_is_refused(self):
        for bad in ('', 'main', '1.6.0', 'v1.6', 'v1.6.0; rm -rf /', 'v1.6.0\nx'):
            with self.assertRaises(ValueError):
                plan.decide('workflow_dispatch', 'branch', 'main', bad, GOOD, TAGS)
        with self.assertRaises(ValueError):
            plan.decide('push', 'tag', 'vNext', '', GOOD, TAGS)

    def test_other_events_do_nothing(self):
        self.assertFalse(plan.decide('pull_request', 'branch', 'x', '', GOOD, TAGS)[0])

    def test_the_real_repository_is_readable(self):
        versions = plan.read_versions()
        self.assertTrue(versions['constant'] and versions['header'] and versions['stable'])
        self.assertTrue(plan.has_changelog(versions['constant']))


if __name__ == '__main__':
    unittest.main()
