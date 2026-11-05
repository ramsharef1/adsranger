@php $muted = 'opacity:.7;font-size:12px'; @endphp
<div style="display:grid;gap:10px">
    @forelse ($links as $l)
        <div style="border:1px solid rgba(148,163,184,.35);border-radius:10px;padding:10px 12px;display:grid;gap:6px">
            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center">
                <strong>{{ $l->label ?: __('Client link') }}</strong>
                @if ($l->isActive())
                    <x-filament::badge color="success" style="display:inline-flex">{{ __('Active') }}</x-filament::badge>
                @else
                    <x-filament::badge color="gray" style="display:inline-flex">{{ $l->revoked_at ? __('Revoked') : __('Expired') }}</x-filament::badge>
                @endif
            </div>
            @if ($l->isActive())
                <div x-data="{ copied: false }" style="display:flex;gap:6px;align-items:center">
                    <input type="text" readonly value="{{ $l->url() }}" dir="ltr" aria-label="{{ __('Link') }}" onclick="this.select()"
                        style="flex:1;min-width:0;min-height:40px;padding:0 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:12px">
                    <x-filament::button size="sm" color="gray" icon="heroicon-o-clipboard"
                        x-on:click="navigator.clipboard.writeText('{{ $l->url() }}'); copied = true; setTimeout(() => copied = false, 1500)">
                        <span x-show="! copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </x-filament::button>
                    @if ($canManage)
                        <x-filament::button size="sm" color="danger" outlined wire:click="revokeLink({{ $l->id }})" wire:confirm="{{ __('Revoke this link? Anyone using it will lose access.') }}">{{ __('Revoke') }}</x-filament::button>
                    @endif
                </div>
            @endif
            <div style="{{ $muted }}">
                {{ __('Created') }} {{ $l->created_at->toDateString() }}
                · {{ $l->expires_at ? __('Expires').' '.$l->expires_at->toDateString() : __('No expiry') }}
                · {{ trans_choice(':count view|:count views', $l->views, ['count' => $l->views]) }}@if ($l->last_viewed_at) · {{ __('last') }} {{ $l->last_viewed_at->diffForHumans() }}@endif
            </div>
        </div>
    @empty
        <div style="{{ $muted }}">{{ __('No links yet.') }}</div>
    @endforelse
</div>
