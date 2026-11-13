@php
    $levels = [
        'verified' => __('Verified'),
        'stale' => __('Was verified, no real sync for :days days', ['days' => $days]),
        'connected' => __('Real account connected, no successful sync yet'),
        'keys_tested' => __('Keys work, no real account yet'),
        'keys_set' => __('Keys saved, not tested'),
        'not_set' => __('Not set up'),
    ];
@endphp
<x-filament-panels::page>
    {{-- A-3 --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('Server health') }}</x-slot>
        @if (empty($health['at']))
            <p style="margin:0;font-size:14px">{{ __('No health check has run yet. It runs every 10 minutes.') }}</p>
        @else
            <p style="margin:0 0 6px;font-size:14px">{{ __('Last check') }}: <bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($health['at'])->timezone('Asia/Amman')->format('Y-m-d H:i') }}</bdi></p>
            @if (empty($health['problems']))
                <p style="margin:0;font-size:14px;font-weight:600">{{ __('No problems found.') }}</p>
            @else
                <ul role="list" style="margin:0;padding-inline-start:18px;font-size:14px">
                    @foreach ($health['problems'] as $p)<li>{{ $p['text'] }}@if (! empty($p['fix']))<br><span style="font-size:12px;opacity:.75"><bdi dir="ltr">{{ $p['fix'] }}</bdi></span>@endif</li>@endforeach
                </ul>
            @endif
        @endif
    </x-filament::section>

    {{-- A-5 --}}
    <x-filament::section style="margin-top:16px">
        <x-slot name="heading">{{ __('Restore test') }}</x-slot>
        <x-slot name="description">{{ __('Once a month the newest nightly backup is restored into a temporary database, checked and deleted. The backups are on this server only until the off-server copy (R2) is set up.') }}</x-slot>
        @if (! $restore)
            <p style="margin:0;font-size:14px">{{ __('No restore test has run yet.') }}</p>
        @else
            <p style="margin:0 0 8px;font-size:14px;font-weight:600">{{ $restore['ok'] ? __('Passed') : __('Failed') }} · <bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($restore['at'])->timezone('Asia/Amman')->format('Y-m-d H:i') }}</bdi> · <bdi dir="ltr">{{ $restore['backup'] }}</bdi></p>
            @unless ($restore['ok'])<p role="alert" style="margin:0 0 8px;font-size:14px">{{ $restore['message'] }}</p>@endunless
            @if (! empty($restore['tables']))
                <table style="border-collapse:collapse;font-size:14px">
                    <caption class="sr-only">{{ __('Restore test') }}</caption>
                    <thead><tr><th scope="col" style="padding:4px 10px;text-align:start">{{ __('Table') }}</th><th scope="col" style="padding:4px 10px;text-align:start">{{ __('Rows in the backup') }}</th><th scope="col" style="padding:4px 10px;text-align:start">{{ __('Rows live now') }}</th></tr></thead>
                    <tbody>@foreach ($restore['tables'] as $t)<tr><td style="padding:4px 10px" dir="ltr">{{ $t['table'] }}</td><td style="padding:4px 10px" dir="ltr">{{ $t['restored'] }}</td><td style="padding:4px 10px" dir="ltr">{{ $t['live'] }}</td></tr>@endforeach</tbody>
                </table>
            @endif
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __(':n of :total networks verified', ['n' => $verified, 'total' => count($rows)]) }}</x-slot>
        <x-slot name="description">{{ __('Verified means a real (not demo) account synced and brought rows back in the last :days days. Everything on this page comes from the system; nothing is typed by hand.', ['days' => $days]) }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <caption class="sr-only">{{ __('Live verification board') }}</caption>
                <thead>
                    <tr>
                        <th scope="col" style="padding:6px;text-align:start">{{ __('Network') }}</th>
                        <th scope="col" style="padding:6px;text-align:start">{{ __('State') }}</th>
                        <th scope="col" style="padding:6px;text-align:start">{{ __('Key test') }}</th>
                        <th scope="col" style="padding:6px;text-align:start">{{ __('Real connections') }}</th>
                        <th scope="col" style="padding:6px;text-align:start">{{ __('Last successful sync') }}</th>
                        <th scope="col" style="padding:6px;text-align:start">{{ __('Last failed sync') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr style="border-top:1px solid rgba(148,163,184,.18)">
                            <td style="padding:6px"><bdi>{{ $r['name'] }}</bdi>@unless ($r['enabled']) <span style="opacity:.6;font-size:12px">· {{ __('off') }}</span>@endunless</td>
                            <td style="padding:6px;font-weight:{{ $r['level'] === 'verified' ? '700' : '400' }}">{{ $levels[$r['level']] }}</td>
                            <td style="padding:6px">{{ match ($r['test']) { 'ok' => __('Works'), 'failed' => __('Failed'), 'unclear' => __('Saved, cannot be fully tested'), default => '—' } }}@if ($r['tested_at']) <span dir="ltr" style="opacity:.6;font-size:12px">{{ $r['tested_at'] }}</span>@endif</td>
                            <td style="padding:6px" dir="ltr">{{ $r['connections'] }}</td>
                            <td style="padding:6px" dir="ltr">{{ $r['last_ok_sync'] ?? '—' }}</td>
                            <td style="padding:6px" dir="ltr">{{ $r['last_error'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
