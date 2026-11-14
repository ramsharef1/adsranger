<x-filament-panels::page>
    @php
        $inputStyle = 'min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
        $btnStyle = 'min-height:44px;padding:0 16px;border-radius:8px;background:#0d9488;color:#fff;font-weight:600;font-size:13px;border:0;cursor:pointer';
    @endphp
    <div style="display:grid;gap:16px">
        @if (! $client)
            <x-filament::section>
                <p style="font-size:13px;opacity:.75">{{ __('Add a client first.') }}</p>
            </x-filament::section>
        @else
            @if ($canManage)
                <x-filament::section>
                    <x-slot name="heading">{{ __('Queue qualified or closed-won offline deal (SHA-256 hashed)') }}</x-slot>
                    <form wire:submit="recordOfflineDeal" style="display:grid;gap:10px">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px">
                            <select wire:model.live="clientId" style="{{ $inputStyle }}">
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="leadIdentifier" placeholder="{{ __('Deal / CRM reference') }}" style="{{ $inputStyle }}">
                            <select wire:model="dealStage" style="{{ $inputStyle }}">
                                <option value="qualified">{{ __('Qualified lead') }}</option>
                                <option value="won">{{ __('Closed won') }}</option>
                            </select>
                            <input type="number" step="0.01" wire:model="dealValue" dir="ltr" placeholder="0.00" style="{{ $inputStyle }}">
                            <select wire:model="targetNetwork" style="{{ $inputStyle }}" dir="ltr">
                                <option value="meta_capi">meta_capi</option>
                                <option value="google_offline">google_offline</option>
                            </select>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px">
                            <input type="text" wire:model="ctwaClid" dir="ltr" placeholder="ctwa_clid" style="{{ $inputStyle }}">
                            <input type="text" wire:model="gclid" dir="ltr" placeholder="gclid" style="{{ $inputStyle }}">
                            <input type="text" wire:model="phone" dir="ltr" placeholder="+9665..." style="{{ $inputStyle }}">
                            <input type="email" wire:model="email" dir="ltr" placeholder="buyer@example.com" style="{{ $inputStyle }}">
                        </div>
                        <div>
                            <button type="submit" style="{{ $btnStyle }}">{{ __('Save offline conversion') }}</button>
                        </div>
                    </form>
                </x-filament::section>
            @endif

            <x-filament::section>
                <x-slot name="heading">{{ __('Recent offline conversion dispatches') }}</x-slot>
                @if ($dispatches->isEmpty())
                    <p style="font-size:13px;opacity:.75">{{ __('No offline conversions logged for this client yet.') }}</p>
                @else
                    <div style="display:grid;gap:8px;font-size:13px">
                        @foreach ($dispatches as $d)
                            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;padding:10px;border:1px solid rgba(148,163,184,.25);border-radius:8px">
                                <span>
                                    <strong><bdi>{{ $d->lead_identifier }}</bdi></strong> ·
                                    <bdi dir="ltr">{{ $d->target_network }} · {{ $d->deal_stage }}</bdi>
                                </span>
                                <span>
                                    @if (! $client->hide_spend)
                                        <strong dir="ltr">{{ \App\Support\ResultResolver::money($d->deal_value, $d->currency) }}</strong> ·
                                    @endif
                                    <bdi dir="ltr">{{ $d->status }}</bdi>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
