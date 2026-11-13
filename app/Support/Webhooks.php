<?php

namespace App\Support;

use App\Jobs\DeliverAgencyWebhookJob;
use App\Models\Agency;
use App\Models\AgencyWebhook;
use Closure;

/**
 * #77 / #115 Outbound webhooks: which events exist, which target URLs are allowed, and firing an event.
 * Targets must be https on port 443 and resolve only to public addresses (SSRF guard, same rule as LandingPages);
 * the job re-checks and pins the resolved IP at send time, and never follows redirects.
 */
class Webhooks
{
    /** Events that something in the app actually sends. */
    public const EVENTS = ['alert.critical', 'report.sent'];

    /** Test hook: replaces DNS resolution (host => public IP or null). */
    public static ?Closure $resolver = null;

    public static function resolve(string $host): ?string
    {
        return static::$resolver ? (static::$resolver)($host) : LandingPages::publicIp($host);
    }

    /** Null when the URL is an allowed target, else a short reason (translated by the caller). */
    public static function targetProblem(string $url): ?string
    {
        $p = parse_url(trim($url));
        if (! $p || strtolower($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            return 'Use an https:// address.';
        }
        if (isset($p['port']) && (int) $p['port'] !== 443) {
            return 'Use the standard https port.';
        }
        if (strlen($url) > 500) {
            return 'The address is too long.';
        }

        return static::resolve($p['host']) ? null : 'The address must point to a public server.';
    }

    /** Webhooks have their own held-back module (D-229): they only fire, and the feed only answers, where it is on. */
    public static function liveFor(Agency $agency): bool
    {
        return Modules::enabled('webhooks') || Modules::isPreviewAgency($agency);
    }

    /** Queue one delivery per active webhook subscribed to the event (all clients, or this client). Returns how many. */
    public static function fire(Agency $agency, string $event, array $data, ?int $clientId = null): int
    {
        if (! in_array($event, self::EVENTS, true) || ! static::liveFor($agency)) {
            return 0;
        }
        $n = 0;
        $hooks = AgencyWebhook::where('agency_id', $agency->id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('client_id')->when($clientId, fn ($q) => $q->orWhere('client_id', $clientId)))->get();
        foreach ($hooks as $hook) {
            if (in_array($event, (array) $hook->events, true)) {
                DeliverAgencyWebhookJob::dispatch($hook->id, $event, $data);
                $n++;
            }
        }

        return $n;
    }
}
