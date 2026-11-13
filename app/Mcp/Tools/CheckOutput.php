<?php

namespace App\Mcp\Tools;

use App\Support\AiWorkspace\CleanUp;
use App\Support\AiWorkspace\ContextPack;
use App\Support\AiWorkspace\OutputContract;
use App\Support\AiWorkspace\QualityGate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** `check_output` (spec v1 §11.2 stage 3): check text before sending it; nothing is saved. */
#[IsReadOnly]
class CheckOutput extends AiTool
{
    protected string $name = 'check_output';

    protected string $description = 'Checks a text for one client before it is sent: numbers against AdsRanger data, Arabic rules, claims, structure. Returns a 0–100 score, a band (Ready, Needs a look, Not for clients), what was fixed and what to fix. Nothing is saved.';

    public function schema(JsonSchema $schema): array
    {
        return ['client_id' => $schema->integer()->required(), 'text' => $schema->string()->required(),
            'output_type' => $schema->string()->enum(array_keys(OutputContract::types())), 'output' => $schema->object()];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['client_id' => ['required', 'integer'], 'text' => ['required', 'string', 'max:20000'],
            'output_type' => ['nullable', 'in:'.implode(',', array_keys(OutputContract::types()))], 'output' => ['nullable', 'array']]);
        $client = $this->client($data['client_id']);
        if (! $client) {
            return $this->denied();
        }
        $clean = CleanUp::pass($data['text']);
        $gate = QualityGate::check($clean['text'], ContextPack::for($client)['facts'], ['type' => $data['output_type'] ?? null, 'output' => $data['output'] ?? null,
            'client_facing' => true, 'client_id' => $client->id, 'allowed_client_ids' => $this->connection()->allowedIds()]);

        return $this->ok(['quality_score' => $gate['score'], 'band' => $gate['band'], 'clean_text' => $gate['text'], 'filler_lines_hidden' => $clean['hidden'],
            'actions_found' => $clean['actions'], 'no_fix' => $clean['no_fix'], 'checks' => $gate['checks']]);
    }
}
