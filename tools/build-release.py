"""Standard release entry point: select only the explicitly reviewed version builder."""
from pathlib import Path
import argparse,re,runpy,sys
root=Path(__file__).resolve().parent.parent
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--verify-only',action='store_true')
args=parser.parse_args()
source=(root/'netchat.php').read_text(encoding='utf-8-sig')
header=re.findall(r'^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)\s*$',source,re.M)
constant=re.findall(r"^define\(\s*['\"]NETCHAT_VER['\"]\s*,\s*['\"](\d+\.\d+\.\d+)['\"]\s*\);\s*$",source,re.M)
if len(header)!=1 or header!=constant:raise SystemExit('Missing or inconsistent plugin version')
version=header[0]
if version not in {'1.2.2','1.2.3'}:raise SystemExit('No reviewed builder registered for version '+version)
builder=root/'tools'/('build-release-'+version+'.py')
if not builder.is_file():raise SystemExit('Missing registered release builder: '+builder.name)
if version=='1.2.3':
 if not (root/'includes/style-delivery.php').is_file():raise SystemExit('Missing registered CSS delivery module')
 if not re.search(r"^require_once NETCHAT_DIR \. ['\"]includes/style-delivery\.php['\"];\s*$",source,re.M):raise SystemExit('Missing CSS delivery module require')
if args.verify_only:
 output=root/'dist'/('release-'+version)
 for name in ['netchat-wp-'+version+'.zip','netchat-wp-'+version+'.zip.manifest.json','SHA256SUMS.txt']:
  if not (output/name).is_file():raise SystemExit('Verify-only requires existing artifact: '+name)
sys.argv=[str(builder)]+(['--verify-only']if args.verify_only else[])
runpy.run_path(str(builder),run_name='__main__')
