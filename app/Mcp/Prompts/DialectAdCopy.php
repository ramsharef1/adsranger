<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Server\Prompts\Argument;

class DialectAdCopy extends Playbook
{
    protected string $name = 'dialect_ad_copy';

    protected string $title = 'Dialect ad copy «نصوص إعلانية باللهجة»';

    protected string $description = 'Ad copy for one client in a chosen Arabic dialect (Gulf, Levantine, Egyptian, Maghrebi) within the platform\'s length limits.';

    public function arguments(): array
    {
        return [...parent::arguments(), new Argument('dialect', 'gulf, levantine, egyptian or maghrebi.', true), new Argument('platform', 'meta, google_ads, tiktok or snapchat.', true)];
    }
}
