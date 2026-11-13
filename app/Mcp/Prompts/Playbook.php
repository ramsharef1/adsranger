<?php

namespace App\Mcp\Prompts;

use App\Support\AiWorkspace\Connector;
use App\Support\AiWorkspace\Playbooks;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/** Connector prompt for one M1 playbook; the envelope is shared with the in-app AI box (Playbooks). */
abstract class Playbook extends Prompt
{
    public function arguments(): array
    {
        return [new Argument('client_id', 'Client id from list_clients.', true), new Argument('notes', 'Anything the person added (goal, period, offer).', false)];
    }

    public function handle(Request $request): Response
    {
        $connection = Connector::current();
        $client = $connection ? Connector::client($connection, $request->get('client_id')) : null;
        if (! $client) {
            return Response::error('This client is not shared with this AI.');
        }
        $envelope = Playbooks::envelope($client, $this->name(), (string) $request->get('notes', ''), $request->get('dialect'), $request->get('platform'));
        $type = Playbooks::all()[$this->name()]['output_type'];
        $envelope['rules'][] = $type ? "Then call create_draft with output_type \"$type\"." : 'Then call save_to_client to keep what is useful.';

        return Response::text(json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
