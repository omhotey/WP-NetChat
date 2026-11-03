"""Run CSS delivery guards with PHP CLI only, without loading WordPress or SQL."""
from pathlib import Path
import argparse,base64,hashlib,json,subprocess,sys
R=Path(__file__).resolve().parent.parent
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php',default='php',help='PHP CLI executable (no WordPress bootstrap)')
parser.add_argument('--docker-container',help='Use PHP CLI in this existing container; no container creation')
parser.add_argument('--output-dir',type=Path,default=R/'dist/style-delivery-checks')
args=parser.parse_args()
S=R;M=S/'includes/style-delivery.php'
files={p.name:p.read_text(encoding='utf8')for p in(S/'assets/css').glob('*.css')}
code=(R/'tests/css-delivery-guards.php').read_text(encoding='utf8').replace('/* PAYLOAD */',"$GLOBALS['files']=json_decode(base64_decode('"+base64.b64encode(json.dumps(files).encode()).decode()+"'),true);")
code=code.replace('/* MODULE */',M.read_text(encoding='utf8').replace('<?php','',1))
cmd=['docker','exec','-i',args.docker_container,args.php,'-n']if args.docker_container else[args.php,'-n']
p=subprocess.run(cmd,input=code.encode(),capture_output=True)
if p.returncode or p.stderr:raise SystemExit(p.stdout.decode('utf8','replace')+p.stderr.decode('utf8','replace'))
report=json.loads(p.stdout)
runtime={'netchat.php','uninstall.php','readme.txt','LICENSE','includes/service-presets.json'}
runtime.update(p.relative_to(S).as_posix()for p in(S/'includes').glob('*.php'))
for folder in ['assets','languages']:
 runtime.update(p.relative_to(S).as_posix()for p in(S/folder).rglob('*')if p.is_file()and p.suffix in['.php','.css','.js','.svg','.mo','.po','.pot'])
report['source_sha256']={rel:hashlib.sha256((S/rel).read_bytes()).hexdigest()for rel in sorted(runtime)}
lint=[]
for rel in sorted(runtime):
 if not rel.endswith('.php'):continue
 p=subprocess.run(cmd+['-l'],input=(S/rel).read_bytes(),capture_output=True)
 if p.returncode or p.stderr:raise SystemExit(rel+' '+p.stdout.decode('utf8','replace')+p.stderr.decode('utf8','replace'))
 lint.append(rel)
report['lint_files']=lint
args.output_dir.mkdir(parents=True,exist_ok=True)
(args.output_dir/'php.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({k:v for k,v in report.items()if k not in['render','source_sha256','lint_files']}));print('lint files',len(lint))
