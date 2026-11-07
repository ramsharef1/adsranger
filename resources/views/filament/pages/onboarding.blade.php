@php
    $field = 'min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font:inherit;font-size:14px;width:100%';
    $label = 'display:block;font-size:13px;font-weight:600;margin-bottom:6px';
    $hint = 'opacity:.7;font-size:12.5px;margin-top:4px';
    $err = 'color:var(--danger-600);font-size:12.5px;margin-top:4px';
    $choice = fn ($on) => 'display:flex;align-items:center;gap:10px;min-height:52px;padding:10px 14px;border-radius:10px;cursor:pointer;border:1px solid '.($on ? 'var(--primary-500)' : 'rgba(148,163,184,.35)').';background:'.($on ? 'color-mix(in srgb, var(--primary-500) 10%, transparent)' : 'transparent');
    $clientWord = $inHouse ? 'brand' : 'client';
@endphp
<x-filament-panels::page>
    <div style="max-width:640px;width:100%;margin-inline:auto;display:grid;gap:16px">
        <div style="opacity:.75;font-size:13px">
            {{ __('onboarding.progress', ['step' => $shownIndex, 'total' => $shownSteps]) }}
            · {{ $step <= \App\Support\Onboarding::REQUIRED_STEPS ? __('onboarding.required') : __('onboarding.optional') }}
        </div>
        <div style="height:6px;border-radius:99px;background:rgba(148,163,184,.25);overflow:hidden" aria-hidden="true">
            <div style="height:6px;width:{{ (int) round($shownIndex / $shownSteps * 100) }}%;background:var(--primary-600)"></div>
        </div>

        <x-filament::section>
            <form wire:submit="save" style="display:grid;gap:16px">
                @if ($step === 1)
                    <h2 style="font-size:18px;font-weight:700;margin:0">{{ __('onboarding.step1.title') }}</h2>
                    <fieldset style="display:grid;gap:10px;border:0;padding:0;margin:0">
                        <legend style="{{ $hint }};margin-bottom:8px">{{ __('onboarding.step1.help') }}</legend>
                        @foreach (\App\Support\Onboarding::AUDIENCES as $key)
                            <label style="{{ $choice($audience === $key) }}">
                                <input type="radio" wire:model.live="audience" value="{{ $key }}" name="audience">
                                <span><strong>{{ __('onboarding.audience.'.$key) }}</strong><br><span style="{{ $hint }}">{{ __('onboarding.audience.'.$key.'_help') }}</span></span>
                            </label>
                        @endforeach
                    </fieldset>
                    @error('audience') <div style="{{ $err }}">{{ __('onboarding.step1.error') }}</div> @enderror

                @elseif ($step === 2)
                    <h2 style="font-size:18px;font-weight:700;margin:0">{{ __('onboarding.step2.title') }}</h2>
                    <div><label style="{{ $label }}" for="ob-name">{{ __('onboarding.step2.name') }}</label>
                        <input id="ob-name" type="text" wire:model="name" style="{{ $field }}" maxlength="140" required>
                        @error('name') <div style="{{ $err }}">{{ $message }}</div> @enderror</div>
                    <div><label style="{{ $label }}" for="ob-country">{{ __('onboarding.step2.country') }}</label>
                        <select id="ob-country" wire:model="country" style="{{ $field }}">
                            @foreach ($countries as $code => $countryName) <option value="{{ $code }}">{{ __($countryName) }}</option> @endforeach
                        </select>
                        @error('country') <div style="{{ $err }}">{{ $message }}</div> @enderror</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(180px,100%),1fr));gap:12px">
                        <div><label style="{{ $label }}" for="ob-currency">{{ __('onboarding.step2.currency') }}</label>
                            <select id="ob-currency" wire:model="currency" style="{{ $field }}" dir="ltr">
                                @foreach ($currencies as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach
                            </select></div>
                        <div><label style="{{ $label }}" for="ob-language">{{ __('onboarding.step2.language') }}</label>
                            <select id="ob-language" wire:model="language" style="{{ $field }}">
                                @foreach (config('adsranger.languages') as $code => $langName) <option value="{{ $code }}">{{ $langName }}</option> @endforeach
                            </select></div>
                        <div><label style="{{ $label }}" for="ob-tz">{{ __('onboarding.step2.timezone') }}</label>
                            <select id="ob-tz" wire:model="timezone" style="{{ $field }}" dir="ltr">
                                @foreach ($timezones as $tz) <option value="{{ $tz }}">{{ $timezoneLabel($tz) }} ({{ $tz }})</option> @endforeach
                            </select></div>
                    </div>

                @elseif ($step === 3)
                    <h2 style="font-size:18px;font-weight:700;margin:0">{{ __('onboarding.step3.title_'.$clientWord) }}</h2>
                    <p style="{{ $hint }};margin:0">{{ __('onboarding.step3.help_'.$clientWord) }}</p>
                    <div><label style="{{ $label }}" for="ob-client">{{ __('onboarding.step3.name_'.$clientWord) }}</label>
                        <input id="ob-client" type="text" wire:model="clientName" style="{{ $field }}" maxlength="140" required>
                        @error('clientName') <div style="{{ $err }}">{{ $message }}</div> @enderror</div>
                    <div><label style="{{ $label }}" for="ob-vertical">{{ __('onboarding.step3.sector') }} <span style="font-weight:400;opacity:.7">({{ __('onboarding.optional_word') }})</span></label>
                        <select id="ob-vertical" wire:model="vertical" style="{{ $field }}">
                            <option value="">—</option>
                            @foreach ($verticals as $key => $verticalName) <option value="{{ $key }}">{{ __($verticalName) }}</option> @endforeach
                        </select></div>

                @elseif ($step === 4)
                    <h2 style="font-size:18px;font-weight:700;margin:0">{{ __('onboarding.step4.title') }}</h2>
                    <p style="{{ $hint }};margin:0">{{ __('onboarding.step4.help') }}</p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:12px">
                        <div><label style="{{ $label }}" for="ob-budget">{{ __('onboarding.step4.budget') }} (<bdi dir="ltr">{{ $agency->currency ?: 'USD' }}</bdi>)</label>
                            <input id="ob-budget" type="number" min="0" step="any" inputmode="decimal" dir="ltr" wire:model="monthlyBudget" style="{{ $field }}">
                            @error('monthlyBudget') <div style="{{ $err }}">{{ $message }}</div> @enderror</div>
                        <div><label style="{{ $label }}" for="ob-cpr">{{ __('onboarding.step4.target_cpr') }} (<bdi dir="ltr">{{ $agency->currency ?: 'USD' }}</bdi>)</label>
                            <input id="ob-cpr" type="number" min="0" step="any" inputmode="decimal" dir="ltr" wire:model="targetCpr" style="{{ $field }}">
                            @error('targetCpr') <div style="{{ $err }}">{{ $message }}</div> @enderror</div>
                    </div>
                    <p style="{{ $hint }};margin:0">{{ __('onboarding.step4.later') }}</p>

                @elseif ($step === 5)
                    <h2 style="font-size:18px;font-weight:700;margin:0">{{ __('onboarding.step5.title') }}</h2>
                    <p style="{{ $hint }};margin:0">{{ __('onboarding.step5.help') }}</p>
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                        <x-filament::button tag="a" :href="$networksUrl" color="primary" icon="heroicon-o-link">{{ __('onboarding.step5.connect') }}</x-filament::button>
                        <x-filament::button tag="a" :href="$importsUrl" color="gray" icon="heroicon-o-arrow-up-tray">{{ __('onboarding.step5.import') }}</x-filament::button>
                    </div>
                    <p style="{{ $hint }};margin:0">{{ __('onboarding.step5.empty') }}</p>

                @elseif ($step === 6)
                    <h2 style="font-size:18px;font-weight:700;margin:0">{{ __('onboarding.step6.title') }}</h2>
                    @if (! $hasTeamStep)
                    <p style="margin:0">{{ __('onboarding.step6.solo_note') }}</p>
                    <div><x-filament::button tag="a" :href="$planUrl" color="gray">{{ __('onboarding.step6.solo_link') }}</x-filament::button></div>
                    @else
                    <p style="{{ $hint }};margin:0">{{ __('onboarding.step6.help') }}</p>
                    <div><label style="{{ $label }}" for="ob-email">{{ __('onboarding.step6.email') }}</label>
                        <input id="ob-email" type="email" dir="ltr" wire:model="inviteEmail" style="{{ $field }}" maxlength="190">
                        @error('inviteEmail') <div style="{{ $err }}">{{ $message }}</div> @enderror</div>
                    <div><label style="{{ $label }}" for="ob-role">{{ __('onboarding.step6.role') }}</label>
                        <select id="ob-role" wire:model="inviteRole" style="{{ $field }}">
                            @foreach (\App\Support\Onboarding::INVITE_ROLES as $r) <option value="{{ $r }}">{{ __(config('adsranger.member_roles')[$r]) }}</option> @endforeach
                        </select></div>
                    @endif

                @elseif ($step === 7)
                    <h2 style="font-size:18px;font-weight:700;margin:0">{{ __('onboarding.step7.title') }}</h2>
                    <p style="{{ $hint }};margin:0">{{ __('onboarding.step7.help', ['tz' => $timezoneLabel($agency->timezone)]) }}</p>
                    <label style="{{ $choice($emailDigest) }}"><input type="checkbox" wire:model.live="emailDigest"> <span>{{ __('onboarding.step7.digest') }}</span></label>
                    <label style="{{ $choice($emailAlerts) }}"><input type="checkbox" wire:model.live="emailAlerts"> <span>{{ __('onboarding.step7.alerts') }}</span></label>
                @endif

                <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;margin-top:4px">
                    <div>
                        @if ($step > 1)
                            <x-filament::button type="button" color="gray" wire:click="back">{{ __('onboarding.back') }}</x-filament::button>
                        @endif
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                        @if ($step > \App\Support\Onboarding::REQUIRED_STEPS)
                            <x-filament::button type="button" color="gray" wire:click="skip">{{ __('onboarding.skip') }}</x-filament::button>
                        @endif
                        <x-filament::button type="submit">
                            {{ $step === 6 ? ($hasTeamStep ? __('onboarding.step6.send') : __('onboarding.step6.continue_solo')) : ($step === \App\Support\Onboarding::LAST_STEP ? __('onboarding.finish') : ($step === 5 ? __('onboarding.continue') : __('onboarding.save_continue'))) }}
                        </x-filament::button>
                    </div>
                </div>
            </form>
        </x-filament::section>
        @if ($step <= \App\Support\Onboarding::REQUIRED_STEPS)
            <p style="{{ $hint }}">{{ __('onboarding.why_required') }}</p>
        @endif
    </div>
</x-filament-panels::page>
