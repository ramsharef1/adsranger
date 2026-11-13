<?php

namespace App\Mcp\Tools;

use App\Support\MenaCalendar;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SeasonCalendar extends AiTool
{
    protected string $name = 'season_calendar';

    protected string $description = 'MENA advertising seasons (Ramadan, Eid, White Friday, national days, school terms) between two dates, at most 400 days apart. Hijri dates are estimates where marked.';

    public function schema(JsonSchema $schema): array
    {
        return ['from' => $schema->string()->description('Start date, YYYY-MM-DD. Default: today.'),
            'to' => $schema->string()->description('End date, YYYY-MM-DD. Default: 120 days after the start.'),
            'market' => $schema->string()->description('Optional country code, e.g. SA, AE, JO, EG.')];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'market' => ['nullable', 'string', 'size:2']]);
        $from = Carbon::parse($data['from'] ?? now()->toDateString());
        $to = isset($data['to']) ? Carbon::parse($data['to']) : $from->copy()->addDays(120);
        if ($to->lt($from) || $from->diffInDays($to) > 400) {
            return Response::error('Use a range of at most 400 days, with "to" after "from".');
        }
        $events = MenaCalendar::events($from->toDateString(), $to->toDateString(), isset($data['market']) ? strtoupper($data['market']) : null);

        return $this->ok(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'source' => 'AdsRanger MENA season calendar',
            'events' => array_map(fn ($e) => ['name' => $e['label'], 'name_ar' => $e['ar'], 'start' => $e['start'], 'end' => $e['end'], 'estimated' => $e['estimated'], 'markets' => $e['markets']], $events)]);
    }
}
