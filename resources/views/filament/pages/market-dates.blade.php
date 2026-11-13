<x-filament-panels::page>
    <x-filament::section>
        <p style="margin:0;font-size:14px;line-height:1.7">{{ __('Enter the payday and school dates you rely on for each country you work in. They show on client charts and in the client calendar, always as "usually". Add a source if you have one, so your team knows where a date came from.') }}</p>
    </x-filament::section>

    @if ($rows->isEmpty())
        <x-filament::section><p style="margin:0;opacity:.75">{{ __('No dates yet. Add the payday for your main country first.') }}</p></x-filament::section>
    @else
        <x-filament::section data-market-dates>
            <div style="overflow-x:auto"><table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr>
                    <th style="text-align:start;padding:8px">{{ __('Country') }}</th><th style="text-align:start;padding:8px">{{ __('Type') }}</th>
                    <th style="text-align:start;padding:8px">{{ __('When') }}</th><th style="text-align:start;padding:8px">{{ __('Label') }}</th>
                    <th style="text-align:start;padding:8px">{{ __('Source') }}</th>@if ($canEdit)<th></th>@endif
                </tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr style="border-top:1px solid rgba(148,163,184,.25)">
                            <td style="padding:8px">{{ __($countries[$r->country] ?? $r->country) }}</td>
                            <td style="padding:8px">{{ $labels[$r->kind] ?? $r->kind }}</td>
                            <td style="padding:8px">@if ($r->kind === 'payday'){{ $r->day_of_month === 0 ? __('Last day of the month') : __('Day :n of each month', ['n' => $r->day_of_month]) }}@else<bdi dir="ltr">{{ $r->starts_on?->toDateString() }} → {{ $r->ends_on?->toDateString() }}</bdi>@endif</td>
                            <td style="padding:8px" dir="auto">{{ $r->label ?: '–' }}</td>
                            <td style="padding:8px;max-width:260px;overflow-wrap:anywhere" dir="auto">{{ $r->source ?: '–' }}</td>
                            @if ($canEdit)<td style="padding:8px;text-align:end"><button type="button" wire:click="remove({{ $r->id }})" wire:confirm="{{ __('Remove this date?') }}" style="min-height:44px;padding:0 12px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent;font-size:13px">{{ __('Remove') }}</button></td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
