"""Regression checks for the real standard entry point, isolated in disposable file fixtures."""
from pathlib import Path
import argparse,ast,hashlib,json,shutil,subprocess,sys,tempfile
R=Path(__file__).resolve().parent.parent
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--work-dir',type=Path,default=Path(tempfile.gettempdir()))
parser.add_argument('--output-dir',type=Path,default=R/'dist/release-1.2.3/dispatcher-checks')
args=parser.parse_args();args.work_dir.mkdir(parents=True,exist_ok=True);work=args.work_dir.resolve()
module=ast.parse((R/'tools/build-release-1.2.3.py').read_text(encoding='utf8'))
records=next(ast.literal_eval(n.value)for n in module.body if isinstance(n,ast.Assign)and any(isinstance(t,ast.Name)and t.id=='RECORDS'for t in n.targets))
expected='ea60af44187b7d6cd499c007fa364776f054526cff7639d600e4981b24d8fbd3'
checks=[]
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
with tempfile.TemporaryDirectory(prefix='netchat-build-',dir=work)as allocated:
 temp=Path(allocated).resolve();assert temp.is_relative_to(work)and temp!=work
 def fixture(name):
  target=temp/name;target.mkdir()
  for rel in list(records)+['tools/build-release.py','tools/build-release-1.2.3.py','tools/build-release-1.2.2.py']:
   p=target/rel;p.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(R/rel,p)
  return target
 def run(target,*extra,success=True,reason=None):
  p=subprocess.run([sys.executable,str(target/'tools/build-release.py'),*extra],capture_output=True,text=True)
  assert (p.returncode==0)==success,(target.name,p.stdout,p.stderr)
  if reason:assert reason in p.stdout+p.stderr,(target.name,p.stdout,p.stderr)
  checks.append({'scenario':target.name+(' verify'if extra else''),'status':'PASS','expected_success':success})
  return p
 good=fixture('standard_build');run(good);out=good/'dist/release-1.2.3';archive=out/'netchat-wp-1.2.3.zip';assert digest(archive)==expected
 before={p.name:p.read_bytes()for p in out.iterdir()};run(good,'--verify-only');assert before=={p.name:p.read_bytes()for p in out.iterdir()}
 empty=fixture('verify_requires_existing');run(empty,'--verify-only',success=False,reason='Verify-only requires existing artifact');assert not(empty/'dist').exists()
 for scenario,relative,reason in [('missing_module','includes/style-delivery.php','Missing registered CSS delivery module'),('missing_builder','tools/build-release-1.2.3.py','Missing registered release builder')]:
  target=fixture(scenario);(target/relative).unlink();run(target,success=False,reason=reason);assert not(target/'dist').exists()
 target=fixture('missing_require');p=target/'netchat.php';p.write_bytes(p.read_bytes().replace(b"require_once NETCHAT_DIR . 'includes/style-delivery.php';",b'// intentionally removed required module'));run(target,success=False,reason='Missing CSS delivery module require');assert not(target/'dist').exists()
 target=fixture('unknown_version');p=target/'netchat.php';p.write_bytes(p.read_bytes().replace(b'1.2.3',b'9.9.9'));run(target,success=False,reason='No reviewed builder registered');assert not(target/'dist').exists()
 target=fixture('inconsistent_version');p=target/'netchat.php';p.write_bytes(p.read_bytes().replace(b'Version:     1.2.3',b'Version:     1.2.4'));run(target,success=False,reason='Missing or inconsistent plugin version');assert not(target/'dist').exists()
 target=fixture('shadow_source_cannot_hide_root_change')
 for rel in records:
  dest=target/'source'/rel;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(target/rel,dest)
 p=target/'assets/css/style.css';p.write_bytes(p.read_bytes()+b'/* altered actual root */');run(target,success=False,reason='assets/css/style.css');assert not(target/'dist').exists()
 archive.write_bytes(archive.read_bytes()+b'corruption');run(good,'--verify-only',success=False,reason='Refusing different existing artifact')
tested_files=list(records)+['tools/build-release.py','tools/build-release-1.2.3.py','tools/build-release-1.2.2.py','tools/test-release-dispatcher.py']
report={'tested_source_sha256':{rel:digest(R/rel)for rel in sorted(tested_files)},'status':'PASS','checks':checks,'assertions':len(checks),'expected_zip_sha256':expected,'scope':'Temporary copies of runtime and builders only; real source, Git, WordPress and network untouched'}
args.output_dir.mkdir(parents=True,exist_ok=True);(args.output_dir/'dispatcher.json').write_text(json.dumps(report,indent=2),encoding='utf8');print(json.dumps(report))
