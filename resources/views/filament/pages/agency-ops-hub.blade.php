<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Custom Domain (#21)') }}</h3>
                <p class="mt-2 text-lg font-bold text-teal-600">{{ $agency?->custom_domain ?? __('Not configured') }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $agency?->custom_domain_status ?? 'pending' }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Budget Reallocations (#20)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $reallocations->count() }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('CFO & Ops Audits (#27–#31)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $operationalAudits->count() }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Influencer Campaigns (S6)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $influencerCampaigns->count() }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="p-5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('Calculate Client Budget Runway') }}</h3>
                <form wire:submit="runRunwayAudit" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Client') }}</label>
                        <select wire:model="clientId" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Remaining balance') }}</label>
                            <input type="number" step="0.01" wire:model="remainingBalance" dir="ltr" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Daily burn rate') }}</label>
                            <input type="number" step="0.01" wire:model="dailyBurnRate" dir="ltr" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-xs font-semibold hover:bg-teal-500">
                        {{ __('Audit budget runway') }}
                    </button>
                </form>

                @if($operationalAudits->isNotEmpty())
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2">
                        @foreach($operationalAudits->take(3) as $audit)
                            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 text-xs flex justify-between items-center">
                                <span class="font-semibold">{{ $audit->audit_type }}</span>
                                <span class="text-teal-600 font-bold">{{ $audit->primary_metric }} ({{ $audit->status }})</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="p-5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('Attribute Influencer Promo Campaign') }}</h3>
                <form wire:submit="recordInfluencer" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Creator name') }}</label>
                            <input type="text" wire:model="influencerName" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Promo code') }}</label>
                            <input type="text" wire:model="promoCode" dir="ltr" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Fixed fee') }}</label>
                            <input type="number" step="0.01" wire:model="fixedFee" dir="ltr" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Orders') }}</label>
                            <input type="number" wire:model="ordersCount" dir="ltr" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Revenue') }}</label>
                            <input type="number" step="0.01" wire:model="attributedRevenue" dir="ltr" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-xs font-semibold hover:bg-teal-500">
                        {{ __('Record creator ROI') }}
                    </button>
                </form>

                @if($influencerCampaigns->isNotEmpty())
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2">
                        @foreach($influencerCampaigns->take(3) as $ic)
                            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 text-xs flex justify-between items-center">
                                <span class="font-semibold">{{ $ic->influencer_name }} ({{ $ic->promo_code }})</span>
                                <span class="text-teal-600 font-bold">ROAS {{ $ic->effective_roas }}x</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

        {{-- #105 Client Onboarding Links & #77/#115 Outbound Webhooks / Looker & Sheets Feed --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-top:16px">
            <div style="background:rgba(15,23,42,.04);border:1px solid rgba(148,163,184,.28);border-radius:12px;padding:16px">
                <h3 style="margin:0 0 8px;font-size:15px;font-weight:700">{{ __("Client Onboarding & Access Link") }}</h3>
                <form wire:submit="createOnboardingLink" style="display:grid;gap:8px;margin-bottom:12px">
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
                    </div>
                @endforeach
            </div>

            <div style="background:rgba(15,23,42,.04);border:1px solid rgba(148,163,184,.28);border-radius:12px;padding:16px">
                <h3 style="margin:0 0 8px;font-size:15px;font-weight:700">{{ __("Webhooks & Looker Studio / Sheets Feed") }}</h3>
                <form wire:submit="createWebhook" style="display:grid;gap:8px;margin-bottom:12px">
                    <input type="text" wire:model="webhookName" placeholder="{{ __("Integration Name (e.g. Zapier / Looker Feed)") }}"
                           style="padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                    <input type="url" wire:model="webhookTargetUrl" placeholder="https://hooks.zapier.com/..."
                           style="padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                    <button type="submit" style="padding:8px 14px;border-radius:8px;background:#0F766E;color:#fff;border:0;font-weight:600;cursor:pointer">
                        {{ __("Create Webhook & Data Feed") }}
                    </button>
                </form>
                @foreach ($webhooks as $wh)
                    <div style="font-size:12px;padding:6px 0;border-top:1px solid rgba(148,163,184,.2)">
                        <strong>{{ $wh->name }}</strong> —
                        <code>/api/v1/export/{{ $wh->secret_token }}</code>
                    </div>
                @endforeach
            </div>
        </div>
</x-filament-panels::page>
