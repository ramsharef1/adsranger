<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Agency waitlist') }} ({{ $total }})</x-slot>
        <x-slot name="description">{{ __('Agencies that joined via /waitlist or /en/waitlist.') }}</x-slot>

        @if ($entries->isEmpty())
            <p style="opacity:.7;font-size:14px">{{ __('No waitlist entries yet.') }}</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <thead>
                        <tr style="text-align:start;border-bottom:1px solid rgba(148,163,184,.25)">
                            <th style="padding:8px">{{ __('Name') }}</th>
                            <th style="padding:8px">{{ __('Agency') }}</th>
                            <th style="padding:8px">{{ __('Email') }}</th>
                            <th style="padding:8px">{{ __('Monthly spend') }}</th>
                            <th style="padding:8px">{{ __('Language') }}</th>
                            <th style="padding:8px">{{ __('Country') }}</th>
                            <th style="padding:8px">{{ __('Networks') }}</th>
                            <th style="padding:8px">{{ __('Consent') }}</th>
                            <th style="padding:8px">{{ __('Created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $e)
                            <tr style="border-top:1px solid rgba(148,163,184,.18)">
                                <td style="padding:8px;font-weight:600">{{ $e->name ?: '—' }}</td>
                                <td style="padding:8px">{{ $e->agency_name ?: '—' }}</td>
                                <td style="padding:8px"><bdi dir="ltr">{{ $e->email }}</bdi></td>
                                <td style="padding:8px"><bdi dir="ltr">{{ $e->monthly_spend ?: '—' }}</bdi></td>
                                <td style="padding:8px">{{ strtoupper($e->lang) }}</td>
                                <td style="padding:8px"><bdi dir="ltr">{{ $e->country ?: '—' }}</bdi></td>
                                <td style="padding:8px"><bdi dir="ltr">{{ $e->networks ?: '—' }}</bdi></td>
                                <td style="padding:8px">@if ($e->mayBeEmailed())<bdi dir="ltr">{{ $e->consent_at->format('Y-m-d') }} · {{ $e->consent_text_version }}</bdi>@else{{ __('None, do not email') }}@endif</td>
                                <td style="padding:8px"><bdi dir="ltr">{{ $e->created_at?->format('Y-m-d H:i') }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
