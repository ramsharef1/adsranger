<?php

namespace App\Mcp\Tools;

use App\Models\Alert;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetAlerts extends AiTool
{
    protected string $name = 'get_alerts';

    protected string $description = 'Open alerts for one client with their level (Critical, Warning, Info), title, the campaign or ad set they concern, and when they were last seen.';

    protected const LEVELS = ['high' => 'Critical', 'medium' => 'Warning', 'low' => 'Info'];

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
        $alerts = Alert::query()->where('agency_id', $client->agency_id)->where('client_id', $client->id)->where('status', 'open')
            ->orderByRaw("case severity when 'high' then 0 when 'medium' then 1 else 2 end")->latest('last_seen_at')->limit(50)->get();

        return $this->ok(['client' => $client->name, 'alerts' => $alerts->map(fn (Alert $a) => [
            'level' => self::LEVELS[$a->severity] ?? 'Info', 'title' => strip_tags((string) $a->title), 'concerns' => $a->entity_name ? strip_tags((string) $a->entity_name) : null,
            'last_seen' => $a->last_seen_at?->toDateString(), 'source' => 'AdsRanger alerts',
        ])->all()]);
    }
}
