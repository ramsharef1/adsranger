@php
    use App\Models\AiKey;
    $muted = 'opacity:.72;font-size:12.5px';
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px;display:grid;gap:8px';
    $ltr = fn ($v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
    $usd = fn ($v) => $ltr(number_format((float) $v, 2).' USD');
@endphp
<x-filament-panels::page>
    @if (! $full)
        <div style="border:1px solid rgba(148,163,184,.35);border-radius:12px;padding:14px;font-size:14px">{{ __(':feature comes with :plan.', ['feature' => \App\Support\PlanGate::featureText('ai_workspace_full', 'name'), 'plan' => \App\Support\PlanGate::tierLabel('pro')]) }} {{ \App\Support\PlanGate::featureText('ai_workspace_full', 'what') }}</div>
    @endif
    <p style="{{ $muted }};font-size:13px;line-height:1.7">{{ __('Your own API key runs the AI box and the night shift. Usage is billed by your provider to your account. AdsRanger stores the key encrypted and never shows it again.') }}</p>

    @foreach ($keys as $k)
        <article style="{{ $card }}">
            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
                <strong>{!! $ltr(AiKey::PROVIDERS[$k->provider] ?? $k->provider) !!} · {!! $ltr('•••• '.$k->last4) !!}</strong>
                <span style="{{ $muted }}">{!! $ltr(\App\Support\AiWorkspace\OwnKey::modelLabel($k->provider, $k->model)) !!}</span>
            </div>
            <div style="font-size:13px">{!! __('This month (estimate): :spent of :cap', ['spent' => $usd($k->spentThisMonth()), 'cap' => $usd($k->monthly_cap_usd)]) !!}</div>
            @if ((float) $k->monthly_cap_usd > 0 && $k->spentThisMonth() >= 0.8 * (float) $k->monthly_cap_usd)
                <div data-cap-80 style="font-size:13px;color:#b45309;font-weight:600">{{ __('Over 80% of the monthly cap. At the cap, requests on this key stop until next month.') }}</div>
            @endif
            {{-- DS-30 (D-301): the model changed; a one-tap 5-task check shows the quality before the night shift runs on it. --}}
            @if ($k->previous_model)
                @php
                    $mc = $k->model_check;
                @endphp
                <div data-model-changed style="border:1px solid rgba(180,83,9,.35);border-radius:10px;padding:10px;font-size:13px;display:grid;gap:6px">
                    <div>{!! __('Model changed from :old to :new.', ['old' => $ltr(\App\Support\AiWorkspace\OwnKey::modelLabel($k->provider, $k->previous_model)), 'new' => $ltr(\App\Support\AiWorkspace\OwnKey::modelLabel($k->provider, $k->model))]) !!}</div>
                    @if (! empty($mc['running']))
                        <div style="{{ $muted }}">{{ __('Check running…') }}</div>
                    @elseif (isset($mc['total']))
                        <div>{!! __('5-task check: :passed of :total passed · average score :score · cost :usd', ['passed' => $ltr($mc['passed']), 'total' => $ltr($mc['total']), 'score' => $ltr($mc['avg_score']), 'usd' => $ltr(number_format((float) $mc['cost'], 3).' USD')]) !!}</div>
                    @else
                        <div style="{{ $muted }}">{!! __('Check the new model on 5 Arabic and English tasks before the night shift uses it (about :usd on your key).', ['usd' => $ltr('≤ '.number_format(\App\Support\AiWorkspace\GoldenSet::quickEstimate($k), 3).' USD')]) !!}</div>
                    @endif
                    @if ($canManage && empty($mc['running']))
                        <div><x-filament::button size="sm" color="gray" wire:click="checkModel({{ $k->id }})" style="min-height:44px">{{ isset($mc['total']) ? __('Check again') : __('Run the 5-task check') }}</x-filament::button></div>
                    @endif
                </div>
            @endif
            <div style="{{ $muted }}">@if ($k->tested_at){{ $k->test_ok ? '✓' : '✗' }} {{ $k->test_message }} · <time datetime="{{ $k->tested_at->toIso8601String() }}">{{ $k->tested_at->locale(app()->getLocale())->diffForHumans() }}</time>@endif</div>
            @if ($canManage)
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end" x-data="{ cap: '{{ (float) $k->monthly_cap_usd }}' }">
                    <div style="width:160px"><label style="{{ $label }}" for="cap-{{ $k->id }}">{{ __('Monthly spend cap') }} (USD)</label><input id="cap-{{ $k->id }}" type="number" min="1" max="1000" step="1" x-model="cap" style="{{ $field }}" dir="ltr"></div>
                    <x-filament::button color="gray" size="sm" x-on:click="$wire.updateCap({{ $k->id }}, cap)" style="min-height:44px">{{ __('Update cap') }}</x-filament::button>
                    <x-filament::button color="danger" size="sm" wire:click="remove({{ $k->id }})" wire:confirm="{{ __('Delete this key? The AI box and night shift stop using it.') }}" style="min-height:44px">{{ __('Delete key') }}</x-filament::button>
                </div>
            @endif
        </article>
    @endforeach

    @if ($canManage)
        <form wire:submit="save" style="{{ $card }}">
            <h3 style="font-size:15px;font-weight:700;margin:0">{{ $keys->isEmpty() ? __('Add your API key') : __('Add or replace a key') }}</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
                <div><label style="{{ $label }}" for="k-provider">{{ __('Provider') }}</label>
                    <select id="k-provider" wire:model.live="provider" style="{{ $field }}">@foreach (AiKey::PROVIDERS as $p => $n)<option value="{{ $p }}">{{ $n }}</option>@endforeach</select></div>
                <div><label style="{{ $label }}" for="k-model">{{ __('Model') }}</label>
                    <select id="k-model" wire:model="model" style="{{ $field }}"><option value="">{{ __('Choose') }}</option>@foreach ($models as $id => $m)<option value="{{ $id }}">{{ $m[0] }}</option>@endforeach</select>
                    @error('model')<p style="color:#b91c1c;font-size:12.5px">{{ $message }}</p>@enderror</div>
                <div><label style="{{ $label }}" for="k-cap">{{ __('Monthly spend cap') }} (USD)</label>
                    <input id="k-cap" type="number" min="1" max="1000" step="1" wire:model="cap" style="{{ $field }}" dir="ltr">
                    @error('cap')<p style="color:#b91c1c;font-size:12.5px">{{ $message }}</p>@enderror</div>
            </div>
            <div><label style="{{ $label }}" for="k-key">{{ __('API key') }}</label>
                <input id="k-key" type="password" autocomplete="off" wire:model="apiKey" style="{{ $field }}" dir="ltr">
                @error('apiKey')<p style="color:#b91c1c;font-size:12.5px">{{ $message }}</p>@enderror</div>
            <p style="{{ $muted }}">{{ __('We test the key with a free call before saving it. The cap is required: the AI box and night shift stop when this month\'s estimated spend reaches it.') }}</p>
            <div><x-filament::button type="submit" style="min-height:44px">{{ __('Test and save') }}</x-filament::button></div>
        </form>
    @else
        <p style="{{ $muted }}">{{ __('Only the owner or an admin can add keys.') }}</p>
    @endif
</x-filament-panels::page>
