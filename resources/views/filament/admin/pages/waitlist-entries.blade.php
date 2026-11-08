@php
    $consentedCount = $entries->filter(fn ($e) => $e->mayBeEmailed())->count();
    $arCount = $entries->where('lang', 'ar')->count();
    $enCount = $entries->where('lang', 'en')->count();
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Agency waitlist') }} ({{ $total }})</x-slot>
        <x-slot name="description">{{ __('Agencies that joined via /waitlist or /en/waitlist.') }}</x-slot>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;font-size:13px">
            <span style="padding:4px 10px;border-radius:99px;border:1px solid rgba(148,163,184,.25);font-weight:600">{{ __('Total') }}: {{ $total }}</span>
            <span style="padding:4px 10px;border-radius:99px;border:1px solid rgba(148,163,184,.25)">{{ __('Consent') }}: {{ $consentedCount }}</span>
            <span style="padding:4px 10px;border-radius:99px;border:1px solid rgba(148,163,184,.25)">AR: {{ $arCount }} · EN: {{ $enCount }}</span>
        </div>

        @if ($entries->isEmpty())
            <p style="opacity:.7;font-size:14px">{{ __('No waitlist entries yet.') }}</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <thead>
                        <tr style="text-align:start;border-bottom:1px solid rgba(148,163,184,.25)">
                            <th style="padding:10px 8px">{{ __('Name') }}</th>
                            <th style="padding:10px 8px">{{ __('Agency') }}</th>
                            <th style="padding:10px 8px">{{ __('onboarding.step1.title') }}</th>
                            <th style="padding:10px 8px">{{ __('Email') }}</th>
                            <th style="padding:10px 8px">{{ __('Monthly spend') }}</th>
                            <th style="padding:10px 8px">{{ __('Language') }}</th>
                            <th style="padding:10px 8px">{{ __('Country') }}</th>
                            <th style="padding:10px 8px">{{ __('Networks') }}</th>
                            <th style="padding:10px 8px">{{ __('Consent') }}</th>
                            <th style="padding:10px 8px">{{ __('Created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $e)
                            <tr style="border-top:1px solid rgba(148,163,184,.18)">
                                <td style="padding:10px 8px;font-weight:600">{{ $e->name ?: '—' }}</td>
                                <td style="padding:10px 8px">{{ $e->agency_name ?: '—' }}</td>
                                <td style="padding:10px 8px">{{ $e->audience ? __('onboarding.audience.'.$e->audience) : '—' }}</td>
                                <td style="padding:10px 8px"><bdi dir="ltr">{{ $e->email }}</bdi></td>
                                <td style="padding:10px 8px"><bdi dir="ltr">{{ $e->monthly_spend ?: '—' }}</bdi></td>
                                <td style="padding:10px 8px">{{ strtoupper($e->lang) }}</td>
                                <td style="padding:10px 8px"><bdi dir="ltr">{{ $e->country ?: '—' }}</bdi></td>
                                <td style="padding:10px 8px"><bdi dir="ltr">{{ $e->networks ?: '—' }}</bdi></td>
                                <td style="padding:10px 8px">@if ($e->mayBeEmailed())<span style="display:inline-block;padding:2px 8px;border-radius:6px;background:rgba(16,185,129,.12);font-size:12.5px"><bdi dir="ltr">{{ $e->consent_at->format('Y-m-d') }} · {{ $e->consent_text_version }}</bdi></span>@else<span style="opacity:.7;font-size:12.5px">{{ __('None, do not email') }}</span>@endif</td>
                                <td style="padding:10px 8px;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $e->created_at?->format('Y-m-d H:i') }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
