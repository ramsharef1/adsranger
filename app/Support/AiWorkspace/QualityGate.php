<?php

namespace App\Support\AiWorkspace;

/**
 * AI Workspace M1 item 5 (spec v2 §4, v1 §11.3–11.4): `check_output`, the same gate for every path (connector, in-app box,
 * night shift). M1 families: Q1 numbers, Q2 Arabic, Q3 claims, Q5 structure, Q9 integrity (a pass / fail gate). Safe
 * fixes are applied, the rest are suggested. Score 0–100 over the families checked in M1 (Q4, Q6–Q8 come in M2 / M3).
 * Bands: ≥ 85 ready · 70–84 needs a look · < 70 not for clients; any blocking failure means not for clients.
 */
class QualityGate
{
    /** Points per family (v1 §11.4). Only the families built in M1 count; the score is scaled to 100 over them. */
    public const WEIGHTS = ['Q1' => 30, 'Q2' => 20, 'Q3' => 20, 'Q5' => 10];

    /** Arabic-script platform names → Latin (GB-03, ADSR-030). */
    protected const PLATFORMS = ['جوجل' => 'Google', 'غوغل' => 'Google', 'فيسبوك' => 'Facebook', 'فيس بوك' => 'Facebook', 'ميتا' => 'Meta',
        'إنستغرام' => 'Instagram', 'انستغرام' => 'Instagram', 'انستقرام' => 'Instagram', 'تيك توك' => 'TikTok', 'تيكتوك' => 'TikTok',
        'سناب شات' => 'Snapchat', 'سنابشات' => 'Snapchat', 'يوتيوب' => 'YouTube', 'لينكد إن' => 'LinkedIn', 'واتساب' => 'WhatsApp', 'واتس اب' => 'WhatsApp'];

    /** Claims that are never sent (CR-02 / CR-04 / UX-207). Removable words are auto-fixed; names are not. */
    protected const CLAIM_WORDS = '/\b(guaranteed?|guarantees)\b|مضمونة?|نضمن/iu';

    protected const COMPETITORS = '/semrush|ahrefs|\bmoz\b|similarweb|ubersuggest|spyfu|سيمراش|أهرفس/iu';

    protected const PROMISES = '/(?:^|\s)#1\b|\b(number one|skyrocket|overnight results)\b|الأول في المنطقة|نتائج فورية|ضمان (النتائج|الترتيب)/iu';

    protected const INJECTION = '/ignore (all |any )?(previous|prior|above) (instructions|rules)|disregard (the )?(rules|instructions)|system prompt|you are now|تجاهل (كل |جميع )?(التعليمات|القواعد)|<\s*script|javascript:|on(error|load)\s*=/iu';

