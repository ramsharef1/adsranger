@php $cell = 'padding:6px;text-align:start;vertical-align:middle'; @endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">{{ __('Suggestions come from the campaign names (Arabic or Latin). Check each row, change what is wrong, then save. Nothing changes before you save.') }}</x-slot>
        @if ($rows === [])
            <p>{{ __('Every imported campaign has a client and a goal.') }}</p>
        @else
            <form wire:submit="save">
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:14px;border-collapse:collapse">
                        <thead><tr>
                            <th style="{{ $cell }}">{{ __('Campaign') }}</th><th style="{{ $cell }}">{{ __('Spend') }}</th>
                            <th style="{{ $cell }}">{{ __('Client') }}</th><th style="{{ $cell }}">{{ __('Goal') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr style="border-top:1px solid rgba(127,127,127,.2)" wire:key="ir-{{ $r['key'] }}">
                                    <td style="{{ $cell }}"><bdi>{{ $r['campaign'] }}</bdi><br><span style="font-size:12px;opacity:.7"><bdi dir="ltr">{{ $r['network'] }}</bdi> · {{ __(':n days', ['n' => $r['rows']]) }}</span></td>
                                    <td style="{{ $cell }}"><bdi dir="ltr">{{ number_format($r['spend'], 2) }}</bdi></td>
                                    <td style="{{ $cell }}">
                                        <select wire:model="choices.{{ $r['key'] }}.client_id" class="fi-input" style="min-width:160px;min-height:40px">
                                            <option value="">{{ __('Not assigned') }}</option>
                                            @foreach ($clients as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                        </select>
                                        @if ($r['suggest_client'])<div style="font-size:12px;color:#0d9488">{{ __('Suggested from the name') }}</div>@endif
                                    </td>
                                    <td style="{{ $cell }}">
                                        <select wire:model="choices.{{ $r['key'] }}.result_type" class="fi-input" style="min-width:150px;min-height:40px">
                                            @foreach ($goals as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                        </select>
                                        @if ($r['suggest_goal'])<div style="font-size:12px;color:#0d9488">{{ __('Suggested from the name') }}</div>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px"><x-filament::button type="submit">{{ __('Save') }}</x-filament::button></div>
            </form>
        @endif
    </x-filament::section>
</x-filament-panels::page>
