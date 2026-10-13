const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),forms=JSON.parse(fs.readFileSync(path.join(root,'includes/service-presets.json')));
assert.deepEqual(Object.keys(forms),['nc_primary_v1','nc_example_v1']);
assert.equal(forms.nc_primary_v1.question_steps,1);assert.equal(forms.nc_example_v1.question_steps,2);
const fixture=JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures-demo-forms.json')));assert.equal(Object.keys(fixture).length,7);
const legacyIdentity=/\bys_[a-z0-9_]+\b/;
function walk(dir){for(const e of fs.readdirSync(dir,{withFileTypes:true})){if(['.git','dist','node_modules','results'].includes(e.name))continue;const file=path.join(dir,e.name);if(e.isDirectory())walk(file);else if(/\.(php|js|mjs|cjs|json|md|txt|ya?ml)$/.test(e.name)){const text=fs.readFileSync(file,'utf8');assert.ok(!legacyIdentity.test(text),'Legacy owner identity in '+path.relative(root,file));}}}
walk(root);console.log('PASS distribution: two public defaults, seven synthetic fixtures, private definition scan.');
