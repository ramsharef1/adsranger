<?php

namespace App\Support\AiWorkspace;

use App\Models\Client;
use App\Models\SeoAudit;
use App\Models\SeoIssue;
use App\Support\Seo\Audit\Catalog;

/**
 * R7 part 4: what the AI may know about the client's last site audit (A1). FS-13 images without alt text, FS-14 the
 * audit summary, FS-07 new Critical issues for the night shift. Only finished audits; page URLs and titles, no page copy.
 */
class SiteContext
{
    /** @return list<SeoAudit> the client's finished audits, newest first */
    public static function audits(Client $client, int $limit = 2): array
    {
        return SeoAudit::with('site')->where('status', 'done')
            ->whereHas('site', fn ($q) => $q->where('client_id', $client->id))
            ->orderByDesc('finished_at')->orderByDesc('id')->limit($limit)->get()->all();
    }

    public static function latest(Client $client): ?SeoAudit
    {
        return static::audits($client, 1)[0] ?? null;
    }

    /** @return array{site: string, date: ?string, score: ?int, pages: int, critical: int, warning: int, info: int, top: list<array{code: string, level: string, issue: string, pages: int}>} */
    public static function summary(SeoAudit $audit): array
    {
        $top = SeoIssue::where('seo_audit_id', $audit->id)->selectRaw('code, level, count(*) as n')->groupBy('code', 'level')->get()
            ->sortBy(fn ($r) => [array_search($r->level, [Catalog::CRITICAL, Catalog::WARNING, Catalog::INFO], true), -$r->n])->take(8)
            ->map(fn ($r) => ['code' => $r->code, 'level' => $r->level, 'issue' => Catalog::describe($r->code, false)['title'], 'pages' => (int) $r->n])->values()->all();

        return ['site' => (string) $audit->site?->host, 'date' => $audit->finished_at?->toDateString(), 'score' => $audit->score, 'pages' => (int) $audit->pages_done,
            'critical' => (int) $audit->critical_count, 'warning' => (int) $audit->warning_count, 'info' => (int) $audit->info_count, 'top' => $top];
    }

    /** @return list<array{page: string, title: string, h1: string, images: list<string>}> pages with images that have no alt text */
    public static function imagesNoAlt(SeoAudit $audit, int $limit = 10): array
    {
        return SeoIssue::with('page')->where('seo_audit_id', $audit->id)->where('code', 'IMG-01')->whereNotNull('seo_audit_page_id')->limit($limit)->get()
            ->map(fn (SeoIssue $i) => ['page' => (string) $i->page?->url, 'title' => mb_substr(strip_tags((string) $i->page?->title), 0, 150),
                'h1' => mb_substr(strip_tags((string) $i->page?->h1), 0, 150), 'images' => static::sources((string) $i->detail)])->values()->all();
    }

    /** FS-07: Critical issue kinds in the latest finished audit that the one before did not have (all of them on a first audit). */
    public static function newCritical(Client $client, ?\DateTimeInterface $since = null): array
    {
        [$now, $before] = static::audits($client, 2) + [null, null];
        if (! $now || ($since && $now->finished_at && $now->finished_at->lt($since))) {
            return [];
        }
        $codes = fn (SeoAudit $a) => SeoIssue::where('seo_audit_id', $a->id)->where('level', Catalog::CRITICAL)->selectRaw('code, count(*) as n')->groupBy('code')->pluck('n', 'code')->all();
        $old = $before ? $codes($before) : [];

        return collect($codes($now))->reject(fn ($n, $code) => isset($old[$code]))
            ->map(fn ($n, $code) => ['issue' => Catalog::describe($code, false)['title'], 'pages' => (int) $n, 'site' => (string) $now->site?->host, 'date' => $now->finished_at?->toDateString()])
            ->values()->all();
    }

    /** @return list<string> image addresses saved in an IMG-01 detail ("3 img: /a.jpg · /b.png") */
    protected static function sources(string $detail): array
    {
        $list = trim((string) (explode(':', $detail, 2)[1] ?? ''));

        return $list === '' ? [] : array_values(array_filter(array_map('trim', explode(' · ', $list))));
    }
}
