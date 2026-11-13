<?php

namespace App\Support\Reach;

use App\Models\ReachPublisher;
use App\Models\ReachWidget;
use InvalidArgumentException;

/**
 * Reach R-4 (E2-064): deterministic publisher install kits, built from the publisher's ref and web widget only.
 *  - WordPress plugin .zip (no external libraries; a stored zip written here byte for byte, fixed 1980-01-01 timestamps,
 *    so the same publisher always gets the same file): async tag.js, [reach_offers] shortcode + optional auto-insert
 *    after posts, /llms.txt that adds the publisher's promo-code block, and schema.org Offer JSON-LD only on pages
 *    where the offers block is shown (markup must match visible content).
 *  - Cloudflare Worker snippet doing the /llms.txt merge and the async tag on HTML pages.
 */
class PublisherKit
{
    public const PLUGIN_SLUG = 'adsranger-reach';

    public const PLUGIN_VERSION = '1.0.0';

    /** @return array{base: string, ref: string, widget: string, placement: string, site: string} */
    public static function values(ReachPublisher $publisher, ReachWidget $widget): array
    {
        $values = [
            'base' => rtrim((string) config('app.url'), '/'),
            'ref' => (string) $publisher->ref,
            'widget' => (string) $widget->public_key,
            'placement' => (string) $widget->placement,
            'site' => (string) $publisher->domain_or_app,
        ];
        if (! preg_match('/^[a-z0-9]{4,16}$/', $values['ref']) || ! preg_match('/^[A-Za-z0-9_]{4,40}$/', $values['widget'])
            || ! preg_match('/^[A-Za-z0-9\-]{1,32}$/', $values['placement']) || ! preg_match('#^https?://[A-Za-z0-9.\-:]+$#', $values['base'])) {
            throw new InvalidArgumentException('unsafe kit value');
        }

        return $values;
    }

    public static function wordpressZip(ReachPublisher $publisher, ReachWidget $widget): string
    {
        $v = self::values($publisher, $widget);

        return self::zip([
            self::PLUGIN_SLUG.'/'.self::PLUGIN_SLUG.'.php' => self::pluginPhp($v),
            self::PLUGIN_SLUG.'/readme.txt' => self::readme($v),
        ]);
    }

