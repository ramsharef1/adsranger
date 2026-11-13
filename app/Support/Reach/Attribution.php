<?php

namespace App\Support\Reach;

use App\Models\ReachCopyIntent;
use App\Models\ReachCouponCode;
use App\Models\ReachOffer;
use App\Models\ReachPublisher;
use Illuminate\Support\Carbon;

/**
 * FR-REACH R-1: 3-tier coupon attribution waterfall.
 *  1. Sub-ID  ?subid={publisher ref}_{placement}  (exact, from the click)
 *  2. VIP / AI-bot code: a code owned by one publisher (influencer code, or the code we print in its AI feed)
 *  3. Rotating pool code (10–20 per offer): the newest copy intent of that code within 24 h before the sale
 * No match → the conversion is not credited to anyone (never guessed).
 */
class Attribution
{
    /** @return array{publisher: ReachPublisher, placement: string, tier: string}|null */
    public static function resolve(?string $subId, ?string $coupon, ?ReachOffer $offer, ?Carbon $at = null): ?array
    {
        $at ??= now();

        if ($subId !== null && preg_match('/^([a-z0-9]{4,16})(?:_([A-Za-z0-9\-]{1,32}))?$/', trim($subId), $m)) {
            $publisher = ReachPublisher::where('ref', $m[1])->first();
            if ($publisher) {
                return ['publisher' => $publisher, 'placement' => $m[2] ?? 'main', 'tier' => 'subid'];
            }
        }

        $coupon = $coupon !== null ? trim($coupon) : '';
        if ($coupon === '') {
            return null;
        }
        $codes = ReachCouponCode::query()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($coupon)])
            ->when($offer, fn ($q) => $q->where('offer_id', $offer->id))
            ->get();

        $owned = $codes->first(fn (ReachCouponCode $c) => in_array($c->tier, ['vip', 'ai_bot'], true) && $c->publisher_id);
        if ($owned) {
            return ['publisher' => $owned->publisher, 'placement' => $owned->tier, 'tier' => $owned->tier];
        }

        $pool = $codes->where('tier', 'pool')->pluck('id');
        if ($pool->isNotEmpty()) {
            $intent = ReachCopyIntent::whereIn('coupon_code_id', $pool)
                ->whereBetween('copied_at', [$at->copy()->subHours((int) config('reach.attribution.copy_intent_hours')), $at])
                ->orderByDesc('copied_at')->first();
            if ($intent && ($publisher = ReachPublisher::find($intent->publisher_id))) {
                return ['publisher' => $publisher, 'placement' => $intent->placement, 'tier' => 'pool'];
            }
        }

        return null;
    }

    /** Tier 3: hand out the least-recently copied pool code of an offer and record the copy intent. */
    public static function copyPoolCode(ReachOffer $offer, ReachPublisher $publisher, string $placement = 'main'): ?ReachCouponCode
    {
        $codes = $offer->codes()->where('tier', 'pool')->get();
        if ($codes->isEmpty()) {
            return null;
        }
        $last = ReachCopyIntent::whereIn('coupon_code_id', $codes->pluck('id'))
            ->selectRaw('coupon_code_id, MAX(copied_at) as last_at')->groupBy('coupon_code_id')->pluck('last_at', 'coupon_code_id');
        $code = $codes->sortBy(fn (ReachCouponCode $c) => [(string) ($last[$c->id] ?? ''), $c->id])->first();
        ReachCopyIntent::create(['coupon_code_id' => $code->id, 'publisher_id' => $publisher->id, 'placement' => mb_substr($placement, 0, 32), 'copied_at' => now()]);

        return $code;
    }
}
