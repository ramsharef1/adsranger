<?php

namespace App\Support\AiWorkspace;

use App\Mcp\Servers\AdsRangerServer;
use App\Models\Agency;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;

/** WS-10 / FS-10 (R7): every tool an external AI can call, a switch per tool per workspace, and the last calls. */
class ToolCatalogue
{
    /** @return array<string, string> tool name => description, read from the connector server itself */
    public static function tools(): array
    {
        $prop = new ReflectionProperty(AdsRangerServer::class, 'tools');
        $out = [];
        foreach ((array) $prop->getDefaultValue() as $class) {
            $tool = app($class);
            $out[$tool->name()] = $tool->description();
        }

        return $out;
    }

    public static function off(Agency $a, string $tool): bool
    {
        return in_array($tool, (array) ($a->ai_tools_off ?? []), true);
    }

    public static function toggle(Agency $a, string $tool): void
    {
        $off = (array) ($a->ai_tools_off ?? []);
        $off = in_array($tool, $off, true) ? array_values(array_diff($off, [$tool])) : [...$off, $tool];
        $a->forceFill(['ai_tools_off' => $off ?: null])->save();
    }

    public static function log(Agency $a, ?int $connectionId, string $tool, ?int $clientId, int $status): void
    {
        DB::table('ai_tool_calls')->insert(['agency_id' => $a->id, 'ai_connection_id' => $connectionId, 'tool' => mb_substr($tool, 0, 60),
            'client_id' => $clientId, 'status' => $status, 'created_at' => now()]);
    }

    /** @return list<object> */
    public static function recent(Agency $a, int $limit = 50): array
    {
        return DB::table('ai_tool_calls')->where('agency_id', $a->id)->orderByDesc('id')->limit($limit)->get()->all();
    }
}
