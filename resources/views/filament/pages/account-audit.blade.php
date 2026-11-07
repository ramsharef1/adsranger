@php
    use App\Support\Severity;
    $muted = 'opacity:.7;font-size:12.5px';
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px;display:grid;gap:8px';
    $days = (int) config('audit.window_days');
    $daysPhrase = \App\Support\ArabicCount::phrase($days, 'days');
    $bdi = fn ($v) => '<bdi dir="auto">'.e((string) $v).'</bdi>';
    $ltr = fn ($v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
    $money = fn ($amount, $cur) => $ltr(number_format((float) $amount, 0).' '.$cur);
    $title = function ($f) use ($bdi, $ltr) {
        $p = $f->params ?? [];
        $k = 'audit.checks.'.$f->check_key;

        return match ($f->check_key) {
            'pacing' => e(__($k.'.title_'.(($p['direction'] ?? 'over') === 'under' ? 'under' : 'over'))),
            'spend_cap' => __($k.'.title', ['pct' => $ltr($p['pct'] ?? '')]),
            default => __($k.'.title', ['name' => $bdi($f->entity_name ?? '—')]),
        };
    };
    $detail = function ($f) use ($bdi, $ltr, $days, $daysPhrase) {
        $p = $f->params ?? [];
        $k = 'audit.checks.'.$f->check_key;

        return match ($f->check_key) {
            'rejected_ads' => __($k.'.detail', ['status' => $ltr($p['status'] ?? ''), 'ad_set' => $bdi($p['ad_set'] ?? '—'), 'days' => $days, 'days_phrase' => $daysPhrase]),
            'tired_ad_set' => ($p['ctr_drop'] ?? null) !== null
                ? __($k.'.detail_drop', ['frequency' => $ltr($p['frequency'] ?? ''), 'ctr_drop' => $ltr($p['ctr_drop'])])
                : __($k.'.detail', ['frequency' => $ltr($p['frequency'] ?? '')]),
            'pacing' => __($k.'.detail', ['pace' => $ltr($p['pace'] ?? '')]),
            'learning_limited' => __($k.'.detail', ['results' => $ltr($p['results'] ?? ''), 'target' => $ltr($p['target'] ?? ''), 'days' => $ltr($p['days'] ?? $days), 'days_phrase' => \App\Support\ArabicCount::phrase((int) ($p['days'] ?? $days), 'days')]),
            default => __($k.'.detail', ['days' => $days, 'days_phrase' => $daysPhrase]),
        };
    };
@endphp
<x-filament-panels::page>
    <div style="{{ $muted }};font-size:13px;line-height:1.7">{{ __('audit.intro', ['days' => $days, 'days_phrase' => $daysPhrase]) }}</div>

    <div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
        <div style="min-width:220px;flex:1;max-width:360px">
            <label for="aa-client" style="{{ $label }}">{{ __('Client') }}</label>
            <select id="aa-client" wire:model.live="clientId" style="{{ $field }}">
                <option value="">{{ __('audit.choose_client') }}</option>
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($client && $canManage && $hasAccounts)
            <x-filament::button wire:click="runAudit" wire:loading.attr="disabled" icon="heroicon-o-arrow-path" style="min-height:44px">{{ __('audit.run') }}</x-filament::button>
        @endif
    </div>

    @if (! $client)
        <p style="{{ $muted }}">{{ __('audit.choose_client') }}</p>
    @elseif (! $hasAccounts)
        <p style="{{ $muted }}">{{ __('audit.no_accounts') }}</p>
    @elseif (! $audit)
        <p style="{{ $muted }}">{{ __('audit.not_run') }}</p>
    @else
        <div style="display:flex;gap:16px;flex-wrap:wrap;{{ $muted }}">
            <span>{!! __('audit.last_run', ['when' => $ltr($audit->ran_at->timezone(\Filament\Facades\Filament::getTenant()->timezone ?: 'UTC')->format('Y-m-d H:i'))]) !!} · {{ __('audit.trigger.'.$audit->trigger) }}</span>
            <span>{!! __('audit.window', ['from' => $ltr($audit->window_from->format('Y-m-d')), 'to' => $ltr($audit->window_to->format('Y-m-d'))]) !!}</span>
            @if ($audit->money_at_risk > 0)
                <strong>{!! __('audit.total', ['amount' => $money($audit->money_at_risk, $audit->currency)]) !!}</strong>
            @endif
        </div>

        @if ($shown->isEmpty() && $hiddenCount === 0)
            <div style="{{ $card }}">{{ __('audit.none', ['days' => $days, 'days_phrase' => $daysPhrase]) }}</div>
        @endif

        @php $dismissedCount = $shown->whereNotNull('dismissed_at')->count(); @endphp
        <div style="display:grid;gap:12px">
            @foreach ($shown as $f)
                @continue($f->dismissed_at && ! $showDismissed)
                @php $sev = Severity::normalize($f->severity); @endphp
                <article style="{{ $card }}{{ $f->dismissed_at ? ';opacity:.6' : '' }}">
                    <div style="display:flex;gap:10px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            <span style="{{ $muted }}">{{ $f->rank }}.</span>
                            <x-filament::badge :color="Severity::color($sev)" :icon="Severity::icon($sev)">{{ Severity::label($sev) }}</x-filament::badge>
                            <strong style="font-size:15px">{!! $title($f) !!}</strong>
                            @if ($f->dismissed_at)<span style="{{ $muted }}">· {{ __('audit.dismissed') }}</span>@endif
                        </div>
                        <div style="text-align:end">
                            @if ($f->money_at_risk !== null)
                                <div style="{{ $muted }}">{{ __('audit.money_at_risk') }}</div>
                                <strong style="font-size:16px">{!! $money($f->money_at_risk, $audit->currency) !!}</strong>
                            @elseif ($f->results_at_risk !== null)
                                <div style="{{ $muted }}">{{ __('audit.results_at_risk') }}</div>
                                <strong style="font-size:16px">{!! $ltr((int) round($f->results_at_risk)) !!}</strong>
                            @endif
                        </div>
                    </div>
                    <p style="margin:0;font-size:13.5px;line-height:1.7">{!! $detail($f) !!}</p>
                    <p style="margin:0;font-size:13.5px;line-height:1.7"><strong>{{ __('audit.fix') }}:</strong> {{ __('audit.checks.'.$f->check_key.'.fix') }}</p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                        @if ($f->fix_url)
                            <x-filament::button tag="a" :href="$f->fix_url" target="_blank" rel="noopener noreferrer" color="gray" size="sm" icon="heroicon-o-arrow-top-right-on-square" style="min-height:44px"
                                :aria-label="__('audit.open').' '.__('audit.opens_new_tab')">{{ __('audit.open') }}</x-filament::button>
                        @endif
                        @if ($canManage)
                            @if ($f->dismissed_at)
                                <x-filament::button wire:click="restore({{ $f->id }})" color="gray" size="sm" style="min-height:44px">{{ __('audit.restore') }}</x-filament::button>
                            @else
                                <x-filament::button wire:click="dismiss({{ $f->id }})" color="gray" size="sm" outlined style="min-height:44px">{{ __('audit.dismiss') }}</x-filament::button>
                            @endif
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if ($dismissedCount > 0)
            <label style="display:flex;gap:8px;align-items:center;min-height:44px;font-size:13px;cursor:pointer">
                <input type="checkbox" wire:model.live="showDismissed"> {{ __('audit.show_dismissed') }} ({{ $dismissedCount }})
            </label>
        @endif

        @if ($hiddenCount > 0)
            <div style="{{ $card }}">
                <strong>{{ trans_choice('audit.more_locked', $hiddenCount, ['count' => $hiddenCount]) }}</strong>
                <x-locked-state-body feature="account_audit_full" tier="pro" :can-upgrade="$canUpgrade" />
            </div>
        @endif
    @endif
</x-filament-panels::page>
