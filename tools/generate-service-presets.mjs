import fs from 'node:fs';
const target=new URL('../includes/service-presets.json',import.meta.url);
const forms=JSON.parse(fs.readFileSync(target,'utf8'));
if(Object.keys(forms).join(',')!=='nc_primary_v1,nc_example_v1')throw Error('Only the two neutral public defaults may be generated.');
for(const [id,form] of Object.entries(forms)){if(form.id!==id||!form.fields.length)throw Error('Invalid public default');}
fs.writeFileSync(target,JSON.stringify(forms,null,2)+'\n');
console.log('Canonicalized two neutral public defaults.');
