@php
    // D-61 / 911 part 3 point 9 / ADSR-031 part 2: trial banner under the top bar. Info severity, dismissible per day.
    // Owner/admin get the "See plans" link; other roles see the same text without it (EX-37).
    // Self-signup agencies after the end date: read-only / closed wording (warning), with the dates.
    use App\Support\PlanGate;
    use App\Support\Trial;
    use App\Support\TrialNotice;
    $agency = \Filament\Facades\Filament::getTenant();
    $days = $agency ? Trial::daysLeft($agency) : null;
    $ended = $agency && Trial::hasEnded($agency);
    $stage = $agency ? Trial::stage($agency) : Trial::NONE;
@endphp
@if ($agency && (! is_null($days) || $ended))
    @php
        $canUpgrade = PlanGate::canUpgrade(auth()->user(), $agency);
        $text = match (true) {
            $stage === Trial::READ_ONLY => __('Your trial ended — choose a plan').' · '.__('Read-only until :date', ['date' => TrialNotice::date(Trial::readOnlyUntil($agency))]),
            in_array($stage, [Trial::EXPIRED, Trial::ARCHIVED], true) => __('Your trial ended — choose a plan').' · '.__('Data kept until :date', ['date' => TrialNotice::date(Trial::deleteOn($agency))]),
            $ended => __('Your Pro trial has ended. Your workspace is on :tier now.', ['tier' => PlanGate::tierLabel(PlanGate::tier($agency))]),
            default => trans_choice('{0} Last day of your Pro trial|{1} :count day left in your Pro trial|[2,*] :count days left in your Pro trial', $days),
        };
        $key = 'adsr-trial-banner-'.$agency->id.'-'.($ended ? $stage : 'on').'-'.now()->toDateString();
    @endphp
    <div x-data="{ shown: true, key: @js($key) }"
         x-init="try { shown = localStorage.getItem(key) !== '1' } catch (e) { shown = true }"
         x-show="shown" role="status" data-trial-banner
         style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:8px 16px;font-size:14px;background:{{ $ended ? 'rgba(245,158,11,.14)' : 'rgba(20,184,166,.12)' }};border-bottom:1px solid rgba(148,163,184,.3)">
        <span style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <x-filament::icon :icon="$ended ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-information-circle'" style="width:18px;height:18px;opacity:.8" />
            <span>{{ $text }}</span>
            @if ($canUpgrade)
                · <a href="{{ \App\Filament\Pages\PlanUsage::getUrl() }}" style="text-decoration:underline;font-weight:600;min-height:44px;display:inline-flex;align-items:center">{{ __('See plans') }}</a>
            @elseif (Trial::isReadOnly($agency))
                · <span>{{ __('Ask your workspace owner to choose a plan.') }}</span>
            @endif
        </span>
        <button type="button" x-on:click="shown = false; try { localStorage.setItem(key, '1') } catch (e) {}"
                aria-label="{{ __('Hide for today') }}" title="{{ __('Hide for today') }}"
                style="background:transparent;border:0;cursor:pointer;min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center">
            <x-filament::icon icon="heroicon-m-x-mark" style="width:18px;height:18px" />
        </button>
    </div>
@endif
