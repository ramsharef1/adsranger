<?php

namespace App\Support\Attribution;

use App\Models\Client;
use App\Models\ClientProductFeed;
use App\Models\SkuPerformanceSnapshot;
use App\Support\PlanGate;
use App\Support\TextClean;
use DOMDocument;
use Illuminate\Support\Str;

/**
 * E2-071: Tokenized Shopping Feed Builder (/feeds/{token}.xml) gated under intelligence_hub & noindex.
 * Injects custom_label_0 (hero|sidekick|villain|zombie) from sku_performance_snapshots, filters OOS items,
 * and enriches AR/EN product titles with zero platform branding leaks.
 */
class ShoppingFeedBuilder
{
    public const VALID_QUADRANTS = ['hero', 'sidekick', 'villain', 'zombie'];

    /**
     * @param  array<string, mixed>  $rules
     */
    public static function createOrUpdateFeed(
        Client $client,
        string $channel = 'google_merchant',
        bool $includeOutOfStock = false,
        array $rules = [],
        ?int $feedId = null,
    ): ClientProductFeed {
        $ch = in_array($channel, ClientProductFeed::CHANNELS, true) ? $channel : 'google_merchant';

        if ($feedId !== null) {
            $existing = ClientProductFeed::where('agency_id', $client->agency_id)
                ->where('client_id', $client->id)
                ->find($feedId);
            if ($existing) {
                $existing->update([
                    'channel' => $ch,
                    'include_out_of_stock' => $includeOutOfStock,
                    'rules' => $rules,
                ]);

                return $existing;
            }
        }

        return ClientProductFeed::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'feed_token' => Str::random(48),
            'channel' => $ch,
            'include_out_of_stock' => $includeOutOfStock,
            'rules' => $rules,
        ]);
    }

    public static function buildXml(ClientProductFeed $feed, ?int $maxSkus = null): string
    {
        $client = $feed->client;
        $rules = is_array($feed->rules) ? $feed->rules : [];
        $currency = strtoupper((string) ($client->currency ?: 'SAR'));
        $decimals = in_array($currency, config('adsranger.three_decimal_currencies', []), true) ? 3 : 2;
        $baseUrl = rtrim((string) ($rules['base_url'] ?? 'https://store.example'), '/');

        $snapshots = SkuPerformanceSnapshot::where('agency_id', $feed->agency_id)
            ->where('client_id', $feed->client_id)
            ->orderByDesc('period_date')
            ->orderByDesc('id')
            ->get()
            ->unique('sku_code')
            ->values();

        if (! $feed->include_out_of_stock) {
            $snapshots = $snapshots->filter(function (SkuPerformanceSnapshot $sku): bool {
                return (int) $sku->stock_qty > 0
                    && ! in_array((string) $sku->stock_status, ['out_of_stock', 'broken_variant'], true);
            })->values();
        }

        if ($maxSkus === null && $feed->agency) {
            $maxSkus = PlanGate::quota($feed->agency, 'shopping_feed_skus');
        }
        if ($maxSkus !== null && $maxSkus > 0) {
            $snapshots = $snapshots->take($maxSkus);
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $rss = $doc->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $doc->appendChild($rss);

        $channelNode = $doc->createElement('channel');
        $rss->appendChild($channelNode);
        $channelNode->appendChild($doc->createElement('title', htmlspecialchars($client->name.' Catalog', ENT_XML1 | ENT_QUOTES, 'UTF-8')));
        $channelNode->appendChild($doc->createElement('link', htmlspecialchars($baseUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
        $channelNode->appendChild($doc->createElement('description', htmlspecialchars('Product catalog feed ('.$feed->channel.')', ENT_XML1 | ENT_QUOTES, 'UTF-8')));

        foreach ($snapshots as $sku) {
            $item = $doc->createElement('item');
            $enrichedTitle = self::enrichTitle((string) $sku->product_title, $rules);
            $inStock = (int) $sku->stock_qty > 0 && ! in_array((string) $sku->stock_status, ['out_of_stock', 'broken_variant'], true);
            $unitPrice = (int) $sku->orders_count > 0 && (float) $sku->gross_revenue > 0
                ? ((float) $sku->gross_revenue / (int) $sku->orders_count)
                : max(0.0, (float) ($rules['default_price'] ?? 0.0));
            $priceStr = TextClean::western(number_format($unitPrice, $decimals, '.', '')).' '.$currency;

            $quadrant = in_array((string) $sku->quadrant, self::VALID_QUADRANTS, true)
                ? (string) $sku->quadrant
                : ProfitAndAttributionManager::classifySkuQuadrant((float) $sku->ad_spend, (float) $sku->net_profit, (int) $sku->orders_count);

            $appendG = function (string $tag, string $val) use ($doc, $item): void {
                $el = $doc->createElement('g:'.$tag);
                $el->appendChild($doc->createTextNode($val));
                $item->appendChild($el);
            };

            $appendG('id', (string) $sku->sku_code);
            $appendG('title', $enrichedTitle);
            $appendG('link', $baseUrl.'/products/'.rawurlencode((string) $sku->sku_code));
            $appendG('availability', $inStock ? 'in_stock' : 'out_of_stock');
            $appendG('price', $priceStr);
            $appendG('custom_label_0', $quadrant);
            $appendG('custom_label_1', (float) $sku->repeat_ltv_90d > 0 ? 'repeat_buyer_ltv' : 'standard');

            $channelNode->appendChild($item);
        }

        $feed->forceFill(['last_generated_at' => now()])->save();

        return (string) $doc->saveXML();
    }

    /**
     * Enriches Arabic or English product titles with optional brand prefix and attribute suffix.
     *
     * @param  array<string, mixed>  $rules
     */
    public static function enrichTitle(string $rawTitle, array $rules): string
    {
        $title = trim($rawTitle);
        $isArabic = (bool) preg_match('/\p{Arabic}/u', $title);
        $brand = trim((string) ($isArabic ? ($rules['brand_ar'] ?? $rules['brand_en'] ?? '') : ($rules['brand_en'] ?? $rules['brand_ar'] ?? '')));
        $attr = trim((string) ($isArabic ? ($rules['attribute_ar'] ?? $rules['append_attribute'] ?? '') : ($rules['attribute_en'] ?? $rules['append_attribute'] ?? '')));

        if ($brand !== '' && mb_stripos($title, $brand) === false) {
            $title = $brand.' - '.$title;
        }
        if ($attr !== '' && mb_stripos($title, $attr) === false) {
            $title .= ' | '.$attr;
        }

        return mb_substr($title, 0, 150);
    }
}
