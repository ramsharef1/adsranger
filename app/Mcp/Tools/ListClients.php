<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListClients extends AiTool
{
    protected string $name = 'list_clients';

    protected string $description = 'Lists the clients (brands) the person allowed this AI to read in their AdsRanger workspace: id, name, currency.';

    public function handle(Request $request): Response
    {
        $c = $this->connection();
        $clients = Client::query()->where('agency_id', $c->agency_id)->whereIn('id', $c->allowedIds())->orderBy('name')->get(['id', 'name', 'currency']);

        return $this->ok(['workspace' => $c->agency->name, 'clients' => $clients->map(fn ($x) => ['id' => $x->id, 'name' => $x->name, 'currency' => $x->currency ?: 'USD'])->all()]);
    }
}
