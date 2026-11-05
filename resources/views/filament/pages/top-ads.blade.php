@php
    use App\Support\ResultResolver as R;
    $muted = 'opacity:.65;font-size:12px';
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:8px';
@endphp
<x-filament-panels::page>
    <div style="{{ $muted }}">{{ __('Your best ads across clients, with their images. Money is shown in each client\'s currency.') }}</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
        <div>
            <label for="ta-client" style="{{ $label }}">{{ __('Client') }}</label>
            <select id="ta-client" wire:model.live="clientId" style="{{ $field }}">
                <option value="">{{ __('All clients') }}</option>
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ta-network" style="{{ $label }}">{{ __('Ad network') }}</label>
            <select id="ta-network" wire:model.live="network" style="{{ $field }}">
                <option value="">{{ __('All ad networks') }}</option>
                @foreach ($networks as $k => $name)
                    <option value="{{ $k }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ta-period" style="{{ $label }}">{{ __('Period') }}</label>
            <select id="ta-period" wire:model.live="period" style="{{ $field }}">
                @foreach (\App\Support\TopAds::PERIODS as $k => $p)
                    <option value="{{ $k }}">{{ __($p) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ta-sort" style="{{ $label }}">{{ __('Sort by') }}</label>
            <select id="ta-sort" wire:model.live="sort" style="{{ $field }}">
                <option value="results">{{ __('Most results') }}</option>
                <option value="cpr">{{ __('Lowest cost per result') }}</option>
                <option value="ctr">{{ __('Highest link CTR') }}</option>
                <option value="spend">{{ __('Most spend') }}</option>
            </select>
        </div>
    </div>
    @php $rangeLine = $from.' → '.$to.(in_array($sort, ['cpr', 'spend'], true) ? ' · '.__('Ranked in :currency at today\'s rate.', ['currency' => $base]) : ''); @endphp
    <div style="{{ $muted }}" dir="auto">{{ $rangeLine }}</div>

    @if (! count($ads))
        <x-filament::section>
            <div style="{{ $muted }}">{{ $clients->isEmpty() ? __('No clients yet. Add a client and link its ad accounts first.') : __('No ads with spend or results in this period. Try a longer period or another client.') }}</div>
        </x-filament::section>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,190px),1fr));gap:12px">
            @foreach ($ads as $i => $ad)
                <div style="{{ $card }}">
                    <div style="position:relative">
                        @if ($ad['thumbnail'])
                            <img src="{{ $ad['thumbnail'] }}" alt="{{ $ad['name'] }}" loading="lazy" referrerpolicy="no-referrer" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;display:block">
                        @else
                            <div role="img" aria-label="{{ __('No image from :network', ['network' => $ad['network_name'] ?? __('this ad network')]) }}" style="width:100%;aspect-ratio:1;border-radius:8px;background:rgba(148,163,184,.15);display:flex;align-items:center;justify-content:center;font-size:12px;opacity:.7;text-align:center;padding:8px">{{ __('No image from :network', ['network' => $ad['network_name'] ?? __('this ad network')]) }}</div>
                        @endif
                        <span style="position:absolute;top:6px;inset-inline-start:6px;background:rgba(15,23,42,.75);color:#fff;border-radius:99px;padding:1px 8px;font-size:12px">{{ $i + 1 }}</span>
                    </div>
                    <div style="font-size:13px;font-weight:600;margin-top:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $ad['name'] }}">{{ $ad['name'] }}</div>
                    <div style="{{ $muted }};overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $ad['client'].($ad['network_name'] ? ' · '.$ad['network_name'] : '') }}</div>
                    <dl style="display:grid;grid-template-columns:1fr auto;gap:2px 8px;font-size:12px;margin-top:6px">
                        <dt style="opacity:.7">{{ R::label($ad['main_type'], app()->getLocale()) ?: __('Results') }}</dt><dd style="text-align:end;font-weight:600">{{ number_format($ad['main_results']) }}</dd>
                        <dt style="opacity:.7">{{ __('Cost per result') }}</dt><dd style="text-align:end">{{ R::money($ad['cpr'], $ad['currency']) }}</dd>
                        <dt style="opacity:.7">{{ __('Spend') }}</dt><dd style="text-align:end">{{ R::money($ad['spend'], $ad['currency']) }}</dd>
                        <dt style="opacity:.7">{{ __('Link CTR') }}</dt><dd style="text-align:end">{{ $ad['ctr'] !== null ? number_format($ad['ctr'], 2).'%' : '–' }}</dd>
                    </dl>
                    <a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('view', ['record' => $ad['client_id']]) }}" style="display:inline-flex;align-items:center;min-height:44px;color:var(--primary-600);font-size:12px">{{ __('Open client') }}</a>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
