<?php

namespace App\Mcp\Tools;

use App\Support\AiWorkspace\OutputContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/** `create_draft` (spec v1 §4): a structured draft in AdsRanger. Nothing goes live and nothing is sent. */
class CreateDraft extends SaveToClient
{
    protected string $name = 'create_draft';

    protected string $description = 'Creates a draft weekly summary or ad copy set for a client in AdsRanger. It must fit the output format; nothing goes live or is sent to anyone. Returns the quality score.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->required(),
            'output_type' => $schema->string()->enum(array_keys(OutputContract::types()))->required(),
            'title' => $schema->string()->required(),
            'output' => $schema->object()->required(),
            'playbook' => $schema->string()->description('The playbook used, if any.'),
            'try' => $schema->integer(),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'], 'output_type' => ['required', 'in:'.implode(',', array_keys(OutputContract::types()))],
            'title' => ['required', 'string', 'max:250'], 'output' => ['required', 'array'], 'playbook' => ['nullable', 'string', 'max:40'],
            'try' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);
        $client = $this->client($data['client_id']);
        if (! $client) {
            return $this->denied();
        }

        return $this->store($client, $data + ['type' => 'output']);
    }
}
