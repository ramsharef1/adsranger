<?php

namespace App\Support\Site;

use App\Support\Seo\PublicCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * CS-20 (D-279, D-290): a held guide, post or glossary term goes back into the sitemap only when its Arabic page passes the
 * gate — word band (Growth batch-1 §2.3), schema, at least 2 links out, the copy rules clean — and Rami ticks "ready" on
 * Growth's word. Links in are shown, not required: a held page is not linked by design, so they are counted again after release.
 */
class ReindexGate
{
    /** Arabic body words per kind (Growth_Batch1_Copy §2.3). */
    public const BANDS = ['guide' => [1500, 2500], 'post' => [700, 1200], 'term' => [250, 400]];

    public const SCHEMA = ['guide' => '"Article"', 'post' => '"Article"', 'term' => '"DefinedTerm"'];

    public const MIN_OUT_LINKS = 2;

    /** @var (\Closure(string): array{status: int, body: string})|null tests swap the fetcher */
    public static ?\Closure $fetch = null;

    /** @return array<string, string> path => kind, Arabic paths */
    public static function candidates(): array
    {
        $out = [];
        foreach (array_keys(PublicCatalog::guides()) as $slug) {
            $out['/guides/'.$slug] = 'guide';
        }
        foreach (array_keys(PublicCatalog::posts()) as $slug) {
            $out['/blog/'.$slug] = 'post';
        }
        foreach (array_keys(PublicCatalog::glossary()) as $slug) {
            $out['/glossary/'.$slug] = 'term';
        }

        return $out;
    }

    /** @return list<string> paths released through the gate (read by PublicSite::released) */
    public static function releasedPaths(): array
    {
        return once(function (): array {
            try {
                return Schema::hasTable('page_gate') ? DB::table('page_gate')->whereNotNull('released_at')->pluck('path')->all() : [];
            } catch (Throwable) {
                return [];
            }
        });
    }

    /** Fetches every candidate from the live site, measures it and saves the row. @return int pages checked */
    public static function run(): int
    {
        $base = rtrim((string) config('app.url'), '/');
        $get = self::$fetch ?? function (string $url): array {
            try {
                $r = Http::timeout(15)->withHeaders(['User-Agent' => 'AdsRangerBot/1.0 (+https://adsranger.com/bot)'])->get($url);

                return ['status' => $r->status(), 'body' => (string) $r->body()];
            } catch (Throwable) {
                return ['status' => 0, 'body' => ''];
            }
        };

        $candidates = self::candidates();
        $html = [];
        foreach (array_keys($candidates) as $path) {
            $r = $get($base.$path);
            $html[$path] = $r['status'] === 200 ? $r['body'] : '';
        }
        // Links in come from the other candidates and from the pages around them (hubs, tools).
        foreach (['/', '/guides', '/blog', '/glossary', '/tools'] as $hub) {
            $html[$hub] ??= $get($base.$hub)['body'] ?? '';
        }
        $linksOf = [];
        foreach ($html as $path => $body) {
            $linksOf[$path] = self::internalLinks($body, $path);
        }

        foreach ($candidates as $path => $kind) {
            $body = $html[$path];
            $parsed = CopyLint::parse($body);
            $words = $body === '' ? 0 : count(preg_split('/\s+/u', trim($parsed['text']), -1, PREG_SPLIT_NO_EMPTY));
            [$min, $max] = self::BANDS[$kind];
            $errors = $body === '' ? [['found' => 'page did not load']] : array_values(array_filter(CopyLint::check($path, $body), fn ($f) => $f['severity'] === 'error'));
            $in = count(array_filter($linksOf, fn ($links, $from) => $from !== $path && in_array($path, $links, true), ARRAY_FILTER_USE_BOTH));
            $values = [
                'kind' => $kind,
                'words' => $words,
                'band_ok' => $words >= $min && $words <= $max,
                'schema_ok' => str_contains($body, self::SCHEMA[$kind]),
                'out_links' => count($linksOf[$path]),
                'in_links' => $in,
                'lint_errors' => count($errors),
                'lint_first' => $errors ? mb_substr(($errors[0]['rule'] ?? '').' '.$errors[0]['found'], 0, 200) : null,
                'checked_at' => now(),
                'updated_at' => now(),
            ];
            if (DB::table('page_gate')->where('path', $path)->exists()) {
                DB::table('page_gate')->where('path', $path)->update($values);
            } else {
                DB::table('page_gate')->insert(['path' => $path, 'created_at' => now()] + $values);
            }
        }

        return count($candidates);
    }

    /** @return list<string> distinct internal paths linked from <main>, Arabic form, without the page itself */
    public static function internalLinks(string $html, string $self): array
    {
        $main = preg_match('/<main\b[^>]*>(.*)<\/main>/is', $html, $m) ? $m[1] : '';
        preg_match_all('/<a\b[^>]*href="([^"#?]+)/i', $main, $hrefs);
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $out = [];
        foreach ($hrefs[1] as $href) {
            $h = parse_url($href);
            if (isset($h['host']) && $h['host'] !== $host) {
                continue;
            }
            $p = rtrim(preg_replace('#^/en(?=/|$)#', '', $h['path'] ?? '') ?: '/', '/') ?: '/';
            if ($p !== $self && str_starts_with($p, '/')) {
                $out[$p] = true;
            }
        }

        return array_keys($out);
    }

    public static function passes(object $row): bool
    {
        return $row->band_ok && $row->schema_ok && $row->out_links >= self::MIN_OUT_LINKS && (int) $row->lint_errors === 0;
    }

    public static function markReady(string $path, ?int $userId, bool $ready = true): void
    {
        DB::table('page_gate')->where('path', $path)->update(['ready_at' => $ready ? now() : null, 'ready_by' => $ready ? $userId : null, 'updated_at' => now()]);
    }

    /** Releases a page that passes and is ticked ready; false when it does not. */
    public static function release(string $path, ?int $userId): bool
    {
        $row = DB::table('page_gate')->where('path', $path)->first();
        if (! $row || ! $row->ready_at || ! self::passes($row)) {
            return false;
        }
        DB::table('page_gate')->where('path', $path)->update(['released_at' => now(), 'released_by' => $userId, 'updated_at' => now()]);

        return true;
    }

    public static function hold(string $path): void
    {
        DB::table('page_gate')->where('path', $path)->update(['released_at' => null, 'released_by' => null, 'updated_at' => now()]);
    }
}
