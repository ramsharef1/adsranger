<?php

namespace App\Support\Links;

use App\Models\AuditLog;
use App\Models\LinkRedirect;
use App\Support\PublicSite;
use Illuminate\Http\Request;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * BX-09 link reclamation: our own 404 log (path, hit count, last referring host only: no IP, no full referrer, no query)
 * plus the GSC "Top linked pages" export → pages other sites link to that no longer answer → 301 suggestions in /admin.
 * A 301 added there is served from the 404 handler, so it costs nothing on normal pages. Renames in config/redirects.php
 * are checked too.
 */
class Reclaim
{
    public const MAX_ROWS = 5000;

    public const CACHE = 'link_redirects.map';

    private const SKIP = '~^/(admin|app|livewire|api|oauth|storage|build|vendor|cgi-bin|\.well-known)(/|$)|wp-|\.(php|env|git|asp|aspx|jsp|sql|bak|zip|gz|tar|js|css|map|png|jpe?g|gif|webp|svg|ico|woff2?)$|/\.~i';

    /** Called for every 404 (bootstrap/app.php). Returns a 301 when one was added in /admin, else logs the path. */
    public static function onNotFound(Request $request): ?Response
    {
        try {
            if (! in_array($request->method(), ['GET', 'HEAD'], true) || $request->getHost() === config('ai_workspace.mcp_host') || $request->expectsJson()) {
                return null;
            }
            $path = self::normalise($request->path());
            $to = self::map()[$path] ?? null;
            if ($to !== null) {
                LinkRedirect::where('from_path', $path)->increment('hits');

                return redirect($to, 301);
            }
            if (! self::skip($path)) {
                self::log($path, $request->headers->get('referer'));
            }
        } catch (Throwable) {
            // Logging a 404 must never break the 404 page.
        }

        return null;
    }

    public static function normalise(string $path): string
    {
        $path = '/'.ltrim(rawurldecode(explode('?', $path)[0]), '/');
        if ($path !== '/' && $path !== '/en/') {
            $path = rtrim($path, '/');
        }

        return $path === '/en' ? '/en/' : mb_substr($path, 0, 300);
    }

    public static function skip(string $path): bool
    {
        return (bool) preg_match(self::SKIP, $path);
    }

    protected static function log(string $path, ?string $referer): void
    {
        $refHost = $referer ? strtolower((string) parse_url($referer, PHP_URL_HOST)) : null;
        $refHost = $refHost && ! in_array($refHost, OwnLinks::HOSTS, true) ? mb_substr($refHost, 0, 190) : null;
        $now = now();
        $updated = DB::table('missing_pages')->where('path', $path)->update(array_filter([
            'hits' => DB::raw('hits + 1'), 'last_seen_at' => $now, 'last_referrer_host' => $refHost,
        ], fn ($v) => $v !== null));
        if ($updated === 0 && DB::table('missing_pages')->count() < self::MAX_ROWS) {
            DB::table('missing_pages')->insertOrIgnore(['path' => $path, 'hits' => 1, 'last_referrer_host' => $refHost, 'first_seen_at' => $now, 'last_seen_at' => $now]);
        }
    }

