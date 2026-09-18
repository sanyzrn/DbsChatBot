"""HTTP smoke checks against the disposable, offline demo fixture only.

Requires SSC_WP_TEST_ROOT (SSC_TEST_SITE enabled), local PHP server on 8097,
published plugin, pharma/leads/history/handoff modules, demo product, no AI or
notification delivery. Creates synthetic submissions; never use real reports.
"""
import json
import os
import re
import uuid
from pathlib import Path
from urllib.request import Request, urlopen
from urllib.parse import urlencode
from urllib.error import HTTPError

root = Path(os.environ['SSC_WP_TEST_ROOT'])
config = (root / 'wp-config.php').read_text(encoding='utf-8-sig')
if not re.search(r"define\s*\(\s*['\"]SSC_TEST_SITE['\"]\s*,\s*true\s*\)", config):
    raise SystemExit('Refusing to run without the disposable test-site marker.')
base = 'http://127.0.0.1:8097'
with urlopen(base, timeout=20) as response:
    html = response.read().decode()
match = re.search(r'var SSCChatbotConfig = (\{.*?\});', html)
assert match, 'Published frontend config missing'
cfg = json.loads(match.group(1))
assert not cfg['nonce'], 'Default public frontend must not send a stale/incompatible REST nonce'
cid = 'http-smoke-' + str(uuid.uuid4())

def post(path, data):
    request = Request(base + path, data=urlencode(dict(data, cid=cid)).encode())
    try:
        with urlopen(request, timeout=20) as response:
            return response.status, response.headers, response.read().decode()
    except HTTPError as error:
        return error.code, error.headers, error.read().decode()

adr = dict(type='pharma_adr', name='Synthetic test reporter', phone='۰۹۱۲۳۴۵۶۷۸۹',
           description='Synthetic software test, not a real safety report.',
           product='demo', consent='1', seriousness='["hospitalization"]')
status, _, body = post('/?rest_route=/ssc/v1/submit', adr)
assert status == 200 and json.loads(body)['ok'], (status, body)
status, _, body = post('/wp-admin/admin-ajax.php', dict(adr, action='ssc_chatbot_submit', consent='false'))
assert status == 400 and not json.loads(body)['success'], (status, body)
status, _, body = post('/?rest_route=/ssc/v1/submit', dict(adr, product='unknown'))
assert status == 400, (status, body)
status, _, body = post('/?rest_route=/ssc/v1/chat', dict(message='x' * 2001))
assert status == 400, (status, body)
status, _, body = post('/?rest_route=/ssc/v1/submit', dict(adr, description='x' * 131073))
assert status == 413, (status, body)
status, headers, body = post('/?rest_route=/ssc/v1/chat-stream', dict(message='سلام', product='demo'))
assert status == 200 and 'text/event-stream' in headers['Content-Type'], (status, body)
events = [json.loads(line[6:]) for line in body.splitlines() if line.startswith('data: ')]
assert 'event: done' in body and events[-1]['reply'] and events[-1]['handoff'], body
print('7 HTTP smoke checks passed (synthetic local requests only).')
