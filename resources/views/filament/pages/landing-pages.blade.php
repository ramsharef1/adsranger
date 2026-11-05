@php
    use App\Support\LandingPages as L;
    $muted = 'opacity:.65;font-size:12px';
    $field = 'padding:5px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
    $th = 'padding:6px;text-align:end';
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <label style="{{ $muted }}" for="lp-client">{{ __('Client') }}</label>
            <select id="lp-client" wire:model.live="clientId" style="{{ $field }}">
                <option value="">{{ __('All clients') }}</option>
                @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            @foreach (['broken', 'slow', 'redirects', 'insecure', 'ok'] as $s)
                @if (! empty($counts[$s]))<x-filament::badge :color="L::stateLabel($s)[1]" style="display:inline-flex">{{ $counts[$s] }} {{ __(strtolower(L::stateLabel($s)[0])) }}</x-filament::badge>@endif
            @endforeach
        </div>
        @if ($canManage)<x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="checkAll">{{ __('Check all now') }}</x-filament::button>@endif
    </div>

    @if ($canManage)
        <x-filament::section>
            <x-slot name="heading">{{ __('Add a page') }}</x-slot>
            <form wire:submit="add" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start">
                <div style="flex:1;min-width:240px">
                    <input type="url" wire:model="newUrl" placeholder="https://example.com/offer" style="{{ $field }};width:100%" aria-label="{{ __('Page address') }}" dir="ltr">
                    @error('newUrl')<div style="color:#dc2626;font-size:12px">{{ $message }}</div>@enderror
                </div>
                <select wire:model="newClient" style="{{ $field }}" aria-label="{{ __('Client') }}"><option value="">{{ __('No client') }}</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                <x-filament::button type="submit" size="sm">{{ __('Add and check') }}</x-filament::button>
            </form>
            <div style="{{ $muted }};margin-top:6px">{{ __('Pages used by Google Ads (last 30 days) and active Meta ads are added automatically with each sync. Every page is checked nightly.') }}</div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Page') }}</th><th style="padding:6px;text-align:start">{{ __('State') }}</th>
                    <th style="{{ $th }}">{{ __('HTTP') }}</th><th style="{{ $th }}">{{ __('Load time') }}</th><th style="{{ $th }}">{{ __('Redirects') }}</th><th style="{{ $th }}">{{ __('Mobile score') }}</th>
                    <th style="{{ $th }}">{{ __('Ad clicks (30 d)') }}</th><th style="padding:6px;text-align:start">{{ __('Checked') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($pages as $p)
                    @php [$sl, $sc] = L::stateLabel($p->state); @endphp
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px;max-width:380px;word-break:break-all" dir="ltr"><a href="{{ $p->url }}" target="_blank" rel="noopener noreferrer nofollow" style="color:#14B8A6">{{ $p->url }}</a>
                            <div style="{{ $muted }}" dir="auto">{{ $p->client->name ?? __('No client') }} · {{ $p->source }}@if ($p->final_url && $p->final_url !== $p->url) · {{ __('ends at :url', ['url' => \Illuminate\Support\Str::limit($p->final_url, 70)]) }}@endif</div></td>
                        <td style="padding:6px"><x-filament::badge :color="$sc">{{ __($sl) }}</x-filament::badge>@if ($p->error)<div style="{{ $muted }}">{{ $p->error }}</div>@endif</td>
                        <td style="{{ $th }}">{{ $p->status_code ?? '–' }}</td>
                        <td style="{{ $th }}">{{ $p->ms !== null ? number_format($p->ms / 1000, 1).' s' : '–' }}</td>
                        <td style="{{ $th }}">{{ $p->redirects ?? '–' }}</td>
                        <td style="{{ $th }}">{{ $p->psi_score ?? '–' }}</td>
                        <td style="{{ $th }}">{{ number_format($p->clicks_30d) }}</td>
                        <td style="padding:6px;{{ $muted }}">{{ $p->checked_at?->diffForHumans() ?? __('never') }}</td>
                        <td style="padding:6px;white-space:nowrap">
                            @if ($canManage)
                                <x-filament::button size="xs" color="gray" wire:click="checkOne({{ $p->id }})">{{ __('Check') }}</x-filament::button>
                                <x-filament::button size="xs" color="gray" wire:click="remove({{ $p->id }})" wire:confirm="{{ __('Stop watching this page?') }}">{{ __('Remove') }}</x-filament::button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="padding:12px;{{ $muted }}">{{ __('No landing pages yet. They appear after the next Google or Meta sync, or add one above.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="{{ $muted }};margin-top:6px">{{ __('Broken = error or HTTP 4xx/5xx · Slow = over :seconds s measured from our server (visitors may see different times) · Mobile score = Google PageSpeed:setup.', ['seconds' => number_format(config('insights.landing.slow_ms') / 1000, 1), 'setup' => config('insights.landing.pagespeed_key') ? '' : ' '.__('(not set up yet)')]) }}</div>
    </x-filament::section>
</x-filament-panels::page>
