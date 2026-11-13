<?php

namespace App\Mcp\Tools;

use App\Support\AiWorkspace\ContextPack;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetResults extends AiTool
{
    protected string $name = 'get_results';

    protected string $description = 'Spend, results, cost per result, ROAS, CTR, impressions, link clicks and revenue for one client: last 7 days, the 7 days before, and last 30 days, in the client\'s currency, each with its source and the date the data runs to. Quote only these numbers.';

    public function schema(JsonSchema $schema): array
    {
        return ['client_id' => $schema->integer()->description('Client id from list_clients.')->required()];
    }

    public function handle(Request $request): Response
    {
        $client = $this->client($request->get('client_id'));
        if (! $client) {
            return $this->denied();
        }
        $pack = ContextPack::forAi($client);

        return $this->ok(['client' => $pack['client'], 'currency' => $pack['currency'], 'data_until' => $pack['data_until'], 'periods' => $pack['periods'],
            'facts' => $pack['facts'], 'note' => $pack['facts'] === [] ? 'No synced data for this client yet. Say so; do not estimate.' : null]);
    }
}
