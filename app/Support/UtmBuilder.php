<?php

namespace App\Support;

/**
 * UTM builder (#86): adds utm_* parameters to a landing page address, with per-network presets.
 * Existing utm_* values in the address are replaced; every other parameter and the #fragment are kept.
 * Network placeholders such as {campaignid} or {{campaign.name}} are left unencoded so the network can fill them in.
 */
class UtmBuilder
{
    public const FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'];

    /**
     * Network presets: suggested source / medium and the network's own placeholders (filled in at click time).
     * Only networks whose placeholder syntax is documented are listed with placeholders.
     *
     * @return array<string, array{label: string, source: string, medium: string, campaign?: string, content?: string, term?: string, id?: string}>
     */
    public static function presets(): array
    {
        return [
            'meta' => ['label' => 'Meta (Facebook / Instagram)', 'source' => '{{site_source_name}}', 'medium' => 'paid_social',
                'campaign' => '{{campaign.name}}', 'content' => '{{ad.name}}', 'id' => '{{campaign.id}}'],
            'google' => ['label' => 'Google Ads', 'source' => 'google', 'medium' => 'cpc',
                'campaign' => '{campaignid}', 'content' => '{creative}', 'term' => '{keyword}', 'id' => '{campaignid}'],
            'microsoft' => ['label' => 'Microsoft Advertising', 'source' => 'bing', 'medium' => 'cpc',
                'campaign' => '{CampaignId}', 'content' => '{AdId}', 'term' => '{keyword}', 'id' => '{CampaignId}'],
            'tiktok' => ['label' => 'TikTok', 'source' => 'tiktok', 'medium' => 'paid_social',
                'campaign' => '__CAMPAIGN_NAME__', 'content' => '__CID_NAME__', 'id' => '__CAMPAIGN_ID__'],
            'snapchat' => ['label' => 'Snapchat', 'source' => 'snapchat', 'medium' => 'paid_social'],
            'linkedin' => ['label' => 'LinkedIn', 'source' => 'linkedin', 'medium' => 'paid_social'],
            'x' => ['label' => 'X (Twitter)', 'source' => 'x', 'medium' => 'paid_social'],
            'email' => ['label' => 'Email newsletter', 'source' => 'newsletter', 'medium' => 'email'],
            'whatsapp' => ['label' => 'WhatsApp', 'source' => 'whatsapp', 'medium' => 'messaging'],
        ];
    }

    /** True for a network placeholder ({...}, {{...}} or __NAME__), which must not be URL-encoded. */
    public static function isPlaceholder(string $v): bool
    {
        return (bool) preg_match('/^(\{\{[A-Za-z0-9_.]+\}\}|\{[A-Za-z0-9_:]+\}|__[A-Z0-9_]+__)$/', $v);
    }

    /**
     * Build the tracked address. Null when the base is not a valid http(s) URL.
     *
     * @param  array<string, ?string>  $utm  keys from FIELDS (with or without the utm_ prefix)
     */
    public static function build(string $base, array $utm): ?string
    {
        $base = trim($base);
        $p = parse_url($base);
        if (! $p || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        // Keep every non-utm parameter exactly as written (no re-encoding of the user's own query).
        $keep = [];
        foreach ($p['query'] ?? '' ? explode('&', $p['query']) : [] as $pair) {
            if ($pair === '') {
                continue;
            }
            $k = strtolower(urldecode(explode('=', $pair, 2)[0]));
            if (! in_array($k, self::FIELDS, true)) {
                $keep[] = $pair;
            }
        }
        $add = [];
        foreach (self::FIELDS as $f) {
            $v = $utm[$f] ?? $utm[substr($f, 4)] ?? null;
            $v = trim((string) $v);
            if ($v === '') {
                continue;
            }
            $add[] = $f.'='.(static::isPlaceholder($v) ? $v : rawurlencode($v));
        }
        $url = strtolower($p['scheme']).'://'.(isset($p['user']) ? $p['user'].(isset($p['pass']) ? ':'.$p['pass'] : '').'@' : '')
            .$p['host'].(isset($p['port']) ? ':'.$p['port'] : '').($p['path'] ?? '/');
        $query = implode('&', array_merge($keep, $add));

        return $url.($query !== '' ? '?'.$query : '').(isset($p['fragment']) ? '#'.$p['fragment'] : '');
    }

    /**
     * Friendly warnings about the values (not errors: the link still builds).
     *
     * @param  array<string, ?string>  $utm
     * @return list<string> English sentences (translated in the view)
     */
    public static function warnings(string $base, array $utm): array
    {
        $out = [];
        $source = trim((string) ($utm['utm_source'] ?? $utm['source'] ?? ''));
        $medium = trim((string) ($utm['utm_medium'] ?? $utm['medium'] ?? ''));
        $campaign = trim((string) ($utm['utm_campaign'] ?? $utm['campaign'] ?? ''));
        if ($source === '' || $medium === '' || $campaign === '') {
            $out[] = 'Source, medium and campaign are all needed for clean reports in GA4.';
        }
        foreach (self::FIELDS as $f) {
            $v = trim((string) ($utm[$f] ?? $utm[substr($f, 4)] ?? ''));
            if ($v === '' || static::isPlaceholder($v)) {
                continue;
            }
            if ($v !== mb_strtolower($v)) {
                $out[] = 'Use lowercase: GA4 treats "Facebook" and "facebook" as two different sources.';
                break;
            }
        }
        foreach (self::FIELDS as $f) {
            $v = (string) ($utm[$f] ?? $utm[substr($f, 4)] ?? '');
            if (! static::isPlaceholder(trim($v)) && preg_match('/\s/', trim($v))) {
                $out[] = 'Spaces become %20 in the link; use _ or - instead.';
                break;
            }
        }
        foreach (self::FIELDS as $f) {
            $v = trim((string) ($utm[$f] ?? $utm[substr($f, 4)] ?? ''));
            if ($v !== '' && ! static::isPlaceholder($v) && preg_match('/[^\x00-\x7F]/', $v)) {
                $out[] = 'Arabic letters work, but reports show them encoded; use Latin, e.g. ramadan_2027.';
                break;
            }
        }
        if (preg_match('/[?&]utm_/i', $base)) {
            $out[] = 'The address already had UTM parameters; they were replaced.';
        }
        if (str_starts_with(strtolower(trim($base)), 'http://')) {
            $out[] = 'The page is not HTTPS: browsers warn visitors and some networks reject it.';
        }

        return array_values(array_unique($out));
    }
}