    /** @param  array{base: string, ref: string, widget: string, placement: string, site: string}  $v */
    public static function pluginPhp(array $v): string
    {
        $base = var_export($v['base'], true);
        $ref = var_export($v['ref'], true);
        $widget = var_export($v['widget'], true);
        $placement = var_export($v['placement'], true);
        $version = var_export(self::PLUGIN_VERSION, true);

        return <<<PHP
<?php
/**
 * Plugin Name: AdsRanger Reach Offers
 * Description: Shows your Reach offers with the async tag, adds your promo codes to /llms.txt and marks the shown offers with schema.org Offer JSON-LD.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: adsranger-reach
 */

if (! defined('ABSPATH')) {
    exit;
}

define('ADSRANGER_REACH_BASE', {$base});
define('ADSRANGER_REACH_REF', {$ref});
define('ADSRANGER_REACH_WIDGET', {$widget});
define('ADSRANGER_REACH_PLACEMENT', {$placement});
define('ADSRANGER_REACH_VERSION', {$version});

/** The offers box. min-height keeps the layout still while the tag fills it. */
function adsranger_reach_box(\$placement = ADSRANGER_REACH_PLACEMENT)
{
    \$GLOBALS['adsranger_reach_shown'] = true;
    wp_enqueue_script('adsranger-reach');

    return '<div data-reach="' . esc_attr(ADSRANGER_REACH_WIDGET) . '" data-placement="' . esc_attr(preg_replace('/[^A-Za-z0-9\-]/', '', (string) \$placement)) . '" style="min-height:160px"></div>';
}

add_action('wp_enqueue_scripts', function () {
    wp_register_script('adsranger-reach', ADSRANGER_REACH_BASE . '/reach/v1/tag.js', array(), null, array('in_footer' => true, 'strategy' => 'async'));
});

add_filter('script_loader_tag', function (\$tag, \$handle) {
    return (\$handle === 'adsranger-reach' && strpos(\$tag, ' async') === false) ? str_replace(' src=', ' async src=', \$tag) : \$tag;
}, 10, 2);

add_shortcode('reach_offers', function (\$atts) {
    \$atts = shortcode_atts(array('placement' => ADSRANGER_REACH_PLACEMENT), \$atts, 'reach_offers');

    return adsranger_reach_box(\$atts['placement']);
});

/** After the content of single posts; turn off with add_filter('adsranger_reach_auto_insert', '__return_false'). */
add_filter('the_content', function (\$content) {
    if (! is_singular('post') || ! in_the_loop() || ! is_main_query() || has_shortcode(\$content, 'reach_offers') || ! apply_filters('adsranger_reach_auto_insert', true)) {
        return \$content;
    }

    return \$content . adsranger_reach_box();
});

/** Fetches a Reach URL with a short cache; returns null when Reach doesn't answer (nothing is made up). */
function adsranger_reach_get(\$path, \$ttl)
{
    \$key = 'adsranger_reach_' . md5(\$path);
    \$cached = get_transient(\$key);
    if (\$cached !== false) {
        return \$cached === '' ? null : \$cached;
    }
    \$res = wp_remote_get(ADSRANGER_REACH_BASE . \$path, array('timeout' => 3));
    \$body = (! is_wp_error(\$res) && wp_remote_retrieve_response_code(\$res) === 200) ? (string) wp_remote_retrieve_body(\$res) : '';
    set_transient(\$key, \$body, \$body === '' ? 60 : \$ttl);

    return \$body === '' ? null : \$body;
}

/** /llms.txt: your own text (Settings › Reading is not needed; edit the option adsranger_reach_llms_intro) + your promo codes. */
add_action('init', function () {
    \$path = (string) parse_url(isset(\$_SERVER['REQUEST_URI']) ? \$_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
    if (\$path !== '/llms.txt') {
        return;
    }
    \$intro = trim((string) get_option('adsranger_reach_llms_intro', '# ' . get_bloginfo('name') . "\\n\\n> " . get_bloginfo('description')));
    \$offers = adsranger_reach_get('/reach/v1/llms/' . ADSRANGER_REACH_REF . '.txt', 300);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    echo \$intro . "\\n" . (\$offers !== null ? "\\n" . trim(\$offers) . "\\n" : '');
    exit;
});

/** schema.org Offer list, only on pages that show the offers box. */
add_action('wp_footer', function () {
    if (empty(\$GLOBALS['adsranger_reach_shown']) || ! is_singular()) {
        return;
    }
    \$json = adsranger_reach_get('/reach/v1/ai-feed?w=' . rawurlencode(ADSRANGER_REACH_WIDGET), 3600);
    \$data = \$json !== null ? json_decode(\$json, true) : null;
    if (! is_array(\$data) || (\$data['@type'] ?? '') !== 'ItemList' || empty(\$data['itemListElement'])) {
        return;
    }
    echo '<script type="application/ld+json">' . wp_json_encode(\$data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\\n";
}, 99);

PHP;
    }

    /** @param  array{base: string, ref: string, widget: string, placement: string, site: string}  $v */
    public static function readme(array $v): string
    {
        return "=== AdsRanger Reach Offers ===\n"
            ."Requires at least: 6.0\nTested up to: 6.7\nRequires PHP: 7.4\nStable tag: ".self::PLUGIN_VERSION."\nLicense: GPLv2 or later\n\n"
            ."Built for {$v['site']} (placement {$v['placement']}).\n\n"
            ."== What it does ==\n"
            ."* Loads the Reach tag asynchronously, so it never blocks your page.\n"
            ."* Adds the offers box after single posts, or wherever you put [reach_offers].\n"
            ."* Serves /llms.txt with your site intro and your promo codes (only when the site has no llms.txt file of its own).\n"
            ."* Adds schema.org Offer markup only on pages that show the offers box.\n\n"
            ."== Install ==\n"
            ."Plugins > Add New > Upload Plugin, choose this .zip, then Activate.\n\n"
            ."Earnings shown in the Reach portal are estimates until launch; no payout is made before then.\n";
    }

    public static function cloudflareWorker(ReachPublisher $publisher, ReachWidget $widget): string
    {
        $v = self::values($publisher, $widget);

        return <<<JS
// Cloudflare Worker for {$v['site']}: adds your Reach promo codes to /llms.txt and the async Reach tag to HTML pages.
// Put the offers box where it should show: <div data-reach="{$v['widget']}" data-placement="{$v['placement']}" style="min-height:160px"></div>
const REACH = "{$v['base']}";
const REF = "{$v['ref']}";

export default {
  async fetch(request) {
    const url = new URL(request.url);
    if (url.pathname === "/llms.txt") {
      const [own, offers] = await Promise.all([
        fetch(request).catch(() => null),
        fetch(REACH + "/reach/v1/llms/" + REF + ".txt", { cf: { cacheTtl: 300 } }).catch(() => null),
      ]);
      const parts = [own && own.ok ? (await own.text()).trim() : "", offers && offers.ok ? (await offers.text()).trim() : ""];
      return new Response(parts.filter(Boolean).join("\\n\\n") + "\\n", {
        headers: { "content-type": "text/plain; charset=utf-8", "cache-control": "public, max-age=300" },
      });
    }
    const response = await fetch(request);
    if (!(response.headers.get("content-type") || "").includes("text/html")) {
      return response;
    }
    return new HTMLRewriter()
      .on("body", { element(el) { el.append('<script async src="' + REACH + '/reach/v1/tag.js"></script>', { html: true }); } })
      .transform(response);
  },
};

JS;
    }

    /**
     * Minimal stored (uncompressed) zip with fixed DOS time 1980-01-01 00:00, files in the given order: same input, same bytes.
     *
     * @param  array<string, string>  $files  path => contents
     */
    public static function zip(array $files): string
    {
        $data = '';
        $central = '';
        $offset = 0;
        $dosTime = 0;
        $dosDate = (0 << 9) | (1 << 5) | 1; // 1980-01-01
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $size = strlen($content);
            $nameLen = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034B50, 10, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0).$name;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 0x031E, 10, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0100644 << 16, $offset).$name;
            $data .= $local.$content;
            $offset += strlen($local) + $size;
        }

        return $data.$central.pack('VvvvvVVv', 0x06054B50, 0, 0, count($files), count($files), strlen($central), $offset, 0);
    }
}
