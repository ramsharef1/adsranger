@php
    $cell = 'padding:6px;text-align:start;vertical-align:top';
    $ok = fn (bool $b) => $b ? 'color:#0d9488' : 'color:#dc2626;font-weight:600';
    $kinds = ['guide' => __('Guide'), 'post' => __('Article'), 'term' => __('Term')];
@endphp
<x-filament-panels::page>
    <x-filament::section :heading="__('Held pages and their checks')">
        <x-slot name="description">
            {{ __('A page goes back into the sitemap when its Arabic page has the right length and schema, links out to at least :n pages, passes the copy rules, and is ticked ready on Growth\'s word.', ['n' => $minOut]) }}
            {{ __('Links in are shown only: a held page is not linked by design.') }}
            @unless ($heldAll) <strong>{{ __('All articles are public right now, so this gate changes nothing yet.') }}</strong> @endunless
        </x-slot>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
            <x-filament::button size="sm" wire:click="checkNow">{{ __('Check all now') }}</x-filament::button>
            <span style="font-size:13px;opacity:.75">{{ __('Last check') }}: <bdi dir="ltr">{{ $lastCheck ? \Illuminate\Support\Carbon::parse($lastCheck)->timezone('Asia/Amman')->format('Y-m-d H:i') : '—' }}</bdi> · {{ __('also every night') }}</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:13px;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $cell }}">{{ __('Page') }}</th><th style="{{ $cell }}">{{ __('Words') }}</th><th style="{{ $cell }}">{{ __('Schema') }}</th>
                    <th style="{{ $cell }}">{{ __('Links out') }}</th><th style="{{ $cell }}">{{ __('Links in') }}</th><th style="{{ $cell }}">{{ __('Copy rules') }}</th>
                    <th style="{{ $cell }}">{{ __('Ready') }}</th><th style="{{ $cell }}"></th>
                </tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php [$min, $max] = $bands[$r->kind]; $pass = \App\Support\Site\ReindexGate::passes($r); @endphp
                        <tr style="border-top:1px solid rgba(127,127,127,.2)">
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $r->path }}</bdi><br><span style="opacity:.7">{{ $kinds[$r->kind] ?? $r->kind }}</span></td>
                            <td style="{{ $cell }};{{ $ok((bool) $r->band_ok) }}">{{ $r->words }} <span style="opacity:.7;font-weight:400"><bdi dir="ltr">({{ $min }}–{{ $max }})</bdi></span></td>
                            <td style="{{ $cell }};{{ $ok((bool) $r->schema_ok) }}">{{ $r->schema_ok ? __('OK') : __('Missing') }}</td>
                            <td style="{{ $cell }};{{ $ok($r->out_links >= $minOut) }}">{{ $r->out_links }}</td>
                            <td style="{{ $cell }}">{{ $r->in_links }}</td>
                            <td style="{{ $cell }};{{ $ok((int) $r->lint_errors === 0) }}">{{ (int) $r->lint_errors === 0 ? __('OK') : $r->lint_errors }}@if ($r->lint_first)<br><span style="font-weight:400"><bdi>{{ $r->lint_first }}</bdi></span>@endif</td>
                            <td style="{{ $cell }}">
                                @if ($r->ready_at)
                                    <span style="color:#0d9488">{{ __('Ready') }}</span> <x-filament::link tag="button" size="sm" wire:click="ready('{{ $r->path }}', false)">{{ __('Undo') }}</x-filament::link>
                                @else
                                    <x-filament::button size="xs" color="gray" wire:click="ready('{{ $r->path }}')">{{ __('Mark ready') }}</x-filament::button>
                                @endif
                            </td>
                            <td style="{{ $cell }}">
                                @if ($r->released_at)
                                    <span style="color:#0d9488">{{ __('Released') }}</span> <x-filament::link tag="button" size="sm" wire:click="hold('{{ $r->path }}')">{{ __('Hold again') }}</x-filament::link>
                                @else
                                    <x-filament::button size="xs" :disabled="! ($pass && $r->ready_at)" wire:click="release('{{ $r->path }}')">{{ __('Release') }}</x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td style="{{ $cell }}" colspan="8">{{ __('Not checked yet. Press "Check all now".') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
