<?php

namespace App\Support;

use Illuminate\Support\Str;

/** One short reference per request, shown on 5xx pages and written into the log context (UX-9, EX-26). */
class ErrorRef
{
    private ?string $id = null;

    public function id(): string
    {
        return $this->id ??= strtoupper(Str::random(8));
    }
}
