<x-filament-panels::page>
    <div style="max-width:640px">
            <div style="background:rgba(15,23,42,.04);border:1px solid rgba(148,163,184,.28);border-radius:12px;padding:16px">
                <h3 style="margin:0 0 8px;font-size:15px;font-weight:700">{{ __("Client Onboarding & Access Link") }}</h3>
                <form wire:submit="createOnboardingLink" style="display:grid;gap:8px;margin-bottom:12px">
                    <select wire:model="clientId" style="min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                        <option value="">{{ __('New client (not added yet)') }}</option>
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    <input type="text" wire:model="prospectName" placeholder="{{ __("Client or Prospect Name") }}"
                           style="padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                    <input type="url" wire:model="prospectWebsite" placeholder="https://client-domain.com"
                           style="padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                    <button type="submit" style="padding:8px 14px;border-radius:8px;background:#0F766E;color:#fff;border:0;font-weight:600;cursor:pointer">
                        {{ __("Generate Onboarding Link") }}
                    </button>
                </form>
                @foreach ($onboardingLinks as $ob)
                    <div style="font-size:12px;padding:6px 0;border-top:1px solid rgba(148,163,184,.2)">
                        <strong>{{ $ob->prospect_name }}</strong> ({{ $ob->status }}) —
                        <a href="{{ url('/onboard/'.$ob->token) }}" target="_blank" style="text-decoration:underline">/onboard/{{ $ob->token }}</a>
                        @if (! empty($ob->intake))
                            <details style="margin-top:4px"><summary>{{ __('Client intake answers (drafts to confirm)') }}</summary>
                                @foreach ($ob->intake as $k => $v)<div><strong>{{ \App\Http\Controllers\ClientOnboardingController::intakeLabels()[$k] ?? $k }}:</strong> <bdi>{{ $v }}</bdi></div>@endforeach
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>

    </div>
</x-filament-panels::page>