    /**
     * @param  list<array{label: string, value: float|int, source?: string, date?: string}>  $facts  numbers our tools returned for this client and period
     * @param  array{type?: ?string, output?: ?array, client_facing?: bool, allowed_client_ids?: ?list<int>, client_id?: ?int}  $opts
     * @return array{text: string, score: int, band: string, blocking: bool, client_facing: bool, families: array<string, int>, checks: list<array{family: string, rule: string, found: string, fix: string}>, fixed: int, suggested: int}
     */
    public static function check(string $text, array $facts = [], array $opts = []): array
    {
        $checks = [];
        $blocking = false;
        $loss = array_fill_keys(array_keys(self::WEIGHTS), 0.0);
        $add = function (string $family, string $rule, string $found, string $fix, float $cost = 0.0, bool $blocks = false) use (&$checks, &$loss, &$blocking) {
            $checks[] = ['family' => $family, 'rule' => $rule, 'found' => mb_substr($found, 0, 120), 'fix' => $fix];
            if (isset($loss[$family])) {
                $loss[$family] = min(1.0, $loss[$family] + $cost);
            }
            $blocking = $blocking || $blocks;
        };

        // Q9 integrity first: output is data, never instructions or markup.
        if (preg_match(self::INJECTION, $text, $m)) {
            $add('Q9', 'instructions_in_output', $m[0], 'none', 0, true);
        }
        if ($text !== strip_tags($text)) {
            $text = trim(strip_tags($text));
            $add('Q9', 'markup_removed', 'HTML', 'auto');
        }
        $allowed = $opts['allowed_client_ids'] ?? null;
        if ($allowed !== null && isset($opts['client_id']) && ! in_array((int) $opts['client_id'], array_map('intval', $allowed), true)) {
            $add('Q9', 'client_out_of_scope', (string) $opts['client_id'], 'none', 0, true);
        }

        // Q2 Arabic: mechanical rules are fixed, the rest suggested.
        if (preg_match('/[٠-٩]/u', $text)) {
            $text = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => ',']);
            $add('Q2', 'western_digits', '٠-٩', 'auto');
        }
        foreach (self::PLATFORMS as $ar => $latin) {
            if (mb_strpos($text, $ar) !== false) {
                $text = str_replace($ar, $latin, $text);
                $add('Q2', 'platform_in_latin', $ar.' → '.$latin, 'auto');
            }
        }
        if (preg_match_all('/(?<![\x{0600}-\x{06FF}])و(?=[A-Za-z])/u', $text, $mm)) {
            $text = preg_replace('/(?<![\x{0600}-\x{06FF}])و(?=[A-Za-z])/u', 'و ', $text);
            $add('Q2', 'joined_wa', 'و + Latin ×'.count($mm[0]), 'auto');
        }
        if (preg_match_all('/(?<![\x{0600}-\x{06FF}])[بلك]\x{0640}?[A-Za-z]+/u', $text, $mm)) {
            foreach (array_unique($mm[0]) as $hit) {
                $add('Q2', 'joined_letter', $hit, 'suggest', 0.2);
            }
        }
        if (preg_match('/AdsRanger (هو|يقدم|يساعد)(?!\p{Arabic})/u', $text, $m)) {
            $add('Q2', 'brand_feminine', $m[0], 'suggest', 0.2);
        }

        // Q3 claims.
        if (preg_match_all(self::CLAIM_WORDS, $text, $mm)) {
            $text = trim(preg_replace('/\s{2,}/u', ' ', preg_replace(self::CLAIM_WORDS, '', $text)));
            $add('Q3', 'guarantee_removed', implode(', ', array_unique($mm[0])), 'auto');
        }
        if (preg_match(self::COMPETITORS, $text, $m)) {
            $add('Q3', 'competitor_named', $m[0], 'suggest', 1.0, true);
        }
        if (preg_match(self::PROMISES, $text, $m)) {
            $add('Q3', 'overclaim', $m[0], 'suggest', 0.5, true);
        }

        // Q1 numbers: every metric-like number must be one our tools returned.
        foreach (static::numbers($text) as $n) {
            if (! static::known($n, $facts)) {
                $add('Q1', 'number_not_in_data', $n['raw'], 'none', 0.5, true);
            }
        }

        // Q5 structure.
        if (($type = $opts['type'] ?? null) !== null) {
            foreach (OutputContract::validate($type, $opts['output'] ?? null) as $problem) {
                $add('Q5', 'contract', $problem, 'none', 0.34, true);
            }
        }

        $families = [];
        $earned = 0.0;
        foreach (self::WEIGHTS as $f => $w) {
            $families[$f] = (int) round(100 * (1 - $loss[$f]));
            $earned += $w * (1 - $loss[$f]);
        }
        $score = (int) round(100 * $earned / array_sum(self::WEIGHTS));
        $band = $blocking ? 'blocked' : ($score >= 85 ? 'ready' : ($score >= 70 ? 'review' : 'blocked'));

        return ['text' => $text, 'score' => $score, 'band' => $band, 'blocking' => $blocking, 'client_facing' => (bool) ($opts['client_facing'] ?? false),
            'families' => $families, 'checks' => $checks,
            'fixed' => count(array_filter($checks, fn ($c) => $c['fix'] === 'auto')), 'suggested' => count(array_filter($checks, fn ($c) => $c['fix'] === 'suggest'))];
    }

    /**
     * Metric-like numbers: with a %, a currency, a decimal, a thousands comma, or 11 and up. Years, dates and small
     * counts ("3 actions") are left alone.
     *
     * @return list<array{raw: string, value: float, pct: bool, decimals: int}>
     */
    public static function numbers(string $text): array
    {
        $months = 'january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec|يناير|فبراير|مارس|أبريل|مايو|يونيو|يوليو|أغسطس|سبتمبر|أكتوبر|نوفمبر|ديسمبر|محرم|صفر|ربيع|جمادى|رجب|شعبان|رمضان|شوال|ذو القعدة|ذو الحجة';
        $text = preg_replace(['/\b\d{4}-\d{2}-\d{2}\b|\b\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}\b|\b\d{1,2}\/\d{1,2}\b|\b\d{1,2}:\d{2}\b/u',
            '/\b\d{1,2}\s+(?='.$months.')/iu', '/\b(?:'.$months.')\s+\d{1,2}\b(?![.,]\d)/iu'], ' ', $text);
        preg_match_all('/(?<![\w.,])(\d{1,3}(?:,\d{3})+|\d+)(?:\.(\d+))?\s*(%|٪|USD|SAR|AED|JOD|EGP|KWD|QAR|BHD|OMR|MAD|ريال|دولار|دينار|درهم|جنيه)?/u', $text, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $hit) {
            $int = str_replace(',', '', $hit[1]);
            $dec = $hit[2] ?? '';
            $unit = $hit[3] ?? '';
            $value = (float) ($int.($dec !== '' ? '.'.$dec : ''));
            $isYear = $dec === '' && $unit === '' && $value >= 1990 && $value <= 2100 && ! str_contains($hit[1], ',');
            $small = $dec === '' && $unit === '' && ! str_contains($hit[1], ',') && $value <= 10;
            if ($isYear || $small) {
                continue;
            }
            $out[] = ['raw' => trim($hit[0]), 'value' => $value, 'pct' => in_array($unit, ['%', '٪'], true), 'decimals' => strlen($dec)];
        }

        return $out;
    }

    /** A number matches a fact when it equals the fact rounded the way the text rounds it (or within 0.5 %). */
    protected static function known(array $n, array $facts): bool
    {
        foreach ($facts as $f) {
            $v = (float) $f['value'];
            foreach ([$v, abs($v)] as $candidate) {
                if (round($candidate, $n['decimals']) == round($n['value'], $n['decimals'])
                    || ($candidate != 0.0 && abs($candidate - $n['value']) / abs($candidate) <= 0.005)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Arabic + English band label. */
    public static function bandLabel(string $band): string
    {
        return match ($band) {
            'ready' => __('Ready'),
            'review' => __('Needs a look'),
            default => __('Not for clients'),
        };
    }
}
