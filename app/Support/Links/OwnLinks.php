<?php

namespace App\Support\Links;

use App\Models\AuditLog;
use App\Models\OwnLink;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Seo\SafeFetcher;
use App\Support\WebPush;
use DOMDocument;
use DOMXPath;

/**
 * BX-07 (was `earned_links.py`, B-27): every known page that links to adsranger.com is fetched once a month with the
 * SafeFetcher; we keep whether the link is still there, its rel and its anchor, and the anchor mix. A link that was live
 * and is gone on two tries in the same run is marked lost: audit log, phone push to super admins, red badge in /admin.
 */
class OwnLinks
{
    /** Keyword anchors we do not want too many of (B-27): same rule as the Backlinks script. */
    public const EXACT = '/(ad|ads|advertising) (reporting|management|report) (software|tool|platform)|برنامج تقارير إعلان|منصة إدارة إعلانات/iu';

    public const BRAND = '/ads ?ranger|[أاآ]دز ?رينجر/iu';

    public const HOSTS = ['adsranger.com', 'www.adsranger.com'];

    /**
     * Adds rows from pasted lines: `url`, or `url | name | note` (the Link Tracker export). Returns how many were new.
     * A row already in the list keeps its history; an archived one comes back as unchecked.
     */
    public static function add(string $text): int
    {
        $new = 0;
        foreach (preg_split('/\R/u', $text) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $url = $parts[0] ?? '';
            if (! preg_match('~^https?://[^\s/]+\.[^\s]+$~i', $url) || mb_strlen($url) > 500) {
                continue;
            }
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (in_array($host, self::HOSTS, true)) {
                continue; // our own pages are not earned links
            }
            $row = OwnLink::firstOrNew(['source_url' => $url]);
            if (! $row->exists) {
                $new++;
                $row->status = 'unchecked';
            } elseif ($row->status === 'archived') {
                $row->status = 'unchecked';
            }
            $row->name = mb_substr(($parts[1] ?? '') ?: ($row->name ?? $host), 0, 160);
            $row->note = ($parts[2] ?? '') !== '' ? mb_substr($parts[2], 0, 300) : $row->note;
            $row->save();
        }

        return $new;
    }

    /** @return array{ok: bool, status: ?int, links: list<array{href: string, rel: string, anchor: string, kind: string}>} */
    public static function inspect(string $url): array
    {
        $res = SafeFetcher::fetch($url);
        if (! $res['ok']) {
            return ['ok' => false, 'status' => $res['status'], 'links' => []];
        }

        return ['ok' => true, 'status' => $res['status'], 'links' => self::linksToUs($res['html'], $res['url'])];
    }

    /** @return list<array{href: string, rel: string, anchor: string, kind: string}> */
    public static function linksToUs(string $html, string $base = ''): array
    {
        if (trim($html) === '') {
            return [];
        }
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $out = [];
        foreach ((new DOMXPath($dom))->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            if (! in_array($host, self::HOSTS, true)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $a->textContent));
            $hasImg = $a->getElementsByTagName('img')->length > 0;
            if ($text === '' && $hasImg) {
                $alt = trim((string) $a->getElementsByTagName('img')->item(0)->getAttribute('alt'));
                $text = $alt;
            }
            $out[] = [
                'href' => mb_substr($href, 0, 500),
                'rel' => strtolower(trim($a->getAttribute('rel'))) ?: 'follow',
                'anchor' => mb_substr($text, 0, 200),
                'kind' => self::anchorKind($text, $hasImg),
            ];
        }

        return $out;
    }

    public static function anchorKind(string $text, bool $image = false): string
    {
        if (preg_match(self::EXACT, $text)) {
            return 'exact';
        }
        if (preg_match(self::BRAND, $text)) {
            return 'brand';
        }

        return $text === '' || $image ? 'image' : 'topic';
    }

    /** Checks one row now and saves it. Returns the new status. A failed try does not mark lost unless $final. */
    public static function check(OwnLink $row, bool $final = true): string
    {
        $r = self::inspect($row->source_url);
        $row->checked_at = now();
        $row->http_status = $r['status'];
        if ($r['ok'] && $r['links'] !== []) {
            $first = $r['links'][0];
            $row->fill(['status' => 'live', 'target_url' => $first['href'], 'rel' => mb_substr($first['rel'], 0, 80), 'anchor' => $first['anchor'],
                'anchor_kind' => $first['kind'], 'links_found' => count($r['links']), 'lost_at' => null])->save();

            return 'live';
        }
        if (! $final) {
            $row->save();

            return 'retry';
        }
        $row->links_found = 0;
        if ($row->status !== 'lost') {
            $row->lost_at = now();
        }
        $row->status = 'lost';
        $row->save();

        return 'lost';
    }

    /** The monthly run. @return array{checked: int, live: int, lost: list<string>, newly_lost: list<string>} */
    public static function checkAll(): array
    {
        $rows = OwnLink::where('status', '!=', 'archived')->orderBy('id')->get();
        $before = $rows->mapWithKeys(fn ($r) => [$r->id => $r->status])->all();
        $retry = [];
        foreach ($rows as $row) {
            if (self::check($row, false) === 'retry') {
                $retry[] = $row;
            }
        }
        foreach ($retry as $row) {
            self::check($row, true);
        }
        $lost = $rows->filter(fn ($r) => $r->status === 'lost');
        $newly = $lost->filter(fn ($r) => ($before[$r->id] ?? null) === 'live');
        foreach ($newly as $r) {
            AuditLog::record('links.lost', $r, ['url' => $r->source_url, 'http' => $r->http_status]);
        }
        if ($newly->isNotEmpty()) {
            self::push($newly->pluck('name')->filter()->implode(' · '));
        }
        AuditLog::record('links.verified', null, ['checked' => $rows->count(), 'lost' => $lost->count(), 'newly_lost' => $newly->count()]);

        return ['checked' => $rows->count(), 'live' => $rows->where('status', 'live')->count(),
            'lost' => $lost->pluck('source_url')->values()->all(), 'newly_lost' => $newly->pluck('source_url')->values()->all()];
    }

    /** Anchor mix of the live links: kind → [count, percent]. */
    public static function mix(): array
    {
        $counts = OwnLink::where('status', 'live')->whereNotNull('anchor_kind')->selectRaw('anchor_kind, count(*) as n')->groupBy('anchor_kind')->pluck('n', 'anchor_kind')->all();
        $total = array_sum($counts) ?: 1;

        return collect($counts)->map(fn ($n) => ['count' => (int) $n, 'pct' => (int) round($n * 100 / $total)])->sortByDesc('count')->all();
    }

    public static function lostCount(): int
    {
        return OwnLink::where('status', 'lost')->count();
    }

    protected static function push(string $names): void
    {
        if (! config('push.enabled')) {
            return;
        }
        $admins = User::where('role', 'super_admin')->pluck('id');
        foreach (PushSubscription::whereIn('user_id', $admins)->get() as $sub) {
            try {
                WebPush::send($sub, ['title' => __('A link to AdsRanger is gone'), 'body' => mb_substr($names, 0, 400), 'url' => '/admin/links']);
            } catch (\Throwable) {
                // The alert is still in the audit log and on the Links board.
            }
        }
    }
}
