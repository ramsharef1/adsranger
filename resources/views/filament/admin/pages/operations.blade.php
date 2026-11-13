@php
    $cell = 'padding:6px;text-align:start;vertical-align:top';
    $tz = 'Asia/Amman';
@endphp
<x-filament-panels::page>
    <x-filament::section :heading="__('Automatic launch checks')">
        <x-slot name="description">{{ __('Checked by the code each time this page opens. The hand-ticked rows stay on Costs & launch.') }}</x-slot>
        <div style="display:grid;gap:6px">
            @foreach ($checks as $c)
                <div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">
                    <span style="font-weight:600;{{ $c['ok'] ? 'color:#0d9488' : 'color:#dc2626' }}">{{ $c['ok'] ? __('OK') : __('Needs a look') }}</span>
                    <span>{{ $c['label'] }}</span>
                    @if ($c['detail'] !== '')<span style="opacity:.75;font-size:13px"><bdi>{{ $c['detail'] }}</bdi></span>@endif
                </div>
            @endforeach
        </div>
        <p style="margin-top:10px;font-size:13px;opacity:.75">{{ __('Queue lanes') }}: {{ $drivers['lanes'] ? __('on') : __('off') }} · {{ __('Cache') }}: <bdi dir="ltr">{{ $drivers['cache'] }}</bdi> · {{ __('Sessions') }}: <bdi dir="ltr">{{ $drivers['session'] }}</bdi></p>
    </x-filament::section>

    <x-filament::section :heading="__('Scheduled tasks')">
        <x-slot name="description">{{ __('Last finished run of each task. "Late" means a run that should have finished by now is missing.') }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $cell }}">{{ __('Task') }}</th><th style="{{ $cell }}">{{ __('Schedule (UTC)') }}</th>
                    <th style="{{ $cell }}">{{ __('Last run') }}</th><th style="{{ $cell }}">{{ __('Time taken') }}</th><th style="{{ $cell }}">{{ __('Status') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($jobs as $j)
                        <tr style="border-top:1px solid rgba(127,127,127,.2)">
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $j['name'] }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $j['cron'] }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $j['last']?->timezone($tz)->format('Y-m-d H:i') ?? '—' }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $j['seconds'] !== null ? $j['seconds'].' s' : '—' }}</bdi></td>
                            <td style="{{ $cell }};{{ $j['missed'] || $j['status'] === 'failed' ? 'color:#dc2626;font-weight:600' : '' }}">
                                @if ($j['status'] === 'failed') {{ __('Failed') }}<br><span style="font-size:12px;font-weight:400"><bdi dir="ltr">{{ \Illuminate\Support\Str::limit($j['error'], 120) }}</bdi></span>
                                @elseif ($j['missed']) {{ __('Late') }}
                                @elseif ($j['last']) {{ __('OK') }}
                                @else {{ __('No run recorded yet') }} @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="margin-top:10px;font-size:13px;opacity:.75">{{ __('Scheduler heartbeat') }}: {{ $heartbeat['last'] ? __(':n minutes ago', ['n' => $heartbeat['age_minutes']]) : __('never') }} · <bdi dir="ltr">/up/heartbeat</bdi></p>
    </x-filament::section>

    <x-filament::section :heading="__('Find an error by its reference')">
        <x-slot name="description">{{ __('Paste the reference shown on an error page. Searches the server log and the older log files.') }}</x-slot>
        <form wire:submit="lookup" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="text" wire:model="ref" dir="ltr" aria-label="{{ __('Reference') }}" placeholder="AB12CD34" style="min-height:44px;border-radius:8px;padding:6px 10px;border:1px solid rgba(127,127,127,.4);background:transparent">
            <x-filament::button type="submit" style="min-height:44px">{{ __('Find') }}</x-filament::button>
        </form>
        @if (is_array($found))
            @forelse ($found as $f)
                <pre dir="ltr" style="margin-top:12px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;padding:8px;border-radius:8px;background:rgba(127,127,127,.1)">{{ $f['file'] }}
{{ implode("\n", $f['lines']) }}</pre>
            @empty
                <p style="margin-top:12px;opacity:.75">{{ __('No log entry has this reference.') }}</p>
            @endforelse
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('What we keep and for how long')">
        <div style="display:grid;gap:4px">
            @foreach ($retention as $r)
                <div><span>{{ $r['what'] }}</span> · <span style="opacity:.75">{{ $r['keep'] }}</span></div>
            @endforeach
        </div>
        @if (is_array($lastPrune))
            <p style="margin-top:10px;font-size:13px;opacity:.75">{{ __('Last clean-up') }}: <bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($lastPrune['at'])->timezone($tz)->format('Y-m-d H:i') }}</bdi> · <bdi dir="ltr">{{ collect($lastPrune['deleted'] ?? [])->map(fn ($n, $t) => "$t $n")->implode(' · ') }}</bdi></p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
