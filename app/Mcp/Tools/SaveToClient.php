<?php

namespace App\Mcp\Tools;

use App\Models\AiBrainItem;
use App\Models\Client;
use App\Support\AiWorkspace\Brain;
use App\Support\AiWorkspace\Connector;
use App\Support\AiWorkspace\OutputContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/** `save_to_client` (spec v1 §4): writes to AdsRanger only, never to an ad account. Every save passes the quality gate. */
class SaveToClient extends AiTool
{
    protected string $name = 'save_to_client';

    protected string $description = 'Saves something to a client\'s brain in AdsRanger so every AI the person uses can read it next time: a rule, decision, brief, learning, output, or a link to this chat. AdsRanger checks it (numbers, Arabic, claims) and returns a quality score. For a weekly summary or ad copy set, send output_type and a structured output; if it does not fit, you get the exact fields to fix and may try once more.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->required(),
            'type' => $schema->string()->enum(AiBrainItem::TYPES)->required(),
            'title' => $schema->string()->description('Short title, up to 250 characters.')->required(),
            'body' => $schema->string()->description('The text to save (for everything except structured outputs).'),
            'output_type' => $schema->string()->enum(array_keys(OutputContract::types())),
            'output' => $schema->object()->description('Structured output matching output_type.'),
            'url' => $schema->string()->description('For chat_link: the https link to this chat.'),
            'try' => $schema->integer()->description('1 on the first attempt, 2 after fixing the fields we returned.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'], 'type' => ['required', 'in:'.implode(',', AiBrainItem::TYPES)], 'title' => ['required', 'string', 'max:250'],
            'body' => ['nullable', 'string', 'max:20000'], 'output_type' => ['nullable', 'in:'.implode(',', array_keys(OutputContract::types()))],
            'output' => ['nullable', 'array'], 'url' => ['nullable', 'string', 'max:500'], 'try' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);
        $client = $this->client($data['client_id']);
        if (! $client) {
            return $this->denied();
        }

        return $this->store($client, $data + ['playbook' => null]);
    }

    /** @param array<string, mixed> $data */
    protected function store(Client $client, array $data): Response
    {
        $connection = $this->connection();
        $result = Brain::save($client, [
            'type' => $data['type'], 'title' => $data['title'], 'body' => $data['body'] ?? null, 'output_type' => $data['output_type'] ?? null,
            'output' => $data['output'] ?? null, 'url' => $data['url'] ?? null, 'try' => $data['try'] ?? 1, 'ai' => $connection->provider,
            'playbook' => $data['playbook'] ?? null, 'allowed_client_ids' => $connection->allowedIds(),
        ], $connection->user);

        if (! $result['ok']) {
            Connector::anomaly($connection, 'schema_failures', 'Repeated output format errors.');

            return Response::error("The output doesn't fit the {$data['output_type']} format. Fix these and call again with try = 2:\n- ".implode("\n- ", $result['errors']));
        }
        $item = $result['item'];
        if (collect($item->checks)->contains('rule', 'instructions_in_output')) {
            Connector::anomaly($connection, 'injections', 'Saved text contained instructions aimed at the system.');
        }

        return $this->ok([
            'saved' => true, 'id' => $item->id, 'state' => $item->state, 'quality_score' => $item->score, 'band' => $item->band,
            'band_meaning' => ['ready' => 'Ready', 'review' => 'Needs a look', 'blocked' => 'Not for clients'][$item->band] ?? null,
            'fixed_automatically' => collect($item->checks)->where('fix', 'auto')->pluck('rule')->values()->all(),
            'to_fix' => collect($item->checks)->where('fix', '!=', 'auto')->map(fn ($c) => $c['rule'].': '.$c['found'])->values()->all(),
            'note' => $item->state === 'needs_formatting' ? 'Kept as an internal draft that needs formatting; it cannot be sent to clients.' : null,
        ]);
    }
}
