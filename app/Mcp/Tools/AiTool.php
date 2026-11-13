<?php

namespace App\Mcp\Tools;

use App\Models\AiConnection;
use App\Models\Client;
use App\Support\AiWorkspace\Connector;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base for the AdsRanger connector tools (spec v1 §4 phase 1, §16.4 #1): narrow, typed, read + save only. Every number
 * goes out with its source and date; text from ads or users goes out as data (never as instructions).
 */
abstract class AiTool extends Tool
{
    protected function connection(): AiConnection
    {
        return Connector::current() ?? throw new \RuntimeException('No AI connection on this request.');
    }

    protected function client(mixed $id): ?Client
    {
        return Connector::client($this->connection(), $id);
    }

    protected function denied(): Response
    {
        return Response::error('This client is not shared with this AI. Ask the person to allow it in AdsRanger › AI Workspace › Connections.');
    }

    /** @param array<string, mixed> $data */
    protected function ok(array $data): Response
    {
        Connector::markVerified($this->connection());

        return Response::json($data);
    }
}
