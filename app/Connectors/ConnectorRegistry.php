<?php

namespace App\Connectors;

use App\Models\AdNetwork;

class ConnectorRegistry
{
    protected const MAP = [
        'meta' => MetaConnector::class,
        'google_ads' => GoogleAdsConnector::class,
        'ga4' => Ga4Connector::class,
        'google_merchant' => MerchantConnector::class,
        'tiktok' => TikTokConnector::class,
        'snapchat' => SnapchatConnector::class,
        'microsoft' => MicrosoftConnector::class,
        'linkedin' => LinkedInConnector::class,
        'x' => XConnector::class,
    ];

    public static function for(AdNetwork $network): Connector
    {
        $class = self::MAP[$network->key] ?? GenericConnector::class;

        return new $class($network);
    }
}
