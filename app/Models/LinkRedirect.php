<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** BX-09: a 301 added from /admin for an old or mistyped URL that other sites link to. */
class LinkRedirect extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
