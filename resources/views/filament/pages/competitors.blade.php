@php
    $muted = 'opacity:.65;font-size:12px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px';
    $field = 'padding:5px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px';
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <label style="{{ $muted }}" for="cw-client">Client</label>
        <select id="cw-client" wire:model.live="clientId" style="{{ $field }}">
            @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>
    </div>

    <x-filament::section>
        <div style="font-size:13px">
            Keep a watch list per client and open each competitor's live ads in the public ad libraries, filtered by country.
            <span style="{{ $muted }}">Meta's Ad Library API only returns ads about social issues, elections or politics outside the EU, so commercial ads in MENA can't be pulled automatically; the links open Meta's and Google's public libraries instead.</span>
        </div>
    </x-filament::section>

    @if ($canManage && $clientId)
        <x-filament::section>
            <x-slot name="heading">Add a competitor</x-slot>
            <form wire:submit="add" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;align-items:start">
                <div><label style="{{ $muted }}" for="cw-name">Name</label><input id="cw-name" wire:model="form.name" style="{{ $field }};width:100%" dir="auto">
                    @error('form.name')<div style="color:#dc2626;font-size:12px">{{ $message }}</div>@enderror</div>
                <div><label style="{{ $muted }}" for="cw-page">Facebook page ID (optional)</label><input id="cw-page" wire:model="form.facebook_page_id" inputmode="numeric" style="{{ $field }};width:100%" dir="ltr">
                    <div style="{{ $muted }}">From the Ad Library link: view_all_page_id=…</div>
                    @error('form.facebook_page_id')<div style="color:#dc2626;font-size:12px">{{ $message }}</div>@enderror</div>
                <div><label style="{{ $muted }}" for="cw-web">Website (optional)</label><input id="cw-web" type="url" wire:model="form.website" placeholder="https://" style="{{ $field }};width:100%" dir="ltr">
                    @error('form.website')<div style="color:#dc2626;font-size:12px">{{ $message }}</div>@enderror</div>
                <div><label style="{{ $muted }}" for="cw-cc">Countries</label>
                    <select id="cw-cc" multiple wire:model="form.countries" style="{{ $field }};width:100%;height:84px">
                        @foreach ($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach
                    </select><div style="{{ $muted }}">Empty = the client's main market.</div></div>
                <div style="align-self:end"><x-filament::button type="submit" size="sm">Add competitor</x-filament::button></div>
            </form>
        </x-filament::section>
    @endif

    @forelse ($list as $c)
        <x-filament::section>
            <x-slot name="heading">{{ $c->name }}</x-slot>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">
                @foreach ($c->links() as [$label, $url])
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" style="padding:5px 12px;border-radius:99px;font-size:13px;border:1px solid rgba(148,163,184,.35);color:#14B8A6">{{ $label }} ↗</a>
                @endforeach
            </div>
            <div style="{{ $muted }}">Last reviewed: {{ $c->reviewed_at?->diffForHumans() ?? 'never' }}@if ($c->website) · {{ $c->website }}@endif</div>
            @if ($canManage)
                <textarea wire:model="notes.{{ $c->id }}" rows="3" placeholder="What did you see? Offers, creative angles, new formats, prices…" style="{{ $field }};width:100%;margin-top:8px" dir="auto" aria-label="Notes for {{ $c->name }}"></textarea>
                <div style="display:flex;gap:6px;margin-top:6px">
                    <x-filament::button size="xs" wire:click="saveNotes({{ $c->id }})">Save notes</x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="markReviewed({{ $c->id }})">Mark reviewed</x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="remove({{ $c->id }})" wire:confirm="Remove {{ $c->name }} from the watch list?">Remove</x-filament::button>
                </div>
            @elseif ($c->notes)
                <div style="margin-top:8px;font-size:13px;white-space:pre-line" dir="auto">{{ $c->notes }}</div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section><div style="{{ $muted }}">No competitors on this client's watch list yet.</div></x-filament::section>
    @endforelse
</x-filament-panels::page>
