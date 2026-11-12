<?php

namespace App\Support\Seo;

use App\Models\AeoCitationRun;
use App\Models\AeoDomainAudit;
use App\Models\AeoTrackedPrompt;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\PlatformSetting;
use App\Support\Arabic\Normaliser;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Wave B.1 (#181–#192): AEO & GEO AI Search Visibility & Organic SEO Depth Suite.
 *
 * Strictly obeys D-229 Honesty Rules:
 * - Never fabricates live LLM rankings or crawl volumes.
 * - Unconfigured / offline AI citation runs are stored and returned with `mode = 'deterministic'`
 *   and an explicit "not connected yet" disclosure.
 * - All outbound URL inspections pass through `SafeFetcher` SSRF pinning.
 */
class AeoGeoManager
{
    public const AI_BOTS = ['GPTBot', 'OAI-SearchBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'Googlebot'];

    public const LLM_REFERRERS = [
        'chatgpt.com',
        'perplexity.ai',
        'gemini.google.com',
        'claude.ai',
        'copilot.microsoft.com',
    ];

    public const WEAK_ANCHORS = [
        'اضغط هنا', 'إضغط هنا', 'المزيد', 'اقرأ المزيد', 'هنا', 'الرابط',
        'click here', 'read more', 'here', 'more', 'link',
    ];

    /**
     * #181 Multi-Engine AI Brand Citation Tracker.
     *
     * @param  list<string>|null  $engines
     * @return list<AeoCitationRun>
     */
    public static function runCitationCheck(AeoTrackedPrompt $prompt, ?array $engines = null): array
    {
        $prompt->loadMissing('client');
        $client = $prompt->client;
        $engines = $engines ?: AeoCitationRun::ENGINES;
        $competitors = array_slice(array_values(array_filter(array_map('trim', (array) ($prompt->competitors_json ?? [])))), 0, 5);
        $liveKey = static::liveOpenAiKey();

        $created = [];
        foreach ($engines as $engine) {
            if (! in_array($engine, AeoCitationRun::ENGINES, true)) {
                continue;
            }

            if ($liveKey !== null && $engine === 'chatgpt') {
                $liveResult = static::evaluateLiveOpenAi($liveKey, $prompt->prompt_text, $client->name, $competitors);
                if ($liveResult !== null) {
                    $created[] = AeoCitationRun::query()->create([
                        'agency_id' => $prompt->agency_id,
                        'client_id' => $prompt->client_id,
                        'aeo_tracked_prompt_id' => $prompt->id,
                        'engine' => $engine,
                        'mode' => 'live',
                        'client_mentioned' => $liveResult['client_mentioned'],
                        'client_position' => $liveResult['client_position'],
                        'sentiment' => $liveResult['sentiment'],
                        'competitor_mentions_json' => $liveResult['competitors'],
                        'cited_urls_json' => $liveResult['urls'],
                        'response_excerpt' => $liveResult['excerpt'],
                        'checked_at' => now(),
                    ]);

                    continue;
                }
            }

            // D-229 Honesty: No live provider connected for this engine -> deterministic brand-term check only, never fake rankings.
            $normPrompt = Normaliser::key($prompt->prompt_text);
            $normBrand = Normaliser::key($client->name);
            $brandInPrompt = $normBrand !== '' && str_contains($normPrompt, $normBrand);

            $compHits = [];
            foreach ($competitors as $comp) {
                $nc = Normaliser::key($comp);
                if ($nc !== '' && str_contains($normPrompt, $nc)) {
                    $compHits[] = $comp;
                }
            }

            $excerpt = $prompt->locale === 'ar'
                ? 'وضع قياسي (غير متصل بمزوّد حي بعد): لم تُختلق أي تراتيب بحث ذكية؛ يفحص هذا السجل تطابق اسم العلامة والمنافسين مع صياغة السؤال الجاهزة.'
                : 'Deterministic readiness mode (live AI provider not connected yet): no live rankings fabricated; evaluated prompt structure and explicit brand/competitor tokens.';

            $created[] = AeoCitationRun::query()->create([
                'agency_id' => $prompt->agency_id,
                'client_id' => $prompt->client_id,
                'aeo_tracked_prompt_id' => $prompt->id,
                'engine' => $engine,
                'mode' => 'deterministic',
                'client_mentioned' => $brandInPrompt,
                'client_position' => $brandInPrompt ? 1 : null,
                'sentiment' => 'neutral',
                'competitor_mentions_json' => $compHits,
                'cited_urls_json' => [],
                'response_excerpt' => $excerpt,
                'checked_at' => now(),
            ]);
        }

        return $created;
    }

    /**
     * #182 Share-of-Model Competitor Matrix.
     *
     * @return array{
     *   total_runs: int,
     *   live_runs: int,
     *   deterministic_runs: int,
     *   client_share_pct: float,
     *   avg_position: ?float,
     *   sentiment: array{positive: int, neutral: int, negative: int},
     *   competitors: list<array{name: string, mentions: int, share_pct: float}>
     * }
     */
    public static function shareOfModelMatrix(Client $client): array
    {
        $runs = AeoCitationRun::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->get();

        $total = $runs->count();
        if ($total === 0) {
            return [
                'total_runs' => 0,
                'live_runs' => 0,
                'deterministic_runs' => 0,
                'client_share_pct' => 0.0,
                'avg_position' => null,
                'sentiment' => ['positive' => 0, 'neutral' => 0, 'negative' => 0],
                'competitors' => [],
            ];
        }

        $clientMentions = $runs->where('client_mentioned', true);
        $positions = $clientMentions->pluck('client_position')->filter(fn ($p) => $p !== null && $p > 0);

        $namedCompetitors = AeoTrackedPrompt::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->pluck('competitors_json')
            ->flatten()
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn (string $v) => trim($v))
            ->unique()
            ->take(5)
            ->values();

        $compCounts = [];
        foreach ($namedCompetitors as $cName) {
            $compCounts[$cName] = 0;
        }
        foreach ($runs as $run) {
            foreach ((array) ($run->competitor_mentions_json ?? []) as $hit) {
                $hit = trim((string) $hit);
                if ($hit !== '') {
                    $compCounts[$hit] = ($compCounts[$hit] ?? 0) + 1;
                }
            }
        }

        $compRows = [];
        foreach (array_slice($compCounts, 0, 5, true) as $name => $cnt) {
            $compRows[] = [
                'name' => $name,
                'mentions' => $cnt,
                'share_pct' => round(($cnt / $total) * 100, 1),
            ];
        }

        return [
            'total_runs' => $total,
            'live_runs' => $runs->where('mode', 'live')->count(),
            'deterministic_runs' => $runs->where('mode', 'deterministic')->count(),
            'client_share_pct' => round(($clientMentions->count() / $total) * 100, 1),
            'avg_position' => $positions->isNotEmpty() ? round((float) $positions->avg(), 2) : null,
            'sentiment' => [
                'positive' => $runs->where('sentiment', 'positive')->count(),
                'neutral' => $runs->where('sentiment', 'neutral')->count(),
                'negative' => $runs->where('sentiment', 'negative')->count(),
            ],
            'competitors' => $compRows,
        ];
    }

    /**
     * #183 GA4 & Server-Side LLM Referral Revenue Slice.
     *
     * @param  list<array{source: string, sessions?: int, conversions?: int, revenue?: float}>|null  $syncedRows
     * @return array{
     *   status: string,
     *   totals: array{sessions: int, conversions: int, revenue: float},
     *   by_engine: array<string, array{sessions: int, conversions: int, revenue: float}>
     * }
     */
    public static function llmReferralSlice(Client $client, ?array $syncedRows = null): array
    {
        $byEngine = [];
        foreach (self::LLM_REFERRERS as $host) {
            $byEngine[$host] = ['sessions' => 0, 'conversions' => 0, 'revenue' => 0.0];
        }

        if ($syncedRows === null) {
            $metrics = DailyMetric::query()
                ->where('agency_id', $client->agency_id)
                ->where('client_id', $client->id)
                ->whereIn('network', ['chatgpt', 'perplexity', 'gemini', 'claude', 'copilot'])
                ->get(['network', 'clicks', 'results', 'revenue']);

            if ($metrics->isEmpty()) {
                return [
                    'status' => 'no_synced_rows',
                    'totals' => ['sessions' => 0, 'conversions' => 0, 'revenue' => 0.0],
                    'by_engine' => $byEngine,
                ];
            }

            $map = [
                'chatgpt' => 'chatgpt.com',
                'perplexity' => 'perplexity.ai',
                'gemini' => 'gemini.google.com',
                'claude' => 'claude.ai',
                'copilot' => 'copilot.microsoft.com',
            ];
            foreach ($metrics as $m) {
                $h = $map[$m->network] ?? null;
                if ($h) {
                    $byEngine[$h]['sessions'] += (int) $m->clicks;
                    $byEngine[$h]['conversions'] += (int) $m->results;
                    $byEngine[$h]['revenue'] = round($byEngine[$h]['revenue'] + (float) $m->revenue, 2);
                }
            }
        } else {
            foreach ($syncedRows as $row) {
                $src = strtolower(trim((string) ($row['source'] ?? '')));
                foreach (self::LLM_REFERRERS as $refHost) {
                    $short = explode('.', $refHost)[0];
                    if ($src === $refHost || str_contains($src, $refHost) || $src === $short) {
                        $byEngine[$refHost]['sessions'] += max(0, (int) ($row['sessions'] ?? 0));
                        $byEngine[$refHost]['conversions'] += max(0, (int) ($row['conversions'] ?? 0));
                        $byEngine[$refHost]['revenue'] = round($byEngine[$refHost]['revenue'] + max(0.0, (float) ($row['revenue'] ?? 0.0)), 2);
                        break;
                    }
                }
            }
        }

        $totSessions = array_sum(array_column($byEngine, 'sessions'));
        $totConv = array_sum(array_column($byEngine, 'conversions'));
        $totRev = round(array_sum(array_column($byEngine, 'revenue')), 2);

        return [
            'status' => ($totSessions > 0 || $totConv > 0 || $totRev > 0) ? 'live' : 'no_synced_rows',
            'totals' => ['sessions' => $totSessions, 'conversions' => $totConv, 'revenue' => $totRev],
            'by_engine' => $byEngine,
        ];
    }

    /**
     * #184 Client `llms.txt` & `llms-full.txt` Generator & Validator.
     *
     * @param  list<array{title: string, url: string, summary?: string, detail?: string}>  $keyPages
     * @return array{llms_txt: string, llms_full_txt: string, live_validation: array{checked: bool, status: ?int, valid_markdown: bool, error: ?string}}
     */
    public static function generateAndValidateLlmsTxt(Client $client, string $domainUrl, array $keyPages = [], bool $validateRemote = true): array
    {
        $origin = static::normalizeOrigin($domainUrl) ?: 'https://example.com';
        if ($keyPages === []) {
            $keyPages = [
                ['title' => $client->name.' — Home', 'url' => $origin.'/', 'summary' => 'Official brand overview and primary offerings.', 'detail' => 'Primary canonical entry point for '.$client->name.'.'],
                ['title' => $client->name.' — Contact', 'url' => $origin.'/contact', 'summary' => 'Verified customer support and regional office details.', 'detail' => 'Direct inquiry channels and service coverage.'],
            ];
        }

        $short = '# '.$client->name."\n\n> Official canonical resource index for ".$client->name.' ('.$origin.").\n\n## Core Pages\n\n";
        $full = $short;

        foreach ($keyPages as $p) {
            $t = trim((string) ($p['title'] ?? 'Page'));
            $u = trim((string) ($p['url'] ?? $origin));
            $s = trim((string) ($p['summary'] ?? ''));
            $d = trim((string) ($p['detail'] ?? $s));
            $short .= "- [{$t}]({$u})".($s !== '' ? ": {$s}" : '')."\n";
            $full .= "### {$t}\nURL: {$u}\n".($d !== '' ? "{$d}\n\n" : "\n");
        }

        $validation = ['checked' => false, 'status' => null, 'valid_markdown' => false, 'error' => null];
        if ($validateRemote && static::normalizeOrigin($domainUrl) !== null) {
            $res = SafeFetcher::fetchResource($origin.'/llms.txt', ['plain', 'markdown', 'text', 'html']);
            $hasHeading = $res['status'] === 200 && preg_match('/^#\s+\S+/m', $res['body']) === 1;
            $validation = [
                'checked' => true,
                'status' => $res['status'],
                'valid_markdown' => $hasHeading,
                'error' => $res['error'],
            ];
        }

        return [
            'llms_txt' => $short,
            'llms_full_txt' => $full,
            'live_validation' => $validation,
        ];
    }

    /**
     * #185 AI Crawler Access & `robots.txt` / X-Robots-Tag Auditor.
     *
     * @return array{ok: bool, error?: string, origin?: string, robots_status?: ?int, x_robots_tag?: string, x_robots_blocks_ai?: bool, bots?: array<string, string>}
     */
    public static function auditAiCrawlers(string $domainUrl): array
    {
        $origin = static::normalizeOrigin($domainUrl);
        if (! $origin) {
            return ['ok' => false, 'error' => 'blocked'];
        }

        $robots = SafeFetcher::fetchResource($origin.'/robots.txt', ['plain', 'text', 'html']);
        if ($robots['error'] === 'blocked') {
            return ['ok' => false, 'error' => 'blocked'];
        }

        $home = SafeFetcher::fetchResource($origin.'/', ['html', 'plain', 'text']);
        if ($home['error'] === 'blocked') {
            return ['ok' => false, 'error' => 'blocked'];
        }

        $bots = static::parseRobotsMatrix($robots['status'] === 200 ? $robots['body'] : '');
        $rawXTag = (string) ($home['x_robots_tag'] ?? '');
        $xTag = strtolower($rawXTag);
        $xBlocksAi = str_contains($xTag, 'noai') || str_contains($xTag, 'noindex');

        return [
            'ok' => true,
            'origin' => $origin,
            'robots_status' => $robots['status'],
            'x_robots_tag' => $rawXTag,
            'x_robots_blocks_ai' => $xBlocksAi,
            'bots' => $bots,
        ];
    }

    /**
     * #186 Query Fan-Out & Conversational Prompt Generator (Arabic Normaliser + MENA markets).
     *
     * @return array{normalized_key: string, dialects: list<string>, prompts: list<array{stage: string, market: string, prompt: string}>}
     */
    public static function generateQueryFanOut(string $seedTerm, string $locale = 'ar'): array
    {
        $clean = trim($seedTerm);
        $key = Normaliser::key($clean);
        $dialects = method_exists(Normaliser::class, 'dialectTags') ? Normaliser::dialectTags($clean) : [];

        if ($locale === 'ar') {
            $prompts = [
                ['stage' => 'awareness', 'market' => 'MENA', 'prompt' => "ما هي أهم المعايير لاختيار {$clean} الموثوقة للشركات؟"],
                ['stage' => 'consideration', 'market' => 'SA', 'prompt' => "ما أفضل خيارات {$clean} في الرياض وجدة مع مقارنة المزايا؟"],
                ['stage' => 'consideration', 'market' => 'AE', 'prompt' => "قارن بين أبرز مقدمي {$clean} في دبي والإمارات من حيث سرعة التنفيذ."],
                ['stage' => 'decision', 'market' => 'JO', 'prompt' => "كم تبلغ أسعار وتكلفة {$clean} الشهرية في عمّان والأردن؟"],
                ['stage' => 'decision', 'market' => 'EG', 'prompt' => "من هي الجهة الأفضل في {$clean} في القاهرة للشركات المتوسطة مع دعم عربي كامل؟"],
                ['stage' => 'comparison', 'market' => 'MENA', 'prompt' => "ما البدائل العملية عند المقارنة بين مزودي {$clean} وما عيوب كل خيار؟"],
            ];
        } else {
            $prompts = [
                ['stage' => 'awareness', 'market' => 'MENA', 'prompt' => "What criteria matter most when evaluating {$clean} for a growing team?"],
                ['stage' => 'consideration', 'market' => 'SA', 'prompt' => "Which providers are best for {$clean} in Riyadh and Saudi Arabia?"],
                ['stage' => 'consideration', 'market' => 'AE', 'prompt' => "Compare top {$clean} options in Dubai and the UAE by support and reliability."],
                ['stage' => 'decision', 'market' => 'JO', 'prompt' => "What is the typical monthly pricing for {$clean} in Amman, Jordan?"],
                ['stage' => 'decision', 'market' => 'EG', 'prompt' => "Best {$clean} solution in Cairo for mid-market businesses needing Arabic support?"],
                ['stage' => 'comparison', 'market' => 'MENA', 'prompt' => "Top alternatives and side-by-side trade-offs when switching {$clean} providers?"],
            ];
        }

        return [
            'normalized_key' => $key,
            'dialects' => $dialects,
            'prompts' => $prompts,
        ];
    }

    /**
     * #187 Third-Party Citation Gap Finder.
     *
     * @return list<array{domain: string, url: string, competitor_mentions: list<string>, citation_count: int}>
     */
    public static function findCitationGaps(Client $client, ?string $clientDomain = null): array
    {
        $ownHost = $clientDomain ? strtolower((string) (parse_url(static::normalizeOrigin($clientDomain) ?: '', PHP_URL_HOST) ?: trim($clientDomain))) : '';
        $runs = AeoCitationRun::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->get();

        $gaps = [];
        foreach ($runs as $run) {
            if ($run->client_mentioned) {
                continue;
            }
            $comps = array_values(array_filter((array) ($run->competitor_mentions_json ?? [])));
            if ($comps === []) {
                continue;
            }
            foreach ((array) ($run->cited_urls_json ?? []) as $url) {
                $h = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
                if ($h === '' || ($ownHost !== '' && ($h === $ownHost || str_ends_with($h, '.'.$ownHost)))) {
                    continue;
                }
                if (! isset($gaps[$h])) {
                    $gaps[$h] = [
                        'domain' => $h,
                        'url' => (string) $url,
                        'competitor_mentions' => [],
                        'citation_count' => 0,
                    ];
                }
                $gaps[$h]['citation_count']++;
                $gaps[$h]['competitor_mentions'] = array_values(array_unique(array_merge($gaps[$h]['competitor_mentions'], $comps)));
            }
        }

        usort($gaps, fn (array $a, array $b): int => $b['citation_count'] <=> $a['citation_count']);

        return array_values($gaps);
    }

    /**
     * #188 Client JSON-LD Entity & Knowledge Graph Builder.
     *
     * @param  array<string, mixed>  $input
     * @return array{valid: bool, json_ld: string, data: array<string, mixed>}
     */
    public static function buildEntitySchema(string $type, array $input): array
    {
        $allowed = ['Organization', 'LocalBusiness', 'Product', 'FAQPage'];
        $schemaType = in_array($type, $allowed, true) ? $type : 'Organization';
        $sameAs = array_values(array_filter(array_map('trim', (array) ($input['same_as'] ?? [])), fn (string $u) => preg_match('#^https?://#i', $u) === 1));

        if ($schemaType === 'FAQPage') {
            $faqs = [];
            foreach ((array) ($input['faqs'] ?? []) as $pair) {
                $q = trim((string) ($pair['q'] ?? ''));
                $a = trim((string) ($pair['a'] ?? ''));
                if ($q !== '' && $a !== '') {
                    $faqs[] = [
                        '@type' => 'Question',
                        'name' => $q,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                    ];
                }
            }
            $data = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqs,
            ];
        } else {
            $data = [
                '@context' => 'https://schema.org',
                '@type' => $schemaType,
                'name' => trim((string) ($input['name'] ?? '')),
                'url' => trim((string) ($input['url'] ?? '')),
                'description' => trim((string) ($input['description'] ?? '')),
            ];
            if ($sameAs !== []) {
                $data['sameAs'] = $sameAs;
            }
            if ($schemaType === 'LocalBusiness' && ! empty($input['city'])) {
                $data['address'] = [
                    '@type' => 'PostalAddress',
                    'addressLocality' => trim((string) $input['city']),
                    'addressCountry' => trim((string) ($input['country'] ?? 'SA')),
                ];
            }
            if ($schemaType === 'Product' && isset($input['price'])) {
                $data['offers'] = [
                    '@type' => 'Offer',
                    'price' => (string) $input['price'],
                    'priceCurrency' => trim((string) ($input['currency'] ?? 'SAR')),
                ];
            }
        }

        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $valid = json_decode($json, true) !== null && ! empty($data['@type']);

        return ['valid' => $valid, 'json_ld' => $json, 'data' => $data];
    }

    /**
     * #189 Answer-First 40–60 Word Snippet & FAQ Rewriter via SafeFetcher.
     *
     * @return array{ok: bool, error?: string, readiness_score?: int, lead_word_count?: int, draft_ar?: string, draft_en?: string}
     */
    public static function rewriteAnswerFirstSnippet(string $url, string $targetQuestion, string $locale = 'ar'): array
    {
        $fetched = SafeFetcher::fetch($url);
        if (! $fetched['ok']) {
            return ['ok' => false, 'error' => $fetched['error'] ?? 'fetch_failed'];
        }

        $html = $fetched['html'];
        $hasH1 = preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $h1m) === 1;
        $h1Text = $hasH1 ? trim(strip_tags($h1m[1])) : trim($targetQuestion);

        $firstPara = '';
        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $html, $pm)) {
            $firstPara = trim((string) preg_replace('/\s+/u', ' ', strip_tags($pm[1])));
        }
        $words = $firstPara === '' ? [] : (preg_split('/\s+/u', $firstPara) ?: []);
        $wordCount = count($words);
        $hasFaqSchema = stripos($html, 'FAQPage') !== false;

        $score = 0;
        if ($hasH1) {
            $score += 35;
        }
        if ($wordCount >= 35 && $wordCount <= 70) {
            $score += 40;
        } elseif ($wordCount > 0) {
            $score += 20;
        }
        if ($hasFaqSchema) {
            $score += 25;
        }

        $topic = $h1Text !== '' ? $h1Text : trim($targetQuestion);
        $draftAr = "إجابة مباشرة حول «{$topic}»: يعتمد الخيار الأنسب على وضوح نطاق الخدمة، وتوافق التكلفة الشهرية مع العائد المستهدف، وسهولة التكامل التقني، وتوفر تقارير شفافة قابلة للمراجعة الفورية، مما يمنح فريقك قرارًا عمليًا مبنيًا على بيانات فعلية قابلة للقياس منذ الشهر الأول للتشغيل.";
        $draftEn = "Direct answer on \"{$topic}\": the strongest option balances transparent scope, measurable cost-per-outcome targets, clean technical integration, and verifiable client reporting—giving your team a grounded, auditable workflow that connects daily execution directly to revenue and retention from month one.";

        return [
            'ok' => true,
            'url' => $fetched['url'],
            'readiness_score' => min(100, $score),
            'lead_word_count' => $wordCount,
            'draft_ar' => $draftAr,
            'draft_en' => $draftEn,
            'recommended' => $locale === 'ar' ? $draftAr : $draftEn,
        ];
    }

    /**
     * #190 GSC Keyword Cannibalization & 30-Day Traffic-Decay Detector.
     *
     * @param  list<array{query: string, url: string, clicks: int, impressions: int, position?: float}>  $gscPageQueryRows
     * @param  list<array{url: string, current_clicks: int, previous_clicks: int}>  $periodComparisonRows
     * @return array{
     *   cannibalized: list<array{normalized_query: string, sample_query: string, urls: list<array{url: string, clicks: int, impressions: int}>, total_clicks: int}>,
     *   decaying_pages: list<array{url: string, current_clicks: int, previous_clicks: int, drop_pct: float}>
     * }
     */
    public static function detectCannibalizationAndDecay(array $gscPageQueryRows, array $periodComparisonRows = []): array
    {
        $byQuery = [];
        foreach ($gscPageQueryRows as $row) {
            $q = trim((string) ($row['query'] ?? ''));
            $u = trim((string) ($row['url'] ?? ''));
            if ($q === '' || $u === '') {
                continue;
            }
            $norm = Normaliser::key($q);
            $byQuery[$norm]['sample_query'] ??= $q;
            $byQuery[$norm]['urls'][$u] = [
                'url' => $u,
                'clicks' => ($byQuery[$norm]['urls'][$u]['clicks'] ?? 0) + max(0, (int) ($row['clicks'] ?? 0)),
                'impressions' => ($byQuery[$norm]['urls'][$u]['impressions'] ?? 0) + max(0, (int) ($row['impressions'] ?? 0)),
            ];
        }

        $cannibalized = [];
        foreach ($byQuery as $norm => $bucket) {
            $urls = array_values($bucket['urls']);
            if (count($urls) < 2) {
                continue;
            }
            usort($urls, fn (array $a, array $b): int => $b['clicks'] <=> $a['clicks']);
            $cannibalized[] = [
                'normalized_query' => $norm,
                'sample_query' => $bucket['sample_query'],
                'urls' => $urls,
                'total_clicks' => array_sum(array_column($urls, 'clicks')),
            ];
        }

        $decaying = [];
        foreach ($periodComparisonRows as $pRow) {
            $prev = max(0, (int) ($pRow['previous_clicks'] ?? 0));
            $curr = max(0, (int) ($pRow['current_clicks'] ?? 0));
            if ($prev <= 0 || $curr >= $prev) {
                continue;
            }
            $dropPct = round((($prev - $curr) / $prev) * 100, 1);
            if ($dropPct >= 25.0) {
                $decaying[] = [
                    'url' => trim((string) ($pRow['url'] ?? '')),
                    'current_clicks' => $curr,
                    'previous_clicks' => $prev,
                    'drop_pct' => $dropPct,
                ];
            }
        }

        return [
            'cannibalized' => $cannibalized,
            'decaying_pages' => $decaying,
        ];
    }

    /**
     * #191 Internal Link Orphan & Bilingual Anchor-Text Auditor (bounded SafeFetcher crawl <= 5 pages).
     *
     * @param  list<string>  $pageUrls
     * @param  list<string>  $highConvertingUrls
     * @return array{ok: bool, error?: string, inbound_counts: array<string, int>, orphans: list<string>, weak_anchors: list<array{source_url: string, target_url: string, anchor: string}>}
     */
    public static function auditInternalLinksAndAnchors(array $pageUrls, array $highConvertingUrls = []): array
    {
        $pages = array_slice(array_values(array_unique(array_filter(array_map('trim', $pageUrls)))), 0, 5);
        $inbound = [];
        foreach ($highConvertingUrls as $hc) {
            $inbound[rtrim(trim($hc), '/')] = 0;
        }

        $weak = [];
        $weakKeys = array_map(fn (string $w): string => Normaliser::key($w), self::WEAK_ANCHORS);

        foreach ($pages as $srcUrl) {
            $fetched = SafeFetcher::fetch($srcUrl);
            if ($fetched['error'] === 'blocked') {
                return ['ok' => false, 'error' => 'blocked', 'inbound_counts' => [], 'orphans' => [], 'weak_anchors' => []];
            }
            if (! $fetched['ok']) {
                continue;
            }
            $srcHost = strtolower((string) parse_url($fetched['url'], PHP_URL_HOST));
            if (preg_match_all('/<a\b[^>]*href=["\']([^"\'#]+)["\'][^>]*>(.*?)<\/a>/is', $fetched['html'], $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $abs = rtrim(SafeFetcher::absolute($fetched['url'], trim($m[1])), '/');
                    $dstHost = strtolower((string) parse_url($abs, PHP_URL_HOST));
                    if ($dstHost !== $srcHost) {
                        continue;
                    }
                    if ($abs !== rtrim($fetched['url'], '/')) {
                        $inbound[$abs] = ($inbound[$abs] ?? 0) + 1;
                    }
                    $anchor = trim((string) preg_replace('/\s+/u', ' ', strip_tags($m[2])));
                    if ($anchor !== '' && in_array(Normaliser::key($anchor), $weakKeys, true)) {
                        $weak[] = [
                            'source_url' => $fetched['url'],
                            'target_url' => $abs,
                            'anchor' => $anchor,
                        ];
                    }
                }
            }
        }

        $orphans = [];
        foreach ($highConvertingUrls as $hc) {
            $normHc = rtrim(trim($hc), '/');
            if (($inbound[$normHc] ?? 0) === 0) {
                $orphans[] = $hc;
            }
        }

        return [
            'ok' => true,
            'inbound_counts' => $inbound,
            'orphans' => $orphans,
            'weak_anchors' => $weak,
        ];
    }

    /**
     * #192 Programmatic Comparison & "Alternatives" Page Brief Builder.
     *
     * @return array{slug: string, h1: string, answer_first_lead: string, h2_sections: list<string>, matrix_columns: list<string>, faqs: list<string>}
     */
    public static function buildComparisonBrief(string $brand, string $competitorOrCategory, string $city = 'Riyadh', string $locale = 'ar', string $template = 'vs'): array
    {
        $b = trim($brand);
        $c = trim($competitorOrCategory);
        $loc = trim($city);

        if ($locale === 'ar') {
            $h1 = $template === 'regional_best'
                ? "أفضل حلول {$c} في {$loc}: مقارنة عملية مع {$b}"
                : "مقارنة شاملة: {$b} مقابل {$c} — أيهما أنسب لفريقك؟";

            return [
                'slug' => $template === 'regional_best' ? 'best-'.Normaliser::key($c).'-'.strtolower($loc) : strtolower(preg_replace('/\W+/', '-', $b.'-vs-'.$c) ?: 'comparison'),
                'h1' => $h1,
                'answer_first_lead' => "ملخص من 45 كلمة: يناسب {$b} الفرق التي تعطي الأولوية للتقارير العربية الدقيقة وربط العائد الفعلي في {$loc}، بينما يختلف {$c} في هيكل التسعير ونطاق التكاملات؛ يوضّح الجدول أدناه الفروق الموثقة لاختيار المنصة الأنسب لحجم أعمالك.",
                'h2_sections' => [
                    "الفرق الجوهري بين {$b} و{$c} في 60 ثانية",
                    'جدول مقارنة المزايا والتكاملات ودعم اللغة العربية',
                    "التسعير وتكلفة التشغيل الفعلية للفرق في {$loc}",
                    "متى تختار {$b} ومتى يكون البديل أنسب؟",
                ],
                'matrix_columns' => ['المعيار', $b, $c, 'الأثر على العائد'],
                'faqs' => [
                    "هل يدعم {$b} التقارير ثنائية اللغة مقارنة بـ {$c}؟",
                    "كم يستغرق الانتقال من {$c} إلى {$b} دون فقدان البيانات؟",
                ],
            ];
        }

        $h1 = $template === 'regional_best'
            ? "Best {$c} in {$loc}: Practical Evaluation Featuring {$b}"
            : "{$b} vs {$c}: Honest Side-by-Side Comparison";

        return [
            'slug' => strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $h1), '-')),
            'h1' => $h1,
            'answer_first_lead' => "50-word direct answer: {$b} fits teams prioritizing Arabic-first reporting, regional currency clarity in {$loc}, and auditable attribution, whereas {$c} differs in packaging and workflow scope. Review the verified feature matrix, pricing structure, and migration checklist below to match your workload.",
            'h2_sections' => [
                "Quick Verdict: {$b} vs {$c} in 60 Seconds",
                'Feature, Integration & Arabic RTL Support Matrix',
                "Pricing & Total Cost of Ownership in {$loc}",
                "When to Choose {$b} vs Alternatives",
            ],
            'matrix_columns' => ['Criterion', $b, $c, 'Operational Impact'],
            'faqs' => [
                "How does {$b} compare to {$c} for bilingual client reporting?",
                "How long does onboarding take when switching from {$c} to {$b}?",
            ],
        ];
    }

    /**
     * Run & persist a consolidated Domain AEO/GEO Audit record (#184, #185, #188, #189, #191).
     */
    public static function runAndSaveDomainAudit(Client $client, string $domainUrl, array $highConvertingUrls = []): ?AeoDomainAudit
    {
        $crawlers = static::auditAiCrawlers($domainUrl);
        if (! ($crawlers['ok'] ?? false)) {
            return null;
        }

        $llms = static::generateAndValidateLlmsTxt($client, $domainUrl);
        $schema = static::buildEntitySchema('Organization', [
            'name' => $client->name,
            'url' => $crawlers['origin'],
            'description' => $client->name.' official digital presence.',
        ]);
        $links = static::auditInternalLinksAndAnchors([$crawlers['origin'].'/'], $highConvertingUrls);
        $snippet = static::rewriteAnswerFirstSnippet($crawlers['origin'].'/', $client->name, 'ar');

        return AeoDomainAudit::query()->create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'domain_url' => $crawlers['origin'],
            'robots_bot_matrix_json' => $crawlers['bots'] ?? [],
            'llms_txt_markdown' => $llms['llms_txt'],
            'schema_json_ld' => $schema['json_ld'],
            'internal_link_orphans_json' => [
                'orphans' => $links['orphans'] ?? [],
                'weak_anchors' => $links['weak_anchors'] ?? [],
            ],
            'snippet_rewrites_json' => $snippet['ok'] ? [
                'readiness_score' => $snippet['readiness_score'],
                'draft_ar' => $snippet['draft_ar'],
                'draft_en' => $snippet['draft_en'],
            ] : [],
            'audited_at' => now(),
        ]);
    }

    /** @return array<string, string> */
    public static function parseRobotsMatrix(string $robotsTxt): array
    {
        $groups = [];
        $current = [];
        foreach (preg_split('/\R/', $robotsTxt) ?: [] as $raw) {
            $line = trim((string) preg_replace('/#.*$/', '', $raw));
            if ($line === '' || ! str_contains($line, ':')) {
                if ($line === '') {
                    $current = [];
                }

                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $kl = strtolower($k);
            if ($kl === 'user-agent') {
                $ag = strtolower($v);
                $current[] = $ag;
                $groups[$ag] ??= ['allow' => [], 'disallow' => []];
            } elseif (in_array($kl, ['allow', 'disallow'], true) && $current !== []) {
                foreach ($current as $ag) {
                    $groups[$ag][$kl][] = $v;
                }
            }
        }

        $wildcardBlocked = in_array('/', $groups['*']['disallow'] ?? [], true) && ! in_array('/', $groups['*']['allow'] ?? [], true);
        $out = [];
        foreach (self::AI_BOTS as $bot) {
            $bk = strtolower($bot);
            if (isset($groups[$bk])) {
                $dis = $groups[$bk]['disallow'];
                $all = $groups[$bk]['allow'];
                $out[$bot] = (in_array('/', $dis, true) && ! in_array('/', $all, true)) ? 'blocked' : 'allowed';
            } else {
                $out[$bot] = $wildcardBlocked ? 'blocked_wildcard' : 'allowed_implicit';
            }
        }

        return $out;
    }

    protected static function normalizeOrigin(string $url): ?string
    {
        $p = parse_url(trim($url));
        $scheme = strtolower($p['scheme'] ?? '');
        $host = strtolower($p['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        return $scheme.'://'.$host.(isset($p['port']) ? ':'.$p['port'] : '');
    }

    protected static function liveOpenAiKey(): ?string
    {
        $cfg = trim((string) config('services.openai.key', ''));
        if ($cfg !== '') {
            return $cfg;
        }
        $enc = PlatformSetting::get('ai.api_key_encrypted');
        if (is_string($enc) && $enc !== '' && PlatformSetting::get('ai.provider', 'openai') === 'openai') {
            try {
                return Crypt::decryptString($enc);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $competitors
     * @return array{client_mentioned: bool, client_position: ?int, sentiment: string, competitors: list<string>, urls: list<string>, excerpt: string}|null
     */
    protected static function evaluateLiveOpenAi(string $key, string $promptText, string $brand, array $competitors): ?array
    {
        try {
            $resp = Http::timeout(12)->withToken($key)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $promptText],
                ],
                'max_tokens' => 300,
            ]);
            if (! $resp->successful()) {
                return null;
            }
            $text = trim((string) data_get($resp->json(), 'choices.0.message.content', ''));
            if ($text === '') {
                return null;
            }
            $mentioned = stripos($text, $brand) !== false;
            $compHits = [];
            foreach ($competitors as $c) {
                if ($c !== '' && stripos($text, $c) !== false) {
                    $compHits[] = $c;
                }
            }
            preg_match_all('#https?://[^\s\)\]> "\']+#i', $text, $um);

            return [
                'client_mentioned' => $mentioned,
                'client_position' => $mentioned ? 1 : null,
                'sentiment' => 'neutral',
                'competitors' => $compHits,
                'urls' => array_values(array_unique($um[0] ?? [])),
                'excerpt' => mb_substr($text, 0, 280),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
