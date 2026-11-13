<?php

namespace App\Support\Seo;

use App\Models\AeoBrandMonitor;
use App\Models\AeoCitationCheck;
use App\Models\AiKey;
use App\Support\AiWorkspace\AiKill;
use App\Support\AiWorkspace\OwnKey;
use App\Support\Arabic\Normaliser;
use App\Support\Seo\Audit\PageParser;

/**
 * E2-065 AEO / GEO brand citation & AI readiness radar (module seo_audit, off).
 *  - readiness(): deterministic, from our own SafeFetcher requests only: robots.txt rules per AI crawler, /llms.txt,
 *    Organization / FAQPage / Product-or-Service JSON-LD on the home page, and Arabic / English entity parity. A score out
 *    of 100 from fixed weights; it describes readiness, never a citation rate.
 *  - probe(): asks the tracked questions with the workspace's own AI key (#159; OwnKey enforces the monthly cap) and
 *    records whether each answer names the brand or cites the domain. Without probes there is no citation rate at all.
 */
class AeoCitationRadar
{
    /** Readiness weights (sum 100). */
    public const WEIGHTS = ['robots' => 25, 'llms_txt' => 15, 'organization' => 15, 'faq' => 10, 'offering' => 10, 'bilingual' => 10, 'brand_ar' => 5, 'brand_en' => 5, 'org_names' => 5];

    private const OFFERING_TYPES = ['Product', 'Service', 'Offer', 'AggregateOffer', 'ProductGroup'];

    public static function origin(string $domain): ?string
    {
        $d = strtolower(trim($domain));
        $d = preg_replace('#^https?://#', '', $d) ?? $d;
        $d = rtrim(explode('/', $d)[0], '.');

        return preg_match('/^(?=.{4,190}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/', $d) ? 'https://'.$d : null;
    }

