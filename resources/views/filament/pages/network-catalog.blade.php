@php
    use App\Support\NetworkCatalog as NC;
    use App\Support\PlanGate;
    $muted = 'opacity:.65;font-size:12px';
    $field = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
    $badge = ['connected' => ['Connected', 'success'], 'available' => ['Ready to connect', 'success'], 'restricted' => ['Ask your workspace owner', 'gray'],
        'upgrade' => ['Upgrade to use', 'warning'], 'soon' => ['Coming soon', 'gray'], 'planned' => ['Planned', 'gray']];
@endphp
<x-filament-panels::page>
    <style>.ar-tap{min-height:44px}</style>
    <div style="{{ $muted }}">{{ __('Every ad network AdsRanger has or plans. Ask for the ones you need: the most-requested come first.') }}</div>

    @foreach ($groups as $category => $rows)
        <x-filament::section>
            <x-slot name="heading">{{ __($category) }}</x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:10px">
                @foreach ($rows as $r)
                    @php [$label, $color] = $badge[$r['status']]; @endphp
                    <div style="border:1px solid rgba(148,163,184,.25);border-radius:10px;padding:10px 12px;display:flex;flex-direction:column;gap:8px;min-width:0">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
                            <strong style="font-size:14px;min-width:0" dir="ltr">{{ $r['name'] }}</strong>
                            @php $text = $r['status'] === 'upgrade' ? __('On :tier', ['tier' => PlanGate::tierLabel($r['tier'])]) : __($label); @endphp
                            <x-filament::badge :color="$color">{{ $text }}</x-filament::badge>
                        </div>
                        @if ($r['status'] === 'connected')
                            <div style="{{ $muted }}">{{ trans_choice('{1} :count connection|[2,*] :count connections', $r['connected'], ['count' => $r['connected']]) }}</div>
                        @elseif (NC::requestable($r['status']))
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
                                @php $asked = $r['requests'] ? trans_choice('{1} :count workspace asked|[2,*] :count workspaces asked', $r['requests'], ['count' => $r['requests']]) : __('No requests yet'); @endphp
                                <span style="{{ $muted }}">{{ $asked }}</span>
                                <x-filament::button class="ar-tap" size="sm" :color="$r['requested'] ? 'gray' : 'primary'" :outlined="! $r['requested']"
                                    :icon="$r['requested'] ? 'heroicon-o-check' : 'heroicon-o-hand-raised'" wire:click="toggleRequest('{{ $r['key'] }}')">
                                    {{ $r['requested'] ? __('Requested') : __('I need this') }}
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    <x-filament::section>
        <x-slot name="heading">{{ __('Suggest a network we don\'t list') }}</x-slot>
        <form wire:submit="suggest" style="display:grid;gap:10px;max-width:560px">
            <div>
                <label for="nc-name" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">{{ __('Network name') }}</label>
                <input id="nc-name" type="text" wire:model="suggestName" maxlength="120" dir="auto" style="{{ $field }}">
                @error('suggestName')<div style="color:var(--danger-600);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="nc-note" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">{{ __('Why you need it') }} <span style="{{ $muted }}">({{ __('optional') }})</span></label>
                <textarea id="nc-note" wire:model="suggestNote" rows="2" maxlength="500" dir="auto" style="{{ $field }};resize:vertical"></textarea>
            </div>
            <div><x-filament::button class="ar-tap" type="submit" icon="heroicon-o-paper-airplane">{{ __('Send suggestion') }}</x-filament::button></div>
        </form>
        @if ($mySuggestions->isNotEmpty())
            <div style="{{ $muted }};margin-top:10px">{{ __('Your suggestions: :list', ['list' => $mySuggestions->pluck('network_name')->implode(' · ')]) }}</div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
