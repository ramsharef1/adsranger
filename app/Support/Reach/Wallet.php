<?php

namespace App\Support\Reach;

use App\Models\ReachLedgerEntry;
use App\Models\ReachPublisher;
use App\Models\ReachWallet;
use Illuminate\Support\Facades\DB;

/**
 * FR-REACH R-1 dual wallet. Every movement writes a ledger row with status "estimate" (D-262): before launch nothing
 * is paid, so withdrawals and early cashouts are recorded requests.
 *
 *  pending ──(clear after N days)──▶ cleared ──▶ withdrawal request (min $50) | ad credit (+15%)
 *  early cashout: 85% of un-advanced pending is advanced (6% fee), 15% held as reserve; refunds net against the
 *  reserve first, then against cleared (which may go below zero and nets future earnings).
 */
class Wallet
{
    public static function for(ReachPublisher $publisher): ReachWallet
    {
        return ReachWallet::firstOrCreate(['publisher_id' => $publisher->id], ['currency' => config('reach.wallet.currency')]);
    }

    /** Credits a conversion to pending (+ the 5% referral override to the referrer). */
    public static function creditConversion(ReachPublisher $publisher, array $attrs): ReachLedgerEntry
    {
        return DB::transaction(function () use ($publisher, $attrs): ReachLedgerEntry {
            $entry = ReachLedgerEntry::create($attrs + [
                'publisher_id' => $publisher->id, 'conversion_type' => 'conversion', 'status' => 'estimate', 'wallet_state' => 'pending',
            ]);
            static::bump($publisher, 'pending_balance', $entry->net_commission);

            if ($publisher->referred_by_id && ($referrer = $publisher->referrer) && $entry->net_commission > 0) {
                $override = Reach::amount($entry->net_commission * (float) config('reach.referral_override'));
                ReachLedgerEntry::create([
                    'publisher_id' => $referrer->id, 'offer_id' => $entry->offer_id, 'conversion_type' => 'referral_override',
                    'gross_amount' => $entry->net_commission, 'net_commission' => $override, 'status' => 'estimate', 'wallet_state' => 'pending',
                    'converted_at' => $entry->converted_at, 'meta' => ['from_publisher_id' => $publisher->id, 'from_entry_id' => $entry->id],
                ]);
                static::bump($referrer, 'pending_balance', $override);
            }

            return $entry;
        });
    }

    /** A network reversed a sale: auto-net it out of pending, the reserve, then cleared. */
    public static function refund(ReachLedgerEntry $original, ?string $externalId = null, ?int $endpointId = null): ReachLedgerEntry
    {
        return DB::transaction(function () use ($original, $externalId, $endpointId): ReachLedgerEntry {
            $publisher = $original->publisher;
            $w = ReachWallet::whereKey(static::for($publisher)->id)->lockForUpdate()->firstOrFail();
            $amount = $original->net_commission;
            $netted = [];

            if ($original->wallet_state === 'pending') {
                $w->pending_balance = Reach::amount($w->pending_balance - $amount);
                $netted['pending'] = $amount;
                if ($w->advanced_balance > $w->pending_balance) {
                    $over = Reach::amount($w->advanced_balance - max(0, $w->pending_balance));
                    $w->advanced_balance = Reach::amount(max(0, $w->pending_balance));
                    $fromReserve = min($w->reserve_balance, $over);
                    $w->reserve_balance = Reach::amount($w->reserve_balance - $fromReserve);
                    $w->cleared_balance = Reach::amount($w->cleared_balance - ($over - $fromReserve));
                    $netted += ['reserve' => Reach::amount($fromReserve), 'cleared' => Reach::amount($over - $fromReserve)];
                }
            } else {
                $w->cleared_balance = Reach::amount($w->cleared_balance - $amount);
                $netted['cleared'] = $amount;
            }
            $w->save();
            $original->update(['wallet_state' => 'refunded']);

            $override = ReachLedgerEntry::where('conversion_type', 'referral_override')->where('meta->from_entry_id', $original->id)
                ->whereIn('wallet_state', ['pending', 'cleared'])->first();
            if ($override) {
                static::refund($override);
            }

            return ReachLedgerEntry::create([
                'publisher_id' => $publisher->id, 'offer_id' => $original->offer_id, 'postback_endpoint_id' => $endpointId,
                'conversion_type' => 'refund', 'gross_amount' => -$original->gross_amount, 'net_commission' => -$amount,
                'status' => 'estimate', 'external_conversion_id' => $externalId, 'converted_at' => now(),
                'meta' => ['refund_of' => $original->id, 'netted' => $netted],
            ]);
        });
    }

