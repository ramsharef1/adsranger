<?php

namespace App\Jobs;

use App\Models\Agency;
use App\Models\Client;
use App\Support\HealthScore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Nightly: store each active client's health score (#35) for the list, the pacing board and the history. */
class SnapshotHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;

    public function __construct(public ?int $agencyId = null) {}

    public function handle(): void
    {
        Agency::query()->when($this->agencyId, fn ($q) => $q->whereKey($this->agencyId))->with('clients')->each(function (Agency $a) {
            $today = now($a->timezone ?: 'UTC')->startOfDay();
            foreach ($a->clients->where('status', 'active') as $c) {
                try {
                    static::store($c, $today);
                } catch (Throwable $e) {
                    Log::warning('Health snapshot failed', ['client_id' => $c->id, 'error' => $e->getMessage()]);
                }
            }
        });
    }

    public static function store(Client $c, $today): array
    {
        $h = HealthScore::compute($c, $today);
        $parts = array_map(fn ($p) => ['points' => $p['points'], 'max' => $p['max'], 'note' => $p['note']], $h['parts']);
        DB::table('client_health')->upsert([[
            'agency_id' => $c->agency_id, 'client_id' => $c->id, 'date' => $today->copy()->subDay()->toDateString(),
            'score' => $h['score'], 'parts' => json_encode($parts), 'created_at' => now(), 'updated_at' => now(),
        ]], ['client_id', 'date'], ['score', 'parts', 'updated_at']);
        $c->forceFill(['health_score' => $h['score'], 'health_parts' => $parts, 'health_at' => now()])->saveQuietly();

        return $h;
    }
}
