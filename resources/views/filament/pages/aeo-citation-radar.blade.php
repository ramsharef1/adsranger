<x-filament-panels::page>
@php
    $bd = fn (string $t) => (string) \App\Support\Seo\ToolCopy::html($t, $ar);
    $tz = \Filament\Facades\Filament::getTenant()?->timezone ?: 'UTC';
    $when = fn ($d) => $d ? $d->timezone($tz)->format('Y-m-d H:i') : '';
    $labels = \App\Filament\Pages\AeoCitationRadarPage::signalLabels();
@endphp
<style>
.ar-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.ar-card{padding:12px 14px;border-radius:12px;border:1px solid rgba(148,163,184,.25);background:rgba(148,163,184,.06)}
.ar-card strong{display:block;font-size:24px;line-height:1.2}
.ar-card span{font-size:12.5px;opacity:.8}
.ar-in{width:100%;min-height:44px;border-radius:8px;border:1px solid rgba(148,163,184,.5);padding:8px 12px;background:transparent;color:inherit}
.ar-btn{min-height:44px;padding:8px 16px;border-radius:8px;background:#0f766e;color:#fff;font-weight:600;border:0;cursor:pointer}
.ar-btn.ghost{background:transparent;color:inherit;border:1px solid rgba(148,163,184,.5)}
.ar-muted{font-size:13px;opacity:.75;line-height:1.6}
.ar-list{list-style:none;margin:8px 0 0;padding:0;font-size:13.5px}
.ar-list li{display:flex;gap:8px;align-items:flex-start;padding:8px 0;border-top:1px solid rgba(148,163,184,.18)}
.ar-ok{color:#0f766e}.dark .ar-ok{color:#5eead4}
.ar-no{color:#b45309}.dark .ar-no{color:#fbbf24}
</style>

@if (! $client)
    <x-filament::section><p class="ar-muted">{{ __('Add or select a client first to audit its website.') }}</p></x-filament::section>
@else
    <x-filament::section>
        <x-slot name="heading">{{ $client->name }} · {{ __('Brand and questions') }}</x-slot>
        @if ($canManage)
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
                <label class="ar-muted">{{ __('Brand name in Arabic') }}<input type="text" wire:model="brandAr" class="ar-in" dir="rtl" maxlength="120"></label>
                <label class="ar-muted">{{ __('Brand name in English') }}<input type="text" wire:model="brandEn" class="ar-in" dir="ltr" maxlength="120"></label>
                <label class="ar-muted">{{ __('Main domain') }}<input type="text" wire:model="domain" class="ar-in" dir="ltr" placeholder="example.com" maxlength="190"></label>
            </div>
            @error('brandAr')<p style="color:#b91c1c;font-size:13px">{{ $message }}</p>@enderror
            @error('domain')<p style="color:#b91c1c;font-size:13px">{{ $message }}</p>@enderror
            <label class="ar-muted" style="display:block;margin-top:10px">{{ __('Questions people ask AI assistants (one per line, up to 10)') }}
                <textarea wire:model="promptsText" rows="4" class="ar-in" placeholder="{{ __('Best perfume shop in Riyadh?') }}"></textarea></label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                <button type="button" class="ar-btn" wire:click="saveMonitor">{{ __('Save') }}</button>
                @if ($monitor)<button type="button" class="ar-btn ghost" wire:click="runReadiness" wire:loading.attr="disabled">{{ __('Check AI readiness') }}</button>@endif
            </div>
        @elseif ($monitor)
            <p class="ar-muted">{{ $monitor->brand_name_ar }} · <bdi dir="ltr">{{ $monitor->brand_name_en }}</bdi> · <bdi dir="ltr">{{ $monitor->primary_domain }}</bdi></p>
        @else
            <p class="ar-muted">{{ __('An owner, admin or manager sets up the brand first.') }}</p>
        @endif
    </x-filament::section>

    @if ($monitor)
        <x-filament::section>
            <x-slot name="heading">{{ __('AI readiness') }}</x-slot>
            @if ($readiness)
                <div class="ar-grid">
                    <div class="ar-card"><strong>{{ $readiness->readiness_score }}<small style="font-size:14px">/100</small></strong><span>{{ __('Readiness score') }}</span></div>
                </div>
                <p class="ar-muted" style="margin-top:8px">{{ __('From our own checks of the site: how ready it is to be read and cited by AI assistants. It is not a citation rate.') }} · <bdi dir="ltr">{{ $when($readiness->checked_at) }}</bdi></p>
                <ul class="ar-list">
                    @foreach ($weights as $key => $weight)
                        @php $met = $readiness->signals['met'][$key] ?? false; $full = $met === true || $met === 1.0 || $met === 1; @endphp
                        <li>
                            <span class="{{ $full ? 'ar-ok' : 'ar-no' }}" aria-hidden="true">{{ $full ? '✓' : '•' }}</span>
                            <span style="flex:1">{!! $bd($labels[$key] ?? $key) !!}
                                @if ($key === 'robots' && ! empty($readiness->signals['robots']['blocked']))
                                    <br><span class="ar-muted">{{ __('Blocked crawlers') }}: <bdi dir="ltr">{{ implode(', ', $readiness->signals['robots']['blocked']) }}</bdi></span>
                                @endif
                            </span>
                            <span class="ar-muted"><bdi dir="ltr">{{ is_float($met) ? (int) round($met * $weight) : ($met ? $weight : 0) }}/{{ $weight }}</bdi></span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="ar-muted">{{ __('Not checked yet.') }}</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('Brand mentions in AI answers') }}</x-slot>
            @if ($rates)
                <div class="ar-grid">
                    <div class="ar-card"><strong>{{ $rates['mentioned'] }}/{{ $rates['answered'] }}</strong><span>{{ __('Answers that name the brand') }}</span></div>
                    <div class="ar-card"><strong>{{ $rates['cited'] }}/{{ $rates['answered'] }}</strong><span>{{ __('Answers that cite the domain') }}</span></div>
                </div>
                <p class="ar-muted" style="margin-top:8px">{!! $bd(__('Asked with your own key (:provider, :model) without web search, so answers come from the model\'s own knowledge.', ['provider' => $probe->signals['provider'] ?? '', 'model' => $probe->signals['model'] ?? ''])) !!} · <bdi dir="ltr">{{ $when($probe->checked_at) }}</bdi>@if (! empty($probe->signals['error'])) · {{ __('Stopped early: :reason', ['reason' => $probe->signals['error']]) }}@endif</p>
                <ul class="ar-list">
                    @foreach ($probe->citations as $row)
                        <li><span class="{{ $row['mentioned'] ? 'ar-ok' : 'ar-no' }}" aria-hidden="true">{{ $row['mentioned'] ? '✓' : '•' }}</span>
                            <span style="flex:1">{{ $row['prompt'] }}<br><span class="ar-muted">{{ \Illuminate\Support\Str::limit($row['excerpt'], 200) }}</span></span>
                            @if ($row['position'])<span class="ar-muted">#{{ $row['position'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="ar-muted">{{ __('No AI answers checked yet, so there is no citation rate.') }}</p>
            @endif
            @if ($canManage)
                @if ($keys->isEmpty())
                    <p class="ar-muted" style="margin-top:10px">{{ __('Add your own AI key with a monthly cap first (AI keys page).') }}</p>
                @else
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px">
                        <select wire:model="keyProvider" class="ar-in" style="max-width:240px" aria-label="{{ __('AI key') }}">
                            @foreach ($keys as $k)<option value="{{ $k->provider }}">{{ \App\Models\AiKey::PROVIDERS[$k->provider] ?? $k->provider }} · {{ $k->model }}</option>@endforeach
                        </select>
                        <button type="button" class="ar-btn ghost" wire:click="runProbe" wire:loading.attr="disabled">{{ __('Ask the questions now') }}</button>
                    </div>
                    <p class="ar-muted">{{ __('Uses your key\'s monthly cap; the provider bills you directly.') }}</p>
                @endif
            @endif
        </x-filament::section>
    @endif
@endif
</x-filament-panels::page>
