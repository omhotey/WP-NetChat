from pathlib import Path, PurePosixPath
import hashlib, io, json, re, zipfile
ROOT = Path(__file__).resolve().parent.parent
OUTPUT = ROOT / 'dist/release-1.2.2'
NAME = 'netchat-wp-1.2.2.zip'
EXPECTED = 'd485bfadf9dec04443a2131d4311c7d3c1d9f69d73fdbae5b052e2d026acd160'
def sha(data): return hashlib.sha256(data).hexdigest()
manifest_bytes = (ROOT / 'tools/release-1.2.2.manifest.json').read_bytes()
manifest = json.loads(manifest_bytes)
assert manifest['version'] == '1.2.2' and manifest['plugin_root'] == 'netchat'
assert manifest['archive'] == NAME and manifest['sha256'] == EXPECTED
records = {f['path']: f for f in manifest['files']}
assert len(records) == len(manifest['files']) == 60
actual = {'netchat/' + p for p in ['netchat.php', 'uninstall.php', 'readme.txt', 'LICENSE', 'includes/service-presets.json']}
actual.update('netchat/' + p.relative_to(ROOT).as_posix() for p in (ROOT/'includes').glob('*.php'))
for folder in ['assets', 'languages']:
    actual.update('netchat/' + p.relative_to(ROOT).as_posix() for p in (ROOT/folder).rglob('*') if p.is_file() and p.suffix in ['.php','.css','.js','.svg','.mo','.po','.pot'])
assert actual == set(records), 'Unexpected runtime composition'
assert set(json.loads((ROOT/'includes/service-presets.json').read_text(encoding='utf-8-sig'))) == {'nc_primary_v1','nc_example_v1'}
assert re.search(r'Version:\s+1\.2\.2\b', (ROOT/'netchat.php').read_text(encoding='utf8'))
assert 'Stable tag: 1.2.2' in (ROOT/'readme.txt').read_text(encoding='utf8')
buffer = io.BytesIO()
with zipfile.ZipFile(buffer, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for name in sorted(records):
        parts = PurePosixPath(name).parts
        assert parts[0] == 'netchat' and '..' not in parts
        path = ROOT.joinpath(*parts[1:])
        assert not path.is_symlink() and not any(p.is_symlink() for p in path.parents if p != ROOT.parent)
        data = path.read_bytes()
        assert len(data) == records[name]['size'] and sha(data) == records[name]['sha256'], 'Reviewed runtime changed: ' + name
        entry = zipfile.ZipInfo(name, (2026,10,21,0,0,0))
        entry.compress_type = zipfile.ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        archive.writestr(entry, data)
payload = buffer.getvalue()
assert sha(payload) == EXPECTED, 'Byte reproduction differs; never replace the accepted ZIP'
with zipfile.ZipFile(io.BytesIO(payload)) as archive: assert archive.testzip() is None
OUTPUT.mkdir(parents=True, exist_ok=True)
assert {p.name for p in OUTPUT.glob('*.zip')} <= {NAME}, 'Only one current installer is allowed'
outputs = {NAME:payload, NAME+'.manifest.json':manifest_bytes}
outputs['SHA256SUMS.txt'] = ''.join(sha(data)+'  '+name+'\n' for name,data in outputs.items()).encode('ascii')
for name,data in outputs.items():
    dest = OUTPUT/name
    if dest.exists(): assert dest.read_bytes() == data, 'Refusing to overwrite a different artifact: ' + name
    else:
        with dest.open('xb') as output: output.write(data)
print(json.dumps({'status':'PASS','archive':NAME,'sha256':EXPECTED,'files':60,'root':'netchat/'}))
