@php
    $card = 'display:grid;gap:6px;padding:14px;border:1px solid rgba(148,163,184,.3);border-radius:12px';
    $partLabels = ['spend' => __('Spend, cost and revenue'), 'results' => __('Results, clicks and impressions'), 'rules' => __('Client rules')];
    $rows = [
        'ai_box' => [__('Sends'), __('The playbook, your note, and the client facts you allow (last 7 and 30 days), in the client\'s currency with dates.'),
            __('Cannot'), __('Change ads, budgets or settings. It writes a draft that you read before anything is sent to a client.')],
        'night_shift' => [__('Sends'), __('Once a night: the allowed client facts and the open alerts of the clients you picked.'),
            __('Cannot'), __('Change anything. It reads and writes morning notes only.')],
        'connector' => [__('Sends'), __('Only what your own AI assistant asks for, only for the clients you shared with that connection.'),
            __('Cannot'), __('Read clients you did not share, or reach another workspace. Odd requests are logged and can pause the connection.')],
    ];
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <div style="display:grid;gap:8px;font-size:14px">
            <p style="margin:0">{{ __('Every AI request goes to your own AI provider, on your own key and its monthly cap. AdsRanger does not send your data to any other AI.') }}</p>
            <p style="margin:0">{{ __('Before anything is sent, phone numbers, emails and ID-like numbers are replaced with a placeholder, and the run shows how many were hidden.') }}</p>
            <p style="margin:0">{{ __('If your provider does not answer, the night shift waits and tries again for up to 6 hours. It never switches to another provider or to our credits.') }}</p>
        </div>
    </x-filament::section>

    @foreach ($tools as $tool => $t)
        <section style="{{ $card }}" data-ai-tool="{{ $tool }}">
            <strong>{{ __($t['label']) }} @if ($t['off'])<span style="color:#dc2626;font-weight:600">· {{ __('Paused for everyone for now') }}</span>@endif</strong>
            <div style="font-size:14px"><strong>{{ $rows[$tool][0] }}:</strong> {{ $rows[$tool][1] }}</div>
            <div style="font-size:14px"><strong>{{ $rows[$tool][2] }}:</strong> {{ $rows[$tool][3] }}</div>
        </section>
    @endforeach

    <x-filament::section :heading="__('Clients with limits')">
        @forelse ($limited as $c)
            <div style="font-size:14px"><bdi>{{ $c['name'] }}</bdi>: {{ $c['parts'] ? collect($c['parts'])->map(fn ($p) => $partLabels[$p] ?? $p)->implode(' · ') : __('nothing') }}</div>
        @empty
            <p style="margin:0;font-size:14px;opacity:.8">{{ __('No limits set: AI may read spend, results and client rules for every client. Change it per client in Client brain › What your AI sees.') }}</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