    public static function readiness(AeoBrandMonitor $monitor): AeoCitationCheck
    {
        $origin = self::origin($monitor->primary_domain);
        $signals = ['domain' => $origin];
        $met = [];
        if ($origin !== null) {
            // robots.txt: share of AI crawlers allowed on "/" (no robots.txt = all allowed, as crawlers read it).
            $robots = SafeFetcher::fetchResource($origin.'/robots.txt', ['plain', 'text', 'html']);
            $groups = ($robots['status'] ?? null) === 200 ? AiCrawlerChecker::parse((string) $robots['body']) : [];
            $allowed = [];
            $blocked = [];
            foreach (array_keys(AiCrawlerChecker::BOTS) as $bot) {
                [$ok] = AiCrawlerChecker::allowed($groups, $bot, '/');
                $ok ? $allowed[] = $bot : $blocked[] = $bot;
            }
            $signals['robots'] = ['status' => $robots['status'] ?? null, 'allowed' => $allowed, 'blocked' => $blocked];
            $met['robots'] = count($allowed) / max(1, count(AiCrawlerChecker::BOTS));

            $llms = SafeFetcher::fetchResource($origin.'/llms.txt', ['plain', 'text', 'markdown']);
            $met['llms_txt'] = ($llms['status'] ?? null) === 200 && trim((string) $llms['body']) !== '' && ! str_contains(strtolower(substr((string) $llms['body'], 0, 200)), '<html');
            $signals['llms_txt'] = $met['llms_txt'];

            $home = SafeFetcher::fetch($origin.'/');
            $html = (string) ($home['html'] ?? '');
            $signals['home_status'] = $home['status'] ?? null;
            $page = $html !== '' ? PageParser::parse($html, $origin.'/') : null;
            $types = [];
            $orgNames = [];
            foreach ($page['json_ld'] ?? [] as $block) {
                self::collect(json_decode($block, true), $types, $orgNames);
            }
            $types = array_values(array_unique($types));
            $signals['schema_types'] = array_slice($types, 0, 20);
            $met['organization'] = $orgNames !== [] || (bool) array_filter($types, fn ($t) => self::isOrganization($t));
            $met['faq'] = in_array('FAQPage', $types, true);
            $met['offering'] = (bool) array_intersect($types, self::OFFERING_TYPES);

            // Arabic / English parity: the home page and its other-language version (hreflang), brand names on each.
            $lang = substr(strtolower((string) ($page['lang'] ?? '')), 0, 2);
            $pages = [$lang ?: 'ar' => $page];
            foreach ($page['hreflang'] ?? [] as $alt) {
                $altLang = substr(strtolower($alt['lang']), 0, 2);
                if (in_array($altLang, ['ar', 'en'], true) && ! isset($pages[$altLang]) && PageParser::sameHost($alt['href'], $origin)) {
                    $res = SafeFetcher::fetch($alt['href']);
                    $pages[$altLang] = ! empty($res['html']) ? PageParser::parse((string) $res['html'], $alt['href']) : null;
                }
            }
            $met['bilingual'] = ! empty($pages['ar']) && ! empty($pages['en']);
            $met['brand_ar'] = self::named($pages['ar'] ?? null, (string) $monitor->brand_name_ar);
            $met['brand_en'] = self::named($pages['en'] ?? null, (string) $monitor->brand_name_en);
            $names = implode(' | ', $orgNames);
            $met['org_names'] = $orgNames !== [] && self::contains($names, (string) $monitor->brand_name_ar) && self::contains($names, (string) $monitor->brand_name_en);
            $signals['org_names'] = array_slice($orgNames, 0, 6);
            $signals['languages'] = array_keys(array_filter($pages));
        }

        $score = 0.0;
        foreach (self::WEIGHTS as $key => $weight) {
            $value = $met[$key] ?? false;
            $score += $weight * (is_float($value) ? $value : ($value ? 1 : 0));
            $signals['met'][$key] = is_float($value) ? round($value, 2) : (bool) $value;
        }

        return AeoCitationCheck::create([
            'monitor_id' => $monitor->id, 'source' => 'static_readiness', 'readiness_score' => (int) round($score),
            'signals' => $signals, 'citations' => null, 'checked_at' => now(),
        ]);
    }

    /**
     * Asks each tracked question with the workspace's own key. Stops at the first refusal (cap, billing, provider error).
     */
    public static function probe(AeoBrandMonitor $monitor, AiKey $key): AeoCitationCheck
    {
        $prompts = array_slice(array_values(array_filter(array_map('trim', (array) $monitor->tracked_prompts))), 0, (int) config('seo_audit.aeo.max_prompts_per_probe', 5));
        $domain = (string) parse_url((string) self::origin($monitor->primary_domain), PHP_URL_HOST);
        $results = [];
        $cost = 0.0;
        $error = null;
        $system = 'Answer the user\'s question the way you normally would for someone in the Arab world, in the question\'s language. '
            .'Return only a JSON object: {"answer": string, "brands": [brand or business names you recommend, in order], "sources": [URLs you would cite]}.';
        foreach ($prompts as $prompt) {
            if (AiKill::off('ai_box')) {
                $error = 'paused';
                break;
            }
            $r = OwnKey::complete($key, $system, mb_substr($prompt, 0, 300), true, (int) config('seo_audit.aeo.max_output_tokens', 600));
            $cost += (float) $r['cost'];
            if (! $r['ok']) {
                $error = (string) ($r['error'] ?? 'error');
                break;
            }
            $json = OwnKey::json((string) $r['text']);
            $answer = is_array($json) ? (string) ($json['answer'] ?? '') : (string) $r['text'];
            $brands = is_array($json) ? array_values(array_filter(array_map('strval', (array) ($json['brands'] ?? [])))) : [];
            $sources = is_array($json) ? array_values(array_filter(array_map('strval', (array) ($json['sources'] ?? [])))) : [];
            preg_match_all('#https?://[^\s"\'<>)]+#i', $answer, $inline);
            $urls = array_values(array_unique(array_merge($sources, $inline[0])));

            $position = null;
            foreach ($brands as $i => $b) {
                if (self::contains($b, (string) $monitor->brand_name_ar) || self::contains($b, (string) $monitor->brand_name_en)) {
                    $position = $i + 1;
                    break;
                }
            }
            $mentioned = $position !== null || self::contains($answer, (string) $monitor->brand_name_ar) || self::contains($answer, (string) $monitor->brand_name_en);
            $cited = $domain !== '' && (bool) array_filter($urls, fn ($u) => PageParser::sameHost($u, 'https://'.$domain));
            $results[] = [
                'prompt' => mb_substr($prompt, 0, 300), 'mentioned' => $mentioned, 'position' => $position, 'domain_cited' => $cited,
                'urls' => array_slice($urls, 0, 5), 'excerpt' => mb_substr(trim($answer), 0, 300),
            ];
        }

        return AeoCitationCheck::create([
            'monitor_id' => $monitor->id, 'source' => 'byo_probe', 'readiness_score' => null,
            'signals' => ['provider' => $key->provider, 'model' => $key->model, 'asked' => count($prompts), 'answered' => count($results), 'cost_usd' => round($cost, 4), 'error' => $error, 'web_search' => false],
            'citations' => $results, 'checked_at' => now(),
        ]);
    }

