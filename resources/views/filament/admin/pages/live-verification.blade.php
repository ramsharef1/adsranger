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
