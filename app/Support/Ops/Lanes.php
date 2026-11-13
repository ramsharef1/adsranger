<?php

namespace App\Support\Ops;

/** EG-04: which queue a job goes on (high · default · slow); null = the connection's default while lanes are off. */
class Lanes
{
    public static function for(string $jobClass): ?string
    {
        if (! config('ops.lanes_enabled')) {
            return null;
        }
        $base = class_basename($jobClass);
        foreach ((array) config('ops.lanes') as $lane => $jobs) {
            if (in_array($base, (array) $jobs, true)) {
                return $lane;
            }
        }

        return 'default';
    }
}
