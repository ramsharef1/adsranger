@php
    use App\Filament\Pages\AgencyOpsHubPage as P;
    use App\Support\ResultResolver;
    $names = $clients->pluck('name', 'id');
    $curOf = $clients->pluck('currency', 'id');
@endphp
<x-filament-panels::page>
    @include('filament.pages.partials.hub-styles')
    <div class="hub space-y-6" data-agency-ops>
        {{-- What this page holds, at a glance. Counts are for the client picked in the top bar, or all clients. --}}
        <div class="grid grid-cols-1 {{ $showDomain ? 'md:grid-cols-4' : 'md:grid-cols-3' }} gap-4">
            <div class="p-4 rounded-xl bg-white border">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Budget checks') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $operationalAudits->count() }}</p>
                <p class="hub-note mt-1">{{ __('Last 10 shown below') }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white border">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Creator campaigns') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $influencerCampaigns->count() }}</p>
                <p class="hub-note mt-1">{{ __('Fee, commission, orders and revenue you typed') }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white border">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Pitch decks') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $pitchDecks->count() }}</p>
                <p class="hub-note mt-1">{{ __('For new prospects of the agency') }}</p>
            </div>
            @if ($showDomain)
                <div class="p-4 rounded-xl bg-white border">
                    <h3 class="text-sm font-semibold text-gray-500">{{ __('Custom report domain') }}</h3>
                    <p class="mt-2 text-lg font-bold text-teal-600 truncate"><bdi dir="ltr">{{ $agency?->custom_domain ?? __('Not configured') }}</bdi></p>
                </div>
            @endif
        </div>

        @unless ($canManageOps)
            <p class="p-3 rounded-lg bg-gray-50 border text-sm" data-ops-view-only>{{ __('You can view this page. Adding checks, creator campaigns and pitch decks needs a role that can manage data.') }}</p>
        @endunless

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <section class="p-5 rounded-xl bg-white border space-y-4" aria-labelledby="ops-runway">
                <div>
                    <h3 id="ops-runway" class="text-base font-bold text-gray-900">{{ __('How many days of budget are left?') }}</h3>
                    <p class="hub-note mt-1">{{ __('Type the balance left and what is spent per day. The answer is balance ÷ daily spend, from your numbers only.') }}</p>
                </div>
                @if ($canManageOps)
                    <form wire:submit="runRunwayAudit" class="space-y-3">
                        <div>
                            <label for="ops-client" class="block text-xs font-medium">{{ __('Client') }}</label>
                            <select id="ops-client" wire:model="clientId" class="mt-1 w-full">
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="ops-balance" class="block text-xs font-medium">{{ __('Remaining balance') }}</label>
                                <input id="ops-balance" type="number" step="0.001" min="0" inputmode="decimal" wire:model="remainingBalance" dir="ltr" class="mt-1 w-full" />
                                @error('remainingBalance')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="ops-burn" class="block text-xs font-medium">{{ __('Daily burn rate') }}</label>
                                <input id="ops-burn" type="number" step="0.001" min="0" inputmode="decimal" wire:model="dailyBurnRate" dir="ltr" class="mt-1 w-full" />
                                @error('dailyBurnRate')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <button type="submit">{{ __('Audit budget runway') }}</button>
                    </form>
                @endif

                @if ($operationalAudits->isNotEmpty())
                    <div class="pt-3 border-t space-y-2">
                        @foreach ($operationalAudits as $audit)
                            @php [$label, $tone] = P::statusLabel((string) $audit->status); @endphp
                            <div class="p-3 rounded-lg bg-gray-50 text-sm flex justify-between items-center gap-3">
                                <span><span class="font-semibold">{{ P::auditLabel((string) $audit->audit_type) }}</span> · <bdi>{{ $names[$audit->client_id] ?? __('Agency') }}</bdi><br><span class="hub-note">{{ $audit->created_at?->diffForHumans() }}</span></span>
                                <span style="text-align:end">
                                    @if ($audit->audit_type === 'budget_runway')
                                        <span class="font-bold">{{ trans_choice(':n day left|:n days left', (int) round((float) $audit->primary_metric), ['n' => rtrim(rtrim(number_format((float) $audit->primary_metric, 1), '0'), '.')]) }}</span><br>
                                    @endif
                                    <span class="hub-chip hub-{{ $tone }}">{{ $label }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="hub-note">{{ __('No budget checks yet.') }}</p>
                @endif
            </section>

            <section class="p-5 rounded-xl bg-white border space-y-4" aria-labelledby="ops-creator">
                <div>
                    <h3 id="ops-creator" class="text-base font-bold text-gray-900">{{ __('Did a creator campaign pay off?') }}</h3>
                    <p class="hub-note mt-1">{{ __('Type what the creator cost and what the promo code sold. Return = revenue ÷ (fixed fee + commission). Nothing is pulled from a store here.') }}</p>
                </div>
                @if ($canManageOps)
                    <form wire:submit="recordInfluencer" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="ops-creator-name" class="block text-xs font-medium">{{ __('Creator name') }}</label>
                                <input id="ops-creator-name" type="text" maxlength="120" wire:model="influencerName" class="mt-1 w-full" />
                                @error('influencerName')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="ops-platform" class="block text-xs font-medium">{{ __('Platform') }}</label>
                                <select id="ops-platform" wire:model="influencerPlatform" class="mt-1 w-full">
                                    @foreach ($platforms as $key => $name)
                                        <option value="{{ $key }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="ops-code" class="block text-xs font-medium">{{ __('Promo code') }}</label>
                                <input id="ops-code" type="text" maxlength="48" wire:model="promoCode" dir="ltr" class="mt-1 w-full" />
                                @error('promoCode')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="ops-fee" class="block text-xs font-medium">{{ __('Fixed fee') }}</label>
                                <input id="ops-fee" type="number" step="0.001" min="0" inputmode="decimal" wire:model="fixedFee" dir="ltr" class="mt-1 w-full" />
                                @error('fixedFee')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label for="ops-commission" class="block text-xs font-medium">{{ __('Commission %') }}</label>
                                <input id="ops-commission" type="number" step="0.01" min="0" max="100" inputmode="decimal" wire:model="commissionPercent" dir="ltr" class="mt-1 w-full" />
                                @error('commissionPercent')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="ops-orders" class="block text-xs font-medium">{{ __('Orders') }}</label>
                                <input id="ops-orders" type="number" step="1" min="0" inputmode="numeric" wire:model="ordersCount" dir="ltr" class="mt-1 w-full" />
                                @error('ordersCount')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="ops-revenue" class="block text-xs font-medium">{{ __('Revenue') }}</label>
                                <input id="ops-revenue" type="number" step="0.001" min="0" inputmode="decimal" wire:model="attributedRevenue" dir="ltr" class="mt-1 w-full" />
                                @error('attributedRevenue')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <p class="hub-note">{{ __('Saved for the client chosen in the budget form.') }}</p>
                        <button type="submit">{{ __('Record creator ROI') }}</button>
                    </form>
                @endif

                @if ($influencerCampaigns->isNotEmpty())
                    <div class="pt-3 border-t space-y-2">
                        @foreach ($influencerCampaigns as $ic)
                            @php $cur = $curOf[$ic->client_id] ?? null; @endphp
                            <div class="p-3 rounded-lg bg-gray-50 text-sm flex justify-between items-center gap-3">
                                <span><span class="font-semibold"><bdi>{{ $ic->influencer_name }}</bdi></span> · <bdi dir="ltr">{{ $ic->promo_code }}</bdi><br><span class="hub-note"><bdi>{{ $names[$ic->client_id] ?? '' }}</bdi> · {{ $platforms[$ic->platform] ?? $ic->platform }} · {{ trans_choice(':n order|:n orders', (int) $ic->orders_count, ['n' => number_format((int) $ic->orders_count)]) }}</span></span>
                                <span style="text-align:end">
                                    <span class="font-bold text-teal-600"><bdi dir="ltr">{{ number_format((float) $ic->effective_roas, 2) }}×</bdi> {{ __('return') }}</span><br>
                                    <span class="hub-note">{{ __('Paid') }} <bdi dir="ltr">{{ ResultResolver::money((float) $ic->total_payout, $cur) }}</bdi> · {{ __('per order') }} <bdi dir="ltr">{{ ResultResolver::money((float) $ic->cac, $cur) }}</bdi></span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="hub-note">{{ __('No creator campaigns yet.') }}</p>
                @endif
            </section>
        </div>

        <section class="p-5 rounded-xl bg-white border space-y-4" aria-labelledby="ops-pitch">
            <div>
                <h3 id="ops-pitch" class="text-base font-bold text-gray-900">{{ __('Pitch deck for a new prospect') }}</h3>
                <p class="hub-note mt-1">{{ __('We read the prospect\'s public home page once, list what we find, and add three price tiers you can edit. The link is private and not indexed.') }}</p>
            </div>
            @if ($canManageOps)
                <form wire:submit="createPitchDeck" class="grid grid-cols-1 md:grid-cols-4 gap-3" style="align-items:end">
                    <div>
                        <label for="ops-prospect" class="block text-xs font-medium">{{ __('Prospect brand name') }}</label>
                        <input id="ops-prospect" type="text" maxlength="120" wire:model="prospectName" class="mt-1 w-full" />
                    </div>
                    <div>
                        <label for="ops-prospect-url" class="block text-xs font-medium">{{ __('Website') }}</label>
                        <input id="ops-prospect-url" type="url" wire:model="prospectUrl" dir="ltr" placeholder="https://" class="mt-1 w-full" />
                    </div>
                    <div>
                        <label for="ops-prospect-cur" class="block text-xs font-medium">{{ __('Currency') }}</label>
                        <select id="ops-prospect-cur" wire:model="prospectCurrency" dir="ltr" class="mt-1 w-full">
                            @foreach (['SAR', 'AED', 'KWD', 'JOD', 'USD'] as $cur)
                                <option value="{{ $cur }}">{{ $cur }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit">{{ __('Build pitch deck') }}</button>
                </form>
            @endif
            @if ($pitchDecks->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($pitchDecks as $pd)
                        <div class="p-3 rounded-lg bg-gray-50 text-sm flex justify-between items-center gap-3" style="flex-wrap:wrap">
                            <span><strong><bdi>{{ $pd->prospect_name }}</bdi></strong> · <bdi dir="ltr">{{ $pd->prospect_url }}</bdi><br><span class="hub-note">{{ $pd->created_at?->diffForHumans() }}</span></span>
                            <span class="flex gap-3 items-center" style="flex-wrap:wrap">
                                @if ($pd->revoked_at)
                                    <span class="hub-chip" data-deck-revoked>{{ __('Link switched off') }}</span>
                                @else
                                    <a href="{{ url('/pitch/'.$pd->share_token) }}" target="_blank" rel="noopener" class="font-semibold text-teal-600">{{ __('Open preview') }}</a>
                                    <a href="{{ url('/pitch/'.$pd->share_token.'/pdf') }}" class="font-semibold text-teal-600">PDF</a>
                                    @if ($canManageOps)
                                        <button type="button" class="hub-btn" wire:click="revokePitchDeck({{ $pd->id }})" wire:confirm="{{ __('Switch this link off? Anyone who has it will see a not-found page.') }}" style="background:transparent;color:var(--h-2);border:1px solid var(--h-control)">{{ __('Switch link off') }}</button>
                                    @endif
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="hub-note">{{ __('No pitch decks yet.') }}</p>
            @endif
        </section>
    </div>
</x-filament-panels::page>
