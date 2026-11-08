// Arabic text layer (SEO slice 0 step 0.1, F3): the JS twin of app/Support/Arabic/Normaliser.php.
// Same function names, same results (parity test: tests/arabic/parity.mjs). The rules are passed in from
// resources/arabic/normalise.json (the only copy); key() is a search key only, never shown to users.

export const DIALECTS = ['gulf', 'levant', 'egypt', 'maghreb'];

export function createNormaliser(rules) {
    const tashkeel = rules.strip.tashkeel_ranges.map(([a, b]) => `\\u${a}-\\u${b}`).join('');
    const tashkeelRe = new RegExp(`[${tashkeel}]`, 'gu');
    const tashkeelTest = new RegExp(`[${tashkeel}]`, 'u');
    const digitSets = ['eastern', 'persian'].map((set) => Array.from(rules.digits[set]));
    let cityIndex = null;

    const unique = (list) => Array.from(new Set(list));
    const hasPhrase = (text, phrase) => phrase !== '' && ` ${text} `.includes(` ${phrase} `);
    const replacePhrase = (text, phrase, withText) => ` ${text} `.split(` ${phrase} `).join(` ${withText} `).trim();
    const mapLetters = (text) => Array.from(text).map((ch) => rules.letter_maps[ch] ?? ch).join('');

    function key(text) {
        let t = text.toLowerCase().replace(tashkeelRe, '').split(rules.strip.tatweel).join('');
        t = mapLetters(t);
        for (const digits of digitSets) {
            digits.forEach((d, i) => { t = t.split(d).join(String(i)); });
        }

        return t.replace(/\s+/gu, ' ').trim();
    }

    function arabiziToken(chars, i) {
        const two = chars[i] + (chars[i + 1] ?? '');
        if (rules.arabizi_map[two] !== undefined) { return [[].concat(rules.arabizi_map[two]), 2]; }
        if (rules.arabizi_map[chars[i]] !== undefined) { return [[].concat(rules.arabizi_map[chars[i]]), 1]; }
        if (two.length > chars[i].length && rules.arabizi_letters[two] !== undefined) { return [rules.arabizi_letters[two], 2]; }
        if (i === chars.length - 1 && i > 0 && rules.arabizi_final[chars[i]] !== undefined) { return [rules.arabizi_final[chars[i]], 1]; }
        if (i === 0 && ['a', 'e', 'i', 'o', 'u'].includes(chars[i])) { return [['ا'], 1]; }
        if (rules.arabizi_letters[chars[i]] !== undefined) { return [rules.arabizi_letters[chars[i]], 1]; }

        return [[chars[i]], 1];
    }

    function arabiziToArabic(text) {
        const max = Number(rules.arabizi_max_candidates);
        let results = [''];
        for (const word of text.trim().toLowerCase().split(/\s+/u).filter(Boolean)) {
            let forms = [''];
            const chars = Array.from(word);
            let i = 0;
            while (i < chars.length) {
                const [options, len] = arabiziToken(chars, i);
                const next = [];
                for (const f of forms) { for (const o of options) { next.push(f + o); } }
                forms = unique(next).slice(0, max);
                i += len;
            }
            const next = [];
            for (const r of results) { for (const f of forms) { next.push(r === '' ? f : `${r} ${f}`); } }
            results = unique(next).slice(0, max);
        }

        return results.filter((s) => s !== '');
    }

    function variants(term, options = {}) {
        term = term.replace(/\s+/gu, ' ').trim();
        const out = new Map();
        const add = (text, type) => { if (text !== term && !out.has(text)) { out.set(text, { text, type, status: 'candidate' }); } };

        add(mapLetters(term.split(rules.strip.tatweel).join('').replace(tashkeelRe, '')), 'spelling');
        for (const [city, spellings] of Object.entries(rules.city_spellings)) {
            const forms = [city, ...spellings];
            for (const form of forms) {
                if (hasPhrase(term, form)) { for (const other of forms) { add(replacePhrase(term, form, other), 'spelling'); } }
            }
        }
        const dialects = options.dialects ?? DIALECTS;
        for (const [slot, msa] of Object.entries(rules.msa_question_words)) {
            if (!hasPhrase(term, msa)) { continue; }
            for (const d of dialects) {
                const word = rules.dialect_question_words[d]?.[slot];
                if (word !== undefined) { add(replacePhrase(term, msa, word), 'dialect'); }
            }
        }
        if (/[a-z0-9]/i.test(term) && !/\p{Script=Arabic}/u.test(term)) {
            for (const c of arabiziToArabic(term)) { add(c, 'arabizi'); }
        }

        return Array.from(out.values());
    }

    function dialectTags(text) {
        const words = key(text).split(' ');

        return DIALECTS.filter((d) => rules.dialect_markers[d].some((m) => words.includes(key(m))));
    }

    function getCityIndex() {
        if (cityIndex === null) {
            const entries = [];
            for (const [code, cities] of Object.entries(rules.cities)) {
                for (const city of cities) {
                    if (rules.ambiguous_places[city] !== undefined) { continue; }
                    for (const form of [city, ...(rules.city_spellings[city] ?? [])]) { entries.push([key(form), [city, code]]); }
                }
            }
            const index = new Map(entries);
            cityIndex = Array.from(index.entries()).sort((x, y) => (Array.from(y[0]).length - Array.from(x[0]).length) || (x[0] < y[0] ? -1 : x[0] > y[0] ? 1 : 0));
        }

        return cityIndex;
    }

    function cityOf(text, country = null) {
        for (const [plain, amb] of Object.entries(rules.ambiguous_places)) {
            if (!hasPhrase(key(text), key(plain))) { continue; }
            const raw = text.replace(/\s+/gu, ' ');
            if (raw.includes(Array.from(plain)[0] + amb.country_mark)) { return null; }
            if (raw.includes(amb.city_mark) || country === amb.city[1]) { return { city: amb.city[0], country: amb.city[1] }; }

            return null;
        }
        const k = key(text);
        for (const [form, [city, code]] of getCityIndex()) {
            if (hasPhrase(k, form)) { return { city, country: code }; }
        }

        return null;
    }

    function placeTags(text, country) {
        const tags = [];
        let k = key(text);
        for (const [plain, amb] of Object.entries(rules.ambiguous_places)) {
            if (!hasPhrase(k, key(plain))) { continue; }
            if (text.includes(Array.from(plain)[0] + amb.country_mark)) { tags.push(amb.country); }
            else if (text.includes(amb.city_mark)) { tags.push(amb.city[1]); }
            else if (country === amb.city[1] || country === amb.country) { tags.push(country); }
            else { tags.push(`?${key(plain)}`); }
            k = k.split(key(plain)).join(' ').trim();
        }
        for (const [form, [, code]] of getCityIndex()) { if (hasPhrase(k, form)) { tags.push(code); } }
        for (const [code, names] of Object.entries(rules.countries)) {
            for (const name of names) { if (hasPhrase(k, key(name))) { tags.push(code); } }
        }

        return unique(tags).sort((x, y) => (x < y ? -1 : x > y ? 1 : 0));
    }

    function sameKeyword(a, b, country = null) {
        if (key(a) !== key(b)) { return false; }
        const ta = placeTags(a, country);
        const tb = placeTags(b, country);

        return ta.length === 0 || tb.length === 0 || ta.join('|') === tb.join('|');
    }

    function tashkeelRatio(text) {
        const words = text.trim().split(/\s+/u).filter((w) => /[ء-ي]/u.test(w));
        if (words.length === 0) { return 0; }
        const marked = words.filter((w) => tashkeelTest.test(w)).length;

        return Math.round((marked / words.length) * 10000) / 10000;
    }

    return { key, variants, arabiziToArabic, dialectTags, cityOf, sameKeyword, tashkeelRatio };
}
