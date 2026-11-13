@php
    $muted = 'opacity:.72;font-size:12.5px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px;display:grid;gap:8px';
    $ltr = fn ($v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
    $states = [
        'verified' => ['✓', __('Connected · verified'), 'color:#0F766E'],
        'waiting' => ['…', __('Waiting for first request'), 'color:#B45309'],
        'paused' => ['⏸', __('Paused for unusual activity'), 'color:#B91C1C'],
    ];
@endphp
<x-filament-panels::page>
    <div style="{{ $card }}">
        <h3 style="font-size:15px;font-weight:700;margin:0">{{ __('Connect your AI') }}</h3>
        <p style="margin:0;font-size:14px">{{ __('In your AI\'s settings, add a custom connector with this address, then sign in with your AdsRanger account and choose the clients it may read.') }}</p>
        <p style="margin:0"><code dir="ltr" style="font-size:14px;user-select:all">{{ $url }}</code></p>
        <ul style="margin:0;padding-inline-start:18px;font-size:13px;line-height:1.8">
            <li>{!! __(':ai: Settings › Connectors › Add custom connector (needs a paid plan in this AI).', ['ai' => $ltr('ChatGPT')]) !!}</li>
            <li>{!! __(':ai: Settings › Connectors › Add custom connector (paid plans; limited on the free plan).', ['ai' => $ltr('Claude')]) !!}</li>
            <li>{!! __(':ai, :ai2 and :ai3: where your plan allows custom connectors.', ['ai' => $ltr('Gemini'), 'ai2' => $ltr('Copilot'), 'ai3' => $ltr('Perplexity')]) !!}</li>
        </ul>
        <p style="{{ $muted }}">{{ __('Your AI reads only the clients you tick and can save notes and drafts. It can\'t change any ad account.') }}</p>
    </div>

    @if ($connections->isEmpty())
        <p style="{{ $muted }}">{{ __('No AI connected yet.') }}</p>
    @else
        <div style="display:grid;gap:12px">
            @foreach ($connections as $c)
                @php
                    [$icon, $label, $style] = $states[$c->status] ?? ['', $c->status, ''];
                    $calls = $c->calls_day?->toDateString() === now($tz)->toDateString() ? $c->calls_today : 0;
                    $allowed = collect($c->allowedIds())->map(fn ($id) => $clientNames[$id] ?? null)->filter();
                @endphp
                <article style="{{ $card }}" aria-label="{{ $c->providerName() }}">
                    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:baseline">
                        <h3 style="font-size:15px;font-weight:700;margin:0">{!! $ltr($c->providerName()) !!}</h3>
                        <span style="font-weight:600;{{ $style }}">{{ $icon }} {{ $label }}</span>
                    </div>
                    <div style="{{ $muted }}">{!! __('Connected by :name', ['name' => '<bdi>'.e($c->user?->name ?? '—').'</bdi>']) !!} · <time datetime="{{ $c->created_at->toIso8601String() }}">{{ $c->created_at->locale(app()->getLocale())->translatedFormat(app()->getLocale() === 'ar' ? 'j F Y' : 'j M Y') }}</time></div>
                    <div style="font-size:14px">
                        {!! __('Clients it may read: :n', ['n' => $ltr($allowed->count())]) !!}
                        @if ($allowed->isNotEmpty())<span style="{{ $muted }}"> · @foreach ($allowed as $n)<bdi>{{ $n }}</bdi>@if (! $loop->last)، @endif @endforeach</span>@endif
                    </div>
                    <div style="{{ $muted }}">
                        @if ($c->last_call_at){!! __('Last used: :when', ['when' => e($c->last_call_at->locale(app()->getLocale())->diffForHumans())]) !!} · @endif
                        {!! __(':n of :cap requests today', ['n' => $ltr($calls), 'cap' => $ltr($cap)]) !!}
                    </div>
                    @if ($c->status === 'paused')
                        <div style="font-size:13px">{{ __('What we saw:') }} {{ $c->paused_reason }}</div>
                    @endif
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <x-filament::button color="danger" size="sm" wire:click="disconnect({{ $c->id }})"
                            wire:confirm="{{ __(':ai stops reading your data at once. What you saved stays.', ['ai' => $c->providerName()]) }}" style="min-height:44px">{{ __('Disconnect') }}</x-filament::button>
                        @if ($c->status === 'paused' && $isOwner)
                            <x-filament::button color="gray" size="sm" wire:click="restore({{ $c->id }})" style="min-height:44px">{{ __('Restore after review') }}</x-filament::button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
    {{-- WS-10 / FS-10: what an external AI can call, a switch per tool, and the last calls. --}}
    <x-filament::section :heading="__('Tools your AI can call')" data-tool-catalogue>
        <x-slot name="description">{{ __('Every tool is read or save only; none changes an ad account. Switch off a tool and every AI connected to this workspace loses it.') }}</x-slot>
        <div style="display:grid;gap:8px">
            @foreach ($tools as $name => $t)
                <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;border-bottom:1px solid rgba(148,163,184,.2);padding-bottom:6px">
                    <div style="min-width:0"><bdi dir="ltr" style="font-weight:600">{{ $name }}</bdi><div style="font-size:12.5px;opacity:.75" dir="ltr">{{ \Illuminate\Support\Str::limit($t['description'], 160) }}</div></div>
                    @if ($canManage)
                        <x-filament::button size="xs" :color="$t['off'] ? 'danger' : 'gray'" wire:click="toggleTool('{{ $name }}')">{{ $t['off'] ? __('Off (switch on)') : __('On (switch off)') }}</x-filament::button>
                    @else
                        <span style="font-size:12.5px">{{ $t['off'] ? __('Off') : __('On') }}</span>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($canManage)
            <h4 style="font-size:14px;margin:14px 0 6px">{{ __('Last calls') }}</h4>
            @forelse ($toolCalls as $c)
                <div style="font-size:12.5px"><bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($c->created_at)->timezone($tz)->format('Y-m-d H:i') }}</bdi> · <bdi dir="ltr">{{ $c->tool }}</bdi>@if ($c->client_id) · <bdi>{{ $clientNames[$c->client_id] ?? '#'.$c->client_id }}</bdi>@endif · <span style="{{ $c->status < 400 ? 'color:#0d9488' : 'color:#dc2626' }}">{{ $c->status < 400 ? __('OK') : __('Refused') }}</span></div>
            @empty
                <p style="font-size:13px;opacity:.75;margin:0">{{ __('No calls yet. Once your AI uses a tool, it shows here, so you can see the connection works.') }}</p>
            @endforelse
        @endif
    </x-filament::section>
</x-filament-panels::page>