    /** @return array<string, string> from → to */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE, fn () => LinkRedirect::where('active', true)->pluck('to_path', 'from_path')->all());
    }

    /**
     * True when a GET to this path is answered by one of our pages. A route with a slug (/glossary/{term}) counts as
     * live unless the path is in our 404 log, since the page itself answers 404 for an unknown slug.
     */
    public static function live(string $path): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (Throwable) {
            return false;
        }
        if (str_contains((string) $route->uri(), '{any}')) {
            return false;
        }

        return $route->parameterNames() === [] || ! DB::table('missing_pages')->where('path', self::normalise($path))->exists();
    }

    /**
     * Imports a GSC Links → "Top linked pages" CSV (pasted or the file text). Columns: page URL, incoming links,
     * linking sites. Rows for other hosts are skipped. Returns how many rows were read.
     */
    public static function importGsc(string $csv): int
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $n = 0;
        $now = now();
        foreach (preg_split('/\R/u', trim($csv)) as $line) {
            $cells = str_getcsv($line, str_contains($line, "\t") ? "\t" : ',', '"', '');
            $url = trim($cells[0] ?? '');
            if (preg_match('~^https?://~i', $url)) {
                if (! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), OwnLinks::HOSTS, true)) {
                    continue;
                }
                $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
            } elseif (str_starts_with($url, '/')) {
                $path = $url;
            } else {
                continue; // header row or a blank line
            }
            $num = fn ($v) => (int) preg_replace('/\D/', '', (string) $v);
            DB::table('gsc_linked_pages')->updateOrInsert(['path' => self::normalise($path)], [
                'links' => $num($cells[1] ?? 0), 'sites' => $num($cells[2] ?? 0), 'imported_at' => $now,
            ]);
            $n++;
        }
        if ($n > 0) {
            AuditLog::record('links.gsc_import', null, ['rows' => $n]);
        }

        return $n;
    }

    /**
     * Pages linked from outside (GSC) or hit as 404 that do not answer and have no 301 yet. Rows in both lists come first.
     *
     * @return list<array{path: string, hits: int, links: int, sites: int, both: bool, referrer: ?string, suggest: ?string}>
     */
    public static function suggestions(int $limit = 100): array
    {
        $map = self::map();
        $missing = DB::table('missing_pages')->where('ignored', false)->get()->keyBy('path');
        $gsc = DB::table('gsc_linked_pages')->get()->keyBy('path');
        $rows = [];
        foreach ($missing->keys()->merge($gsc->keys())->unique() as $path) {
            if (isset($map[$path]) || self::live($path)) {
                continue;
            }
            $m = $missing[$path] ?? null;
            $g = $gsc[$path] ?? null;
            if (! $m && DB::table('missing_pages')->where('path', $path)->where('ignored', true)->exists()) {
                continue;
            }
            $rows[] = ['path' => $path, 'hits' => (int) ($m->hits ?? 0), 'links' => (int) ($g->links ?? 0), 'sites' => (int) ($g->sites ?? 0),
                'both' => $m !== null && $g !== null, 'referrer' => $m->last_referrer_host ?? null, 'suggest' => self::suggestTarget($path)];
        }
        usort($rows, fn ($a, $b) => [$b['both'], $b['links'], $b['hits']] <=> [$a['both'], $a['links'], $a['hits']]);

        return array_slice($rows, 0, $limit);
    }

    /** Best live page for a dead path: a known rename, else the closest public page by slug, else its parent page. */
    public static function suggestTarget(string $path): ?string
    {
        $en = $path === '/en/' || str_starts_with($path, '/en/');
        $ar = $en ? (substr($path, 3) ?: '/') : $path;
        $renames = (array) config('redirects.renames');
        if (isset($renames[$ar])) {
            return $en ? '/en'.$renames[$ar] : $renames[$ar];
        }
        $candidates = collect(array_keys(PublicSite::pages()))
            ->merge(collect((array) config('seo_tools.tools'))->map(fn ($t) => '/tools/'.$t))->unique();
        $slug = mb_strtolower(basename($ar));
        $parent = dirname($ar);
        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $c) {
            if ($c === '/') {
                continue;
            }
            similar_text($slug, mb_strtolower(basename($c)), $pct);
            $pct += dirname($c) === $parent ? 10 : 0;
            if ($pct > $bestScore) {
                [$best, $bestScore] = [$c, $pct];
            }
        }
        $pick = $bestScore >= 60 ? $best : ($parent !== '/' && $parent !== '.' && self::live($parent) ? $parent : null);
        if ($pick === null) {
            return null;
        }
        $pick = $en ? ($pick === '/' ? '/en/' : '/en'.$pick) : $pick;

        return self::live($pick) ? $pick : null;
    }

    /** Adds a 301. Returns null when done, else the reason it was refused. */
    public static function addRedirect(string $from, string $to, ?int $userId = null): ?string
    {
        $from = self::normalise($from);
        $to = self::normalise(trim($to));
        if ($from === $to || ! str_starts_with(trim($to), '/')) {
            return 'bad_target';
        }
        if (self::live($from)) {
            return 'from_is_live'; // the page answers, a 301 here would never be reached
        }
        if (! self::live($to) || isset(self::map()[$to])) {
            return 'target_not_live'; // only to a page that answers, never a chain
        }
        $row = LinkRedirect::updateOrCreate(['from_path' => $from], ['to_path' => $to, 'active' => true, 'created_by' => $userId]);
        Cache::forget(self::CACHE);
        AuditLog::record('links.redirect_added', $row, ['from' => $from, 'to' => $to]);

        return null;
    }

    public static function disableRedirect(int $id): void
    {
        $row = LinkRedirect::find($id);
        if ($row) {
            $row->update(['active' => false]);
            Cache::forget(self::CACHE);
            AuditLog::record('links.redirect_disabled', $row, ['from' => $row->from_path]);
        }
    }

    public static function ignore(string $path): void
    {
        $path = self::normalise($path);
        DB::table('missing_pages')->updateOrInsert(['path' => $path], ['ignored' => true]);
    }

    /**
     * Every rename in config/redirects.php (AR and EN) must be a 301 to its new path, and the new path must answer.
     *
     * @return list<array{from: string, to: string, ok: bool}>
     */
    public static function renameCheck(): array
    {
        $out = [];
        foreach ((array) config('redirects.renames') as $old => $new) {
            foreach (['', '/en'] as $pre) {
                $from = $pre.$old;
                $to = $pre.$new;
                $ok = false;
                try {
                    $route = Route::getRoutes()->match(Request::create($from, 'GET'));
                    $ok = ltrim($route->getActionName(), '\\') === RedirectController::class
                        && ($route->defaults['destination'] ?? null) === $to && (int) ($route->defaults['status'] ?? 0) === 301
                        && self::live($to);
                } catch (Throwable) {
                }
                $out[] = ['from' => $from, 'to' => $to, 'ok' => $ok];
            }
        }

        return $out;
    }
}
