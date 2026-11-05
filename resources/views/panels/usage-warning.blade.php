@php
    // EX-50 point 6: warning banner at 80 % of a quota (tracked ad accounts, seats = members + pending invites),
    // owner/admin only (they can act on it), with "See plans". Dismissible per day. At the limit itself the
    // create action still opens the quota notice (QuotaNotice). Not shown while the workspace is read-only.
    use App\Support\PlanGate;
    use App\Support\UsageWarning;
    $agency = \Filament\Facades\Filament::getTenant();
    $rows = $agency && PlanGate::canUpgrade(auth()->user(), $agency) ? UsageWarning::rows($agency) : [];
@endphp
@if ($rows)
    @php $key = 'adsr-usage-warning-'.$agency->id.'-'.now()->toDateString(); @endphp
    <div x-data="{ shown: true, key: @js($key) }"
         x-init="try { shown = localStorage.getItem(key) !== '1' } catch (e) { shown = true }"
         x-show="shown" role="status" data-usage-warning
         style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:8px 16px;font-size:14px;background:rgba(245,158,11,.14);border-bottom:1px solid rgba(148,163,184,.3)">
        <span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width:18px;height:18px;opacity:.8" />
            <strong>{{ UsageWarning::title($agency) }}</strong>
            <span>{{ collect($rows)->pluck('text')->implode(' · ') }}</span>
            · <a href="{{ \App\Filament\Pages\PlanUsage::getUrl() }}" style="text-decoration:underline;font-weight:600;min-height:44px;display:inline-flex;align-items:center">{{ __('See plans') }}</a>
        </span>
        <button type="button" x-on:click="shown = false; try { localStorage.setItem(key, '1') } catch (e) {}"
                aria-label="{{ __('Hide for today') }}" title="{{ __('Hide for today') }}"
                style="background:transparent;border:0;cursor:pointer;min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center">
            <x-filament::icon icon="heroicon-m-x-mark" style="width:18px;height:18px" />
        </button>
    </div>
@endif
