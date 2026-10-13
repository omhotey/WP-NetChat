const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const validation = require('../assets/js/validation.js');
const examples = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures-normalization.json'), 'utf8'));
for (const example of examples) {
    const result = validation.normalize(example.type, example.raw);
    assert.equal(result.url, example.url, `${example.type}: ${example.raw}`);
    if (example.url) assert.deepEqual(validation.normalize(example.type, result.url), result, 'idempotent');
    else if (example.raw) assert.equal(result.ok, false, 'bad value flagged');
}
console.log(`PASS JS normalization: ${examples.length} shared PHP/JS examples.`);