    /**
     * Citation numbers exist only for answered probes.
     *
     * @return array{answered: int, mentioned: int, cited: int}|null
     */
    public static function rates(?AeoCitationCheck $probe): ?array
    {
        $rows = (array) ($probe?->citations ?? []);
        if ($probe === null || $rows === []) {
            return null;
        }

        return ['answered' => count($rows), 'mentioned' => count(array_filter($rows, fn ($r) => ! empty($r['mentioned']))), 'cited' => count(array_filter($rows, fn ($r) => ! empty($r['domain_cited'])))];
    }

    /** @param  list<string>  $types @param  list<string>  $orgNames */
    private static function collect(mixed $node, array &$types, array &$orgNames): void
    {
        if (! is_array($node)) {
            return;
        }
        $nodeTypes = array_map('strval', (array) ($node['@type'] ?? []));
        foreach ($nodeTypes as $t) {
            $types[] = $t;
        }
        if (array_filter($nodeTypes, fn ($t) => self::isOrganization($t))) {
            foreach (array_merge((array) ($node['name'] ?? []), (array) ($node['alternateName'] ?? [])) as $n) {
                if (is_string($n) && trim($n) !== '') {
                    $orgNames[] = trim($n);
                }
            }
        }
        foreach ($node as $key => $child) {
            if (is_array($child) && $key !== '@context') {
                self::collect($child, $types, $orgNames);
            }
        }
    }

    private static function isOrganization(string $type): bool
    {
        return str_ends_with($type, 'Organization') || str_ends_with($type, 'Business') || in_array($type, ['Corporation', 'Store', 'OnlineStore', 'Restaurant', 'Brand'], true);
    }

    private static function named(?array $page, string $brand): bool
    {
        return $page !== null && trim($brand) !== '' && self::contains(($page['title'] ?? '').' '.implode(' ', $page['h1s'] ?? []).' '.($page['text'] ?? ''), $brand);
    }

    /** Arabic-aware containment (hamza, taa marbuta, tashkeel folded by the Normaliser); Latin case-insensitive. */
    public static function contains(string $haystack, string $needle): bool
    {
        $needle = trim($needle);
        if ($needle === '') {
            return false;
        }
        if (preg_match('/\p{Arabic}/u', $needle)) {
            $k = Normaliser::key($needle);

            return $k !== '' && str_contains(Normaliser::key($haystack), $k);
        }

        return str_contains(mb_strtolower($haystack), mb_strtolower($needle));
    }
}
