<x-filament-panels::page>
    <x-filament::section :heading="__('Limit hits and unreadable pages')">
        <x-slot name="description">{{ __('Counts only: we never keep the visitor\'s IP or the page address. A visitor who hits the limit :v times in a day, or a host we fail to read :h times in a day, is refused for 24 hours.', ['v' => \App\Support\Seo\ToolGuard::AUTO_VISITOR, 'h' => \App\Support\Seo\ToolGuard::AUTO_HOST]) }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr style="text-align:start">
                    <th style="padding:6px;text-align:start">{{ __('Tool') }}</th>
                    <th style="padding:6px;text-align:start">{{ __('Limit hits today / 7 days') }}</th>
                    <th style="padding:6px;text-align:start">{{ __('Unreadable today / 7 days') }}</th>
                    <th style="padding:6px;text-align:start">{{ __('Refused today / 7 days') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($rows as $tool => $r)
                        <tr style="border-top:1px solid rgba(127,127,127,.2)">
                            <td style="padding:6px"><bdi dir="ltr">/tools/{{ $tool }}</bdi></td>
                            <td style="padding:6px"><bdi dir="ltr">{{ $r['today']['limit'] }} / {{ $r['week']['limit'] }}</bdi></td>
                            <td style="padding:6px"><bdi dir="ltr">{{ $r['today']['fail'] }} / {{ $r['week']['fail'] }}</bdi></td>
                            <td style="padding:6px"><bdi dir="ltr">{{ $r['today']['refused'] }} / {{ $r['week']['refused'] }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('Blocks')">
        @if (empty($auto) && empty($manual))
            <p style="opacity:.7">{{ __('Nothing is blocked.') }}</p>
        @endif
        @foreach ($auto as $key => $b)
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(127,127,127,.2)">
                <span>{{ $b['kind'] === 'host' ? __('Host') : __('Visitor') }}: <bdi dir="ltr">{{ $b['label'] }}</bdi> · {{ __('automatic, until :time', ['time' => \Illuminate\Support\Carbon::parse($b['until'])->timezone('Asia/Amman')->format('Y-m-d H:i')]) }}</span>
                <x-filament::button size="sm" color="gray" wire:click="lift(@js($key))" style="min-height:44px">{{ __('Lift') }}</x-filament::button>
            </div>
        @endforeach
        @foreach ($manual as $h)
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(127,127,127,.2)">
                <span>{{ __('Host') }}: <bdi dir="ltr">{{ $h }}</bdi> · {{ __('blocked by hand') }}</span>
                <x-filament::button size="sm" color="gray" wire:click="lift(@js('manual:'.$h))" style="min-height:44px">{{ __('Lift') }}</x-filament::button>
            </div>
        @endforeach
        <form wire:submit="blockHost" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin-top:12px">
            <label style="display:flex;flex-direction:column;gap:4px">{{ __('Block a host') }}
                <input type="text" wire:model="host" dir="ltr" placeholder="example.com" style="min-height:44px;border-radius:8px;padding:0 10px"></label>
            <x-filament::button type="submit" color="danger" style="min-height:44px">{{ __('Block') }}</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
