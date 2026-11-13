<?php

namespace App\Models;

use App\Support\AiWorkspace\FreePlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AdAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_synced_at' => 'datetime', 'backfilled_at' => 'datetime', 'meta' => 'array', 'health' => 'array', 'health_checked_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(AdNetwork::class, 'ad_network_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    protected static function booted(): void
    {
        // Free plan (D-286, spec §17.2 A1 + E1): one Free workspace per ad account, and never a platform-disabled one.
        // The account is kept but stays inactive; the lite view says why.
        static::saving(function (AdAccount $a) {
            if ($a->is_active && ($a->isDirty('is_active') || ! $a->exists) && FreePlan::isFree($a->agency)
                && (FreePlan::adAccountTaken($a) || FreePlan::platformDisabled($a))) {
                $a->is_active = false;
            }
        });

        // History follows the account when it moves to another client.
        static::updated(function (AdAccount $a) {
            if ($a->wasChanged('client_id')) {
                DailyMetric::where('ad_account_id', $a->id)->update(['client_id' => $a->client_id]);
                Alert::where('ad_account_id', $a->id)->update(['client_id' => $a->client_id]);
                foreach (['metric_breakdowns', 'google_campaign_daily', 'search_terms', 'google_keywords', 'google_asset_groups'] as $t) {
                    DB::table($t)->where('ad_account_id', $a->id)->update(['client_id' => $a->client_id]);
                }
            }
        });
    }
}
