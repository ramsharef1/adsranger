@php
    $cell = 'padding:6px;text-align:start;vertical-align:top';
    $kinds = ['brand' => __('Brand'), 'exact' => __('Keyword (exact)'), 'topic' => __('Topic / other'), 'image' => __('Image')];
    $states = ['live' => __('Live'), 'lost' => __('Lost'), 'unchecked' => __('Not checked yet'), 'archived' => __('Archived')];
@endphp
<x-filament-panels::page>
    @if ($lost > 0)
        <div role="alert" style="padding:12px;border-radius:8px;background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.4)">
            {{ __('Known links to AdsRanger that are gone: :n', ['n' => $lost]) }}
        </div>
    @endif

    <x-filament::section :heading="__('Known links to us')">
        <x-slot name="description">{{ __('Pages on other sites that link to adsranger.com (from the Link Tracker). Checked on the 1st of every month: is the link still there, its rel and its anchor.') }}</x-slot>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
            <x-filament::button wire:click="checkAll" style="min-height:44px">{{ __('Check now') }}</x-filament::button>
            @if ($mix)
                <span style="align-self:center">{{ __('Anchor mix') }}:
                    @foreach ($mix as $k => $m) <bdi>{{ $kinds[$k] ?? $k }} {{ $m['count'] }} ({{ $m['pct'] }}%)</bdi>@if (! $loop->last) · @endif @endforeach
                </span>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $cell }}">{{ __('Site') }}</th><th style="{{ $cell }}">{{ __('Status') }}</th>
                    <th style="{{ $cell }}">rel</th><th style="{{ $cell }}">{{ __('Anchor') }}</th>
                    <th style="{{ $cell }}">{{ __('Checked') }}</th><th style="{{ $cell }}"></th>
                </tr></thead>
                <tbody>
                    @forelse ($links as $l)
                        <tr style="border-top:1px solid rgba(127,127,127,.2);{{ $l->status === 'archived' ? 'opacity:.55' : '' }}">
                            <td style="{{ $cell }}">{{ $l->name }}<br><a href="{{ $l->source_url }}" target="_blank" rel="noopener noreferrer" style="font-size:12px;opacity:.75"><bdi dir="ltr">{{ \Illuminate\Support\Str::limit($l->source_url, 70) }}</bdi></a>@if ($l->note)<br><span style="font-size:12px;opacity:.75">{{ $l->note }}</span>@endif</td>
                            <td style="{{ $cell }};{{ $l->status === 'lost' ? 'color:#dc2626;font-weight:600' : '' }}">{{ $states[$l->status] ?? $l->status }}@if ($l->status === 'lost' && $l->http_status) <bdi dir="ltr">(HTTP {{ $l->http_status }})</bdi>@endif</td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $l->rel }}</bdi></td>
                            <td style="{{ $cell }}"><bdi>{{ $l->anchor }}</bdi>@if ($l->anchor_kind)<br><span style="font-size:12px;opacity:.75">{{ $kinds[$l->anchor_kind] ?? $l->anchor_kind }}@if ($l->links_found > 1) · {{ __(':n links', ['n' => $l->links_found]) }}@endif</span>@endif</td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $l->checked_at?->timezone('Asia/Amman')->format('Y-m-d') ?? '—' }}</bdi></td>
                            <td style="{{ $cell }};white-space:nowrap">
                                @if ($l->status === 'archived')
                                    <x-filament::button size="sm" color="gray" wire:click="restore({{ $l->id }})" style="min-height:44px">{{ __('Restore') }}</x-filament::button>
                                @else
                                    <x-filament::button size="sm" color="gray" wire:click="checkOne({{ $l->id }})" style="min-height:44px">{{ __('Check') }}</x-filament::button>
                                    <x-filament::button size="sm" color="gray" wire:click="archive({{ $l->id }})" style="min-height:44px">{{ __('Archive') }}</x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="{{ $cell }};opacity:.7">{{ __('No known links yet. Paste the live rows from the Link Tracker below.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <form wire:submit="addLinks" style="margin-top:16px;display:grid;gap:8px">
            <label for="links-paste">{{ __('Add links: one per line, as url or url | name | note') }}</label>
            <textarea id="links-paste" wire:model="paste" rows="4" dir="ltr" style="width:100%;border-radius:8px;padding:8px;border:1px solid rgba(127,127,127,.4);background:transparent"></textarea>
            <div><x-filament::button type="submit" style="min-height:44px">{{ __('Add') }}</x-filament::button></div>
        </form>
    </x-filament::section>

    <x-filament::section :heading="__('Link reclamation')">
        <x-slot name="description">{{ __('Addresses that answer 404 on our site (our own log: path and count only) and pages other sites link to (Search Console export). Add a 301 to the closest live page.') }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $cell }}">{{ __('Address') }}</th><th style="{{ $cell }}">{{ __('404 hits') }}</th>
                    <th style="{{ $cell }}">{{ __('Links (Search Console)') }}</th><th style="{{ $cell }}">{{ __('301 to') }}</th><th style="{{ $cell }}"></th>
                </tr></thead>
                <tbody>
                    @forelse ($suggestions as $s)
                        <tr style="border-top:1px solid rgba(127,127,127,.2)">
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $s['path'] }}</bdi>@if ($s['both'])<br><span style="font-size:12px;color:#0d9488;font-weight:600">{{ __('In both lists') }}</span>@endif @if ($s['referrer'])<br><span style="font-size:12px;opacity:.75">{{ __('Last from') }} <bdi dir="ltr">{{ $s['referrer'] }}</bdi></span>@endif</td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $s['hits'] }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $s['links'] }} / {{ $s['sites'] }}</bdi></td>
                            <td style="{{ $cell }}"><input type="text" dir="ltr" wire:model="targets.{{ md5($s['path']) }}" aria-label="{{ __('301 to') }}" placeholder="/pricing" style="width:100%;min-width:160px;min-height:44px;border-radius:8px;padding:6px;border:1px solid rgba(127,127,127,.4);background:transparent"></td>
                            <td style="{{ $cell }};white-space:nowrap">
                                <x-filament::button size="sm" wire:click="addRedirect(@js($s['path']))" style="min-height:44px">{{ __('Add 301') }}</x-filament::button>
                                <x-filament::button size="sm" color="gray" wire:click="ignore(@js($s['path']))" style="min-height:44px">{{ __('Ignore') }}</x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="{{ $cell }};opacity:.7">{{ __('Nothing to reclaim right now.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <form wire:submit="importGsc" style="margin-top:16px;display:grid;gap:8px">
            <label for="gsc-paste">{{ __('Search Console → Links → Top linked pages → Export: paste the CSV here') }}@if ($gscAt) <span style="opacity:.75">· {{ __('last import') }} <bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($gscAt)->timezone('Asia/Amman')->format('Y-m-d') }}</bdi></span>@endif</label>
            <textarea id="gsc-paste" wire:model="gsc" rows="4" dir="ltr" style="width:100%;border-radius:8px;padding:8px;border:1px solid rgba(127,127,127,.4);background:transparent"></textarea>
            <div><x-filament::button type="submit" style="min-height:44px">{{ __('Import') }}</x-filament::button></div>
        </form>
    </x-filament::section>

    <x-filament::section :heading="__('301s and renames')">
        @foreach ($renames as $r)
            <div style="padding:6px 0;border-bottom:1px solid rgba(127,127,127,.2)">
                <span style="{{ $r['ok'] ? 'color:#0d9488' : 'color:#dc2626;font-weight:600' }}">{{ $r['ok'] ? __('OK') : __('Broken') }}</span>
                · {{ __('Rename') }} <bdi dir="ltr">{{ $r['from'] }} → {{ $r['to'] }}</bdi>
            </div>
        @endforeach
        @foreach ($redirects as $r)
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(127,127,127,.2)">
                <span><bdi dir="ltr">{{ $r->from_path }} → {{ $r->to_path }}</bdi> · {{ __(':n visits', ['n' => $r->hits]) }}</span>
                <x-filament::button size="sm" color="gray" wire:click="disableRedirect({{ $r->id }})" style="min-height:44px">{{ __('Turn off') }}</x-filament::button>
            </div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
