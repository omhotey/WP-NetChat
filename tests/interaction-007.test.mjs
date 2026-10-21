import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {fileURLToPath} from 'node:url';import {spawnSync} from 'node:child_process';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..'),read=p=>fs.readFileSync(path.join(root,p),'utf8');
const phpModules=['includes/leads-settings.php','includes/helpers.php','includes/normalization.php','includes/admin.php','includes/data-transfer.php','includes/leads-frontend.php'];
if(process.argv.includes('--php-bundle')){const modules=phpModules.map(p=>read(p).replace(/^<\?php\s*/,'').replace(/^require_once[^\n]+\n/gm,'')).join('\n');process.stdout.write(read('tests/interaction-007-wp.php').replace('/* LOAD_CANDIDATE */',modules));process.exit(0);}
let checks=0;function check(v,msg){assert.ok(v,msg);checks++;}
for(const p of ['assets/js/admin.js','assets/js/service-forms.js','assets/js/service-forms-admin.js']){const r=spawnSync(process.execPath,['--check',path.join(root,p)],{encoding:'utf8'});check(r.status===0,p+' syntax '+r.stderr);}
const source=read('includes/leads-frontend.php'),service=read('assets/js/service-forms.js'),preview=read('assets/js/service-forms-admin.js');
check(!source.includes('<details>')&&!source.includes('<summary>'),'public alternative markup has no disclosure');
check(source.lastIndexOf("netchat_contact_alternatives('nc-contact-alternatives')")>source.indexOf('</form>'),'contact alternatives after fields');
check(!service.includes("querySelector('details')"),'service has no disclosure dependency');
check(service.includes('root.appendChild(change);alternatives();'),'service alternatives last');
check(preview.lastIndexOf("nc-preview-alternatives")>preview.indexOf('preview.append(actions)'),'preview alternatives after actions');
check(read('includes/admin.php').includes("'form_presentation','form_drawer_side'"),'REST optional keys');
check(read('assets/css/style.css').includes('padding:12px;background:transparent;border:0'),'menu container transparent');
check(read('assets/css/popup.css').includes('@media(prefers-reduced-motion:reduce){#nc-leads-modal[data-form-presentation="drawer"]'),'drawer reduced motion');
check(read('netchat.php').includes("define( 'NETCHAT_VER', '1.2.1' )"),'cache candidate version');
console.log(JSON.stringify({status:'PASS',layer:'source_static',assertions:checks}));
