<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientOnboardingLink extends Model
{
    protected $fillable = [
        'agency_id',
        'client_id',
        'token',
        'prospect_name',
        'website_url',
        'requested_networks',
        'granted_networks',
        'brand_notes',
        'intake',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_networks' => 'array',
            'granted_networks' => 'array',
            'intake' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
