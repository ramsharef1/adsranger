<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
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

    protected function task(Request $request, string $notes): string
    {
        $dialect = in_array($request->get('dialect'), ['gulf', 'levantine', 'egyptian', 'maghrebi'], true) ? $request->get('dialect') : 'gulf';
        $platform = in_array($request->get('platform'), ['meta', 'google_ads', 'tiktok', 'snapchat'], true) ? $request->get('platform') : 'meta';

        return "Write 5 headlines and 3 primary texts for this client in the $dialect dialect for $platform (set platform and dialect in the output to these), within the length limits in output_schema, following the client rules."
            .($notes !== '' ? ' The person added: '.$notes : '');
    }

    protected function outputType(): ?string
    {
        return 'ad_copy_set';
    }
}
