<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** XS-06 (R4): something the agency asked its client to approve on the report link, and the client's answer. */
class ClientApproval extends Model
{
    public const KINDS = ['plan', 'budget', 'creative', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }
}
