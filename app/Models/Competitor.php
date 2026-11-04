<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A competitor a client wants to watch in the ad libraries (#50). */
class Competitor extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['countries' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Public ad library links, per country where relevant. */
    public function links(): array
    {
        $out = [];
        $countries = $this->countries ?: [$this->client?->market ?: 'ALL'];
        foreach ($countries as $cc) {
            $q = ['active_status' => 'active', 'ad_type' => 'all', 'country' => $cc, 'media_type' => 'all'];
            $q += $this->facebook_page_id ? ['view_all_page_id' => $this->facebook_page_id, 'search_type' => 'page']
                : ['q' => $this->name, 'search_type' => 'keyword_unordered'];
            $out[] = ['Meta Ad Library · '.$cc, 'https://www.facebook.com/ads/library/?'.http_build_query($q)];
        }
        if ($this->website) {
            $domain = preg_replace('#^https?://(www\.)?#', '', rtrim($this->website, '/'));
            $out[] = ['Google Ads Transparency', 'https://adstransparency.google.com/?'.http_build_query(['domain' => $domain, 'region' => $countries[0] === 'ALL' ? 'anywhere' : $countries[0]])];
        } else {
            $out[] = ['Google Ads Transparency', 'https://adstransparency.google.com/'];
        }

        return $out;
    }
}