    /** Moves pending conversions older than the lock period to cleared, releasing the matching reserve. */
    public static function clearDue(?ReachPublisher $only = null): int
    {
        $cutoff = now()->subDays((int) config('reach.wallet.clear_after_days'));
        $count = 0;
        ReachLedgerEntry::query()
            ->whereIn('conversion_type', ['conversion', 'referral_override'])->where('wallet_state', 'pending')
            ->where(fn ($q) => $q->where('converted_at', '<=', $cutoff)->orWhere(fn ($q) => $q->whereNull('converted_at')->where('created_at', '<=', $cutoff)))
            ->when($only, fn ($q) => $q->where('publisher_id', $only->id))
            ->orderBy('id')->each(function (ReachLedgerEntry $e) use (&$count): void {
                DB::transaction(function () use ($e): void {
                    $w = static::for($e->publisher);
                    $amount = $e->net_commission;
                    $advanced = min($w->advanced_balance, $amount);
                    $release = $advanced > 0 && $w->advanced_balance > 0 ? Reach::amount($w->reserve_balance * ($advanced / $w->advanced_balance)) : 0.0;
                    $w->update([
                        'pending_balance' => Reach::amount($w->pending_balance - $amount),
                        'advanced_balance' => Reach::amount($w->advanced_balance - $advanced),
                        'reserve_balance' => Reach::amount($w->reserve_balance - $release),
                        // The advanced part was already paid early; only its reserve comes back. The rest clears in full.
                        'cleared_balance' => Reach::amount($w->cleared_balance + ($amount - $advanced) + $release),
                    ]);
                    $e->update(['wallet_state' => 'cleared', 'cleared_at' => now()]);
                });
                $count++;
            });

        return $count;
    }

    /** @return array{advance: float, fee: float, net: float, reserve: float} what an early cashout would give now */
    public static function earlyCashoutQuote(ReachPublisher $publisher): array
    {
        $w = static::for($publisher);
        $eligible = max(0, Reach::amount($w->pending_balance - $w->advanced_balance));
        $advance = Reach::amount($eligible * (float) config('reach.wallet.early_cashout_cap'));
        $fee = Reach::amount($advance * (float) config('reach.wallet.early_cashout_fee'));

        return ['advance' => $advance, 'fee' => $fee, 'net' => Reach::amount($advance - $fee), 'reserve' => Reach::amount($eligible - $advance)];
    }

    public static function requestEarlyCashout(ReachPublisher $publisher): ?ReachLedgerEntry
    {
        $q = static::earlyCashoutQuote($publisher);
        if ($q['advance'] <= 0) {
            return null;
        }

        return DB::transaction(function () use ($publisher, $q): ReachLedgerEntry {
            $w = static::for($publisher);
            $w->update([
                'advanced_balance' => Reach::amount($w->advanced_balance + $q['advance'] + $q['reserve']),
                'reserve_balance' => Reach::amount($w->reserve_balance + $q['reserve']),
            ]);

            return ReachLedgerEntry::create([
                'publisher_id' => $publisher->id, 'conversion_type' => 'early_cashout', 'gross_amount' => $q['advance'],
                'net_commission' => $q['net'], 'status' => 'estimate', 'wallet_state' => 'reserve', 'meta' => $q,
            ]);
        });
    }

    /** @return ReachLedgerEntry|string the request, or why not: below_minimum · over_balance */
    public static function requestWithdrawal(ReachPublisher $publisher, float $amount): ReachLedgerEntry|string
    {
        $w = static::for($publisher);
        $amount = Reach::amount($amount);
        if ($amount < (float) config('reach.wallet.min_withdrawal')) {
            return 'below_minimum';
        }
        if ($amount > $w->cleared_balance) {
            return 'over_balance';
        }

        return DB::transaction(function () use ($publisher, $w, $amount): ReachLedgerEntry {
            $w->update(['cleared_balance' => Reach::amount($w->cleared_balance - $amount)]);

            return ReachLedgerEntry::create([
                'publisher_id' => $publisher->id, 'conversion_type' => 'withdrawal_request', 'gross_amount' => $amount,
                'net_commission' => -$amount, 'status' => 'estimate', 'wallet_state' => 'cleared',
            ]);
        });
    }

    /** @return ReachLedgerEntry|string cleared earnings → ad credit with the +15% bonus, or over_balance */
    public static function reinvestAsAdCredit(ReachPublisher $publisher, float $amount): ReachLedgerEntry|string
    {
        $w = static::for($publisher);
        $amount = Reach::amount($amount);
        if ($amount <= 0 || $amount > $w->cleared_balance) {
            return 'over_balance';
        }
        $credit = Reach::amount($amount * (1 + (float) config('reach.wallet.ad_credit_bonus')));

        return DB::transaction(function () use ($publisher, $w, $amount, $credit): ReachLedgerEntry {
            $w->update(['cleared_balance' => Reach::amount($w->cleared_balance - $amount), 'ad_credit_balance' => Reach::amount($w->ad_credit_balance + $credit)]);

            return ReachLedgerEntry::create([
                'publisher_id' => $publisher->id, 'conversion_type' => 'ad_credit', 'gross_amount' => $amount,
                'net_commission' => $credit, 'status' => 'estimate', 'wallet_state' => 'ad_credit',
            ]);
        });
    }

    private static function bump(ReachPublisher $publisher, string $column, float $by): void
    {
        $w = static::for($publisher);
        $w->update([$column => Reach::amount($w->{$column} + $by)]);
    }
}
