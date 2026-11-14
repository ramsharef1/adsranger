<?php

namespace App\Http\Controllers;

use App\Models\ClientProductFeed;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\Attribution\ShoppingFeedBuilder;
use Illuminate\Http\Response;

/**
 * E2-071: Tokenized product feed endpoint (/feeds/{token}.xml), gated behind intelligence_hub (404 while off)
 * and explicitly marked noindex, nofollow.
 */
class ProductFeedController extends Controller
{
    public function show(string $token): Response
    {
        $feed = ClientProductFeed::with(['agency', 'client'])->where('feed_token', $token)->first();

        abort_unless(
            $feed
            && $feed->client
            && $feed->client->status !== 'archived'
            && ProfitAndAttributionManager::moduleLiveFor($feed->agency),
            404
        );

        $xml = ShoppingFeedBuilder::buildXml($feed);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }
}
