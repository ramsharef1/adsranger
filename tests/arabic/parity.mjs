// Parity harness for the Arabic text layer (SEO slice 0 step 0.1): prints the JS results for every fixture line
// as JSON, so ArabicNormaliserTest can compare them with the PHP results byte for byte.
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createNormaliser } from '../../resources/js/arabic/normalise.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const rules = JSON.parse(readFileSync(join(root, 'resources/arabic/normalise.json'), 'utf8'));
const n = createNormaliser(rules);
const lines = JSON.parse(readFileSync(0, 'utf8'));
const out = lines.map(([text, country]) => ({
    key: n.key(text),
    variants: n.variants(text),
    arabizi: n.arabiziToArabic(text),
    toArabizi: n.arabicToArabizi(text),
    dialects: n.dialectTags(text),
    city: n.cityOf(text, country || null),
    ratio: n.tashkeelRatio(text).toFixed(4),
}));
const pairs = lines.filter(([, , b]) => b !== undefined).map(([a, country, b]) => n.sameKeyword(a, b, country || null));
process.stdout.write(JSON.stringify({ out, pairs }));
