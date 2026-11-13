@php
    use App\Models\AiKey;
    use App\Support\Severity;
    $muted = 'opacity:.72;font-size:12.5px';
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px;display:grid;gap:8px';
    $ltr = fn ($v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
    $levelStyle = ['critical' => 'color:#B91C1C', 'warning' => 'color:#B45309', 'info' => 'color:#0F766E'];
    $fmt = fn ($t) => $t->copy()->timezone($tz)->locale(app()->getLocale())->translatedFormat(app()->getLocale() === 'ar' ? 'j F، g:i A' : 'j M, g:i a');
@endphp
<x-filament-panels::page>
    <p style="{{ $muted }};font-size:13px;line-height:1.7">{{ __('Each night your own AI key reviews the clients you choose and leaves a short morning list. It reads only: nothing changes in any ad account.') }}</p>

    <section style="{{ $card }}">
        <h3 style="font-size:15px;font-weight:700;margin:0">{{ __('This morning') }}</h3>
        @if (! $last)
            <p style="{{ $muted }}">{{ __('No night shift has run yet.') }}</p>
        @elseif ($items->isEmpty())
            <p style="margin:0">{{ __('The night shift found nothing that needs your attention.') }} <span style="{{ $muted }}"><time datetime="{{ $last->started_at->toIso8601String() }}">{{ $fmt($last->started_at) }}</time></span></p>
        @else
            <p style="margin:0;font-weight:600">{!! __('The night shift found :n things that need your attention.', ['n' => $ltr($items->count())]) !!}</p>
            @foreach ($items as $it)
                @php $lv = $it->meta['level'] ?? 'info'; @endphp
                <article style="border-top:1px solid rgba(148,163,184,.2);padding-top:10px;display:grid;gap:4px">
                    <div style="font-weight:600;{{ $levelStyle[$lv] ?? '' }}">{{ Severity::label($lv) }} · <bdi>{{ $it->client?->name }}</bdi></div>
                    <div style="font-weight:600"><bdi>{{ $it->title }}</bdi></div>
                    <div style="font-size:13.5px;line-height:1.7" dir="auto">{!! nl2br(e($it->clean ?? $it->original)) !!}</div>
                    <div style="{{ $muted }}">
                        @if (! empty($it->meta['campaign']))<bdi>{{ $it->meta['campaign'] }}</bdi> · @endif
                        {!! __('Quality score :score · :band', ['score' => $ltr($it->score), 'band' => e(\App\Support\AiWorkspace\QualityGate::bandLabel($it->band))]) !!}
                        · <a href="{{ \App\Filament\Pages\ClientBrainPage::getUrl() }}" style="color:#0F766E;text-decoration:underline">{{ __('Open the client brain') }}</a>
                    </div>
                </article>
            @endforeach
        @endif
    </section>

    @if ($canManage)
        <form wire:submit="save" style="{{ $card }}">
            <h3 style="font-size:15px;font-weight:700;margin:0">{{ __('Settings') }}</h3>
            @if ($keys->isEmpty())
                <p style="margin:0">{{ __('Add a key with a monthly cap first (My API keys).') }} <a href="{{ \App\Filament\Pages\AiKeysPage::getUrl() }}" style="color:#0F766E;text-decoration:underline">{{ __('My API keys') }}</a></p>
            @endif
            <label style="display:flex;gap:10px;align-items:center;min-height:44px"><input type="checkbox" wire:model="enabled" style="width:20px;height:20px"> {{ __('Run the night shift every night') }}</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
                <div><label for="ns-h" style="{{ $label }}">{{ __('Run at (workspace time)') }}</label>
                    <select id="ns-h" wire:model="runHour" style="{{ $field }}" dir="ltr">@for ($h = 0; $h < 24; $h++)<option value="{{ $h }}">{{ sprintf('%02d:05', $h) }}</option>@endfor</select></div>
                <div><label for="ns-k" style="{{ $label }}">{{ __('Key') }}</label>
                    <select id="ns-k" wire:model="provider" style="{{ $field }}"><option value="">{{ __('Choose') }}</option>@foreach ($keys as $k)<option value="{{ $k->provider }}">{{ AiKey::PROVIDERS[$k->provider] }}</option>@endforeach</select>
                    @error('provider')<p style="color:#b91c1c;font-size:12.5px">{{ $message }}</p>@enderror</div>
            </div>
            <fieldset style="border:0;padding:0;margin:0"><legend style="{{ $label }}">{{ __('Clients included') }}</legend>
                @foreach ($clients as $c)
                    <label style="display:flex;gap:10px;align-items:center;min-height:44px"><input type="checkbox" wire:model="clientIds" value="{{ $c->id }}" style="width:20px;height:20px"> <bdi>{{ $c->name }}</bdi></label>
                @endforeach
            </fieldset>
            <p style="{{ $muted }}">{{ __('Each run costs a little on your key (about one request per client). It stops when the key\'s monthly cap is reached.') }}</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <x-filament::button type="submit" style="min-height:44px">{{ __('Save') }}</x-filament::button>
                @if ($isOwner)<x-filament::button type="button" color="gray" wire:click="runNow" wire:loading.attr="disabled" style="min-height:44px">{{ __('Run now') }}</x-filament::button>@endif
            </div>
        </form>
    @endif

    <section style="{{ $card }}">
        <h3 style="font-size:15px;font-weight:700;margin:0">{{ __('Run log') }}</h3>
        @forelse ($runs as $r)
            <div style="font-size:13px;border-top:1px solid rgba(148,163,184,.2);padding-top:6px">
                <time datetime="{{ $r->started_at->toIso8601String() }}">{{ $fmt($r->started_at) }}</time> ·
                {!! __('Clients checked: :c · items: :i · cost on your key: :usd (estimate)', ['c' => $ltr($r->clients_checked), 'i' => $ltr($r->items_found), 'usd' => $ltr(number_format((float) $r->cost_usd, 4).' USD')]) !!}
                @if ($r->status !== 'ok') · <strong>{{ $r->status === 'partial' ? __('Stopped part way') : __('Failed') }}:</strong> {{ $r->error }}@endif
            </div>
        @empty
            <p style="{{ $muted }}">{{ __('No runs yet.') }}</p>
        @endforelse
    </section>
</x-filament-panels::page>
