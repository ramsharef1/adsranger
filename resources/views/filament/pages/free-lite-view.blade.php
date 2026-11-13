@php
    $muted = 'opacity:.72;font-size:12.5px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px;display:grid;gap:8px';
    $ltr = fn ($v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
    $cur = $client?->currency ?: 'USD';
    $money = fn ($v) => $ltr(number_format((float) $v, 0).' '.$cur);
    $cpr = fn ($v) => $v === null ? '—' : $ltr(number_format((float) $v, 2).' '.$cur);
    $trend = ['up' => ['↑', __('More results than the week before')], 'down' => ['↓', __('Fewer results than the week before')], 'flat' => ['→', __('Same as the week before')]];
    $row = 'display:grid;grid-template-columns:minmax(0,2fr) repeat(3,minmax(0,1fr)) 32px;gap:8px;align-items:center;font-size:13.5px';
@endphp
<x-filament-panels::page>
    @if ($banner)
        <div role="status" style="border:1px solid rgba(15,118,110,.45);border-radius:12px;padding:12px;font-size:14px">
            {{ $banner[1] }}
            @if ($banner[0] === 'connect') <a href="{{ \App\Filament\Pages\AiConnectionsPage::getUrl() }}" style="color:#0F766E;text-decoration:underline">{{ __('Connections') }}</a>@endif
        </div>
    @endif
    @foreach ($refused as $a)
        <p style="font-size:13.5px;margin:0">
            {!! \App\Support\AiWorkspace\FreePlan::platformDisabled($a)
                ? __('The ad account :name was disabled by the platform, so it can\'t be connected.', ['name' => '<bdi>'.e($a->name).'</bdi>'])
                : __('The ad account :name is linked to another Free workspace. Move to a paid plan or ask its owner for an invite.', ['name' => '<bdi>'.e($a->name).'</bdi>']) !!}
        </p>
    @endforeach

    @if (! $client)
        <p style="{{ $muted }}">{{ __('Add your client and connect one ad account to see its campaigns here.') }}</p>
    @else
        @if ($until)<p style="{{ $muted }}">{!! __('Data until :date', ['date' => $ltr($until)]) !!}</p>@endif

        @if ($summary)
            <section style="{{ $card }}">
                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><h3 style="font-size:15px;font-weight:700;margin:0">{{ __('This week') }}</h3><span style="{{ $muted }}">{{ __('Automatic summary, no AI') }}</span></div>
                <div style="font-size:14px">{!! __('Spend: :spend · Results: :results', ['spend' => $money($summary['spend']), 'results' => $ltr(number_format((float) $summary['results']))]) !!}</div>
                @if ($summary['best'])<div style="font-size:13.5px">{!! __('Lowest cost per result: :name (:cpr)', ['name' => '<bdi>'.e($summary['best']['name']).'</bdi>', 'cpr' => $cpr($summary['best']['cpr'])]) !!}</div>@endif
                @if ($summary['worst'])<div style="font-size:13.5px">{!! __('Highest cost per result: :name (:cpr)', ['name' => '<bdi>'.e($summary['worst']['name']).'</bdi>', 'cpr' => $cpr($summary['worst']['cpr'])]) !!}</div>@endif
                @if ($summary['season'])<div style="font-size:13.5px">{!! __('Next season: :name on :date', ['name' => e(app()->getLocale() === 'ar' ? $summary['season']['ar'] : $summary['season']['label']), 'date' => $ltr($summary['season']['start'])]) !!}</div>@endif
            </section>
        @endif

        <section style="{{ $card }}">
            <h3 style="font-size:15px;font-weight:700;margin:0">{{ __('Campaigns · last 30 days') }}</h3>
            <div style="{{ $row }};{{ $muted }}"><span>{{ __('Campaign') }}</span><span>{{ __('Spend') }}</span><span>{{ __('Results') }}</span><span>{{ __('Cost per result') }}</span><span></span></div>
            @forelse ($campaigns as $c)
                <details style="border-top:1px solid rgba(148,163,184,.2);padding-top:6px">
                    <summary style="{{ $row }};cursor:pointer;min-height:44px">
                        <span><bdi>{{ $c['name'] }}</bdi>@if ($c['status'])<span style="{{ $muted }}"> · {!! $ltr($c['status']) !!}</span>@endif</span>
                        <span>{!! $money($c['spend']) !!}</span><span>{!! $ltr(number_format((float) $c['main_results'])) !!}</span><span>{!! $cpr($c['cpr']) !!}</span>
                        <span title="{{ $trend[$c['trend']][1] }}" aria-label="{{ $trend[$c['trend']][1] }}">{{ $trend[$c['trend']][0] }}</span>
                    </summary>
                    @foreach ($c['sets'] as $s)
                        <details style="padding-inline-start:16px">
                            <summary style="{{ $row }};cursor:pointer;min-height:44px">
                                <span><bdi>{{ $s['name'] }}</bdi></span><span>{!! $money($s['spend']) !!}</span><span>{!! $ltr(number_format((float) $s['main_results'])) !!}</span><span>{!! $cpr($s['cpr']) !!}</span><span></span>
                            </summary>
                            @foreach ($s['ads'] as $a)
                                <div style="{{ $row }};padding-inline-start:16px;min-height:36px"><span><bdi>{{ $a['name'] }}</bdi></span><span>{!! $money($a['spend']) !!}</span><span>{!! $ltr(number_format((float) $a['main_results'])) !!}</span><span>{!! $cpr($a['cpr']) !!}</span><span></span></div>
                            @endforeach
                        </details>
                    @endforeach
                </details>
            @empty
                <p style="{{ $muted }}">{{ __('No campaign data in the last 30 days yet.') }}</p>
            @endforelse
        </section>

        <section style="{{ $card }}">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ([__('Pause / resume'), __('Budgets'), __('Approve AI changes'), __('Negative keywords')] as $ctl)
                    <button type="button" disabled aria-disabled="true" style="min-height:44px;padding:0 14px;border-radius:10px;border:1px solid rgba(148,163,184,.35);opacity:.6;cursor:not-allowed">{{ $ctl }} · {{ __('Coming soon') }}</button>
                @endforeach
            </div>
            <p style="{{ $muted }};margin:0">{{ __('Available once the ad platforms approve our apps.') }}</p>
        </section>

        <p style="{{ $muted }};margin:0">{{ __('Share with your client on :plan.', ['plan' => \App\Support\PlanGate::tierLabel('solo')]) }}
            {{ __('Filters, exports, more than 30 days, reports and dashboards come with the paid plans.') }}</p>
    @endif
</x-filament-panels::page>
