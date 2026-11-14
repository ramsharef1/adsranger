@php
    use App\Support\ResultResolver;
    $names = $clients->pluck('name', 'id');
    $curOf = $clients->pluck('currency', 'id');
    $latest = $blendedMetrics->first();
    $groupLabel = ['urgency' => __('Urgency'), 'fomo' => __('Scarcity'), 'authority' => __('Trust'), 'aspiration' => __('Aspiration')];
    $intentLabel = ['transactional' => __('Ready to buy'), 'commercial' => __('Comparing'), 'informational' => __('Learning')];
    $extras = array_filter([__('Competitor ad notes') => $trendRadars->count(), __('Page funnel notes') => $uxHeatmaps->count(), __('Network setup events') => $accelerators->count()]);
@endphp
<x-filament-panels::page>
    @include('filament.pages.partials.hub-styles')
    <div class="hub space-y-6" data-intelligence-hub>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl bg-white border">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Return across all channels') }}</h3>
                @if ($latest && (float) $latest->total_spend > 0)
                    <p class="mt-2 text-2xl font-bold text-teal-600"><bdi dir="ltr">{{ number_format((float) $latest->marketing_efficiency_ratio, 2) }}×</bdi></p>
                    <p class="hub-note mt-1"><bdi>{{ $names[$latest->client_id] ?? '' }}</bdi> · <bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($latest->metric_date)->toDateString() }}</bdi> · {{ __('spend') }} <bdi dir="ltr">{{ ResultResolver::money((float) $latest->total_spend, $curOf[$latest->client_id] ?? null) }}</bdi> · {{ __('revenue') }} <bdi dir="ltr">{{ ResultResolver::money((float) $latest->total_revenue, $curOf[$latest->client_id] ?? null) }}</bdi></p>
                @else
                    <p class="mt-2 text-sm" data-no-snapshot>{{ __('No snapshot with spend yet') }}</p>
                    <p class="hub-note mt-1">{{ __('Revenue ÷ spend for one day, every channel together.') }}</p>
                @endif
            </div>
            <div class="p-4 rounded-xl bg-white border">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Starter keyword lists') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $keywordMaps->count() }}</p>
                <p class="hub-note mt-1">{{ __('Phrase templates, not search volumes') }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white border">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Ad text word checks') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $hookScores->count() }}</p>
                <p class="hub-note mt-1">{{ __('Which persuasion words a text uses') }}</p>
            </div>
        </div>
        @if ($extras)
            <p class="hub-note">{{ __('Also saved for this scope:') }} @foreach ($extras as $label => $n){{ $label }} {{ $n }}@if (! $loop->last) · @endif @endforeach</p>
        @endif

        @unless ($canManageHub)
            <p class="p-3 rounded-lg bg-gray-50 border text-sm" data-hub-view-only>{{ __('You can view this page. Saving lists, checks and snapshots needs a role that can manage data.') }}</p>
        @endunless

        <section class="p-5 rounded-xl bg-white border space-y-4" aria-labelledby="ih-summary">
            <div>
                <h3 id="ih-summary" class="text-base font-bold text-gray-900">{{ __('Summary from saved numbers') }}</h3>
                <p class="hub-note mt-1">{{ __('Reads the latest saved snapshot and the open alerts for the chosen client. No AI model is used here, and nothing is filled in when a number is missing.') }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3" style="align-items:end">
                <div class="md:col-span-2">
                    <label for="ih-client" class="block text-xs font-medium">{{ __('Client') }}</label>
                    <select id="ih-client" wire:model="clientId" class="mt-1 w-full">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="hub-btn" wire:click="askPlatform">{{ __('Show the summary') }}</button>
                <button type="button" class="hub-btn" wire:click="generateWeeklyReview" style="background:transparent;color:var(--h-ac);border:1px solid var(--h-ac)">{{ __('Top 3 open alerts') }}</button>
            </div>
            @if ($canManageHub)
                <div><button type="button" class="hub-btn" wire:click="computeBlendedRoas" style="background:transparent;color:var(--h-ac);border:1px solid var(--h-ac)">{{ __('Update snapshot for yesterday') }}</button></div>
            @endif
            @if ($this->platformAnswer)
                <div data-ai-platform-answer class="p-3 rounded-lg bg-teal-50 text-sm">{{ $this->platformAnswer }}</div>
            @endif
            @if (! empty($this->weeklyActions))
                <ul data-weekly-ai-actions class="text-sm" style="margin:0;padding-inline-start:18px">
                    @foreach ($this->weeklyActions as $act)
                        <li style="margin-bottom:4px">{{ $act }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <section class="p-5 rounded-xl bg-white border space-y-4" aria-labelledby="ih-keywords">
                <div>
                    <h3 id="ih-keywords" class="text-base font-bold text-gray-900">{{ __('Starter keyword list') }}</h3>
                    <p class="hub-note mt-1">{{ __('Type a service and get the usual phrase patterns grouped by intent, with a few common negatives. It is a starting template: check volumes in the ad platform before use.') }}</p>
                </div>
                @if ($canManageHub)
                    <form wire:submit="generateKeywords" class="space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-2">
                                <label for="ih-seed" class="block text-xs font-medium">{{ __('Core service or product') }}</label>
                                <input id="ih-seed" type="text" maxlength="80" wire:model="seedKeyword" class="mt-1 w-full" placeholder="{{ __('e.g. Dental Implants') }}" />
                                @error('seedKeyword')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="ih-locale" class="block text-xs font-medium">{{ __('Language') }}</label>
                                <select id="ih-locale" wire:model="keywordLocale" class="mt-1 w-full">
                                    <option value="ar">{{ __('Arabic') }}</option>
                                    <option value="en">{{ __('English') }}</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit">{{ __('Build the list') }}</button>
                    </form>
                @endif
                @forelse ($keywordMaps->take(3) as $km)
                    <details class="p-3 rounded-lg bg-gray-50 text-sm">
                        <summary style="cursor:pointer"><span class="font-semibold"><bdi>{{ $km->target_service }}</bdi></span> · <bdi>{{ $names[$km->client_id] ?? '' }}</bdi> · <span class="hub-note">{{ $km->locale === 'ar' ? __('Arabic') : __('English') }}</span></summary>
                        @foreach ((array) $km->intent_buckets as $intent => $words)
                            <p class="mt-2"><span class="hub-chip">{{ $intentLabel[$intent] ?? $intent }}</span> <bdi>{{ implode(' · ', (array) $words) }}</bdi></p>
                        @endforeach
                        <p class="mt-2"><span class="hub-chip hub-warn">{{ __('Negatives') }}</span> <bdi>{{ implode(' · ', (array) $km->negative_keywords) }}</bdi></p>
                    </details>
                @empty
                    <p class="hub-note">{{ __('No keyword lists yet.') }}</p>
                @endforelse
            </section>

            <section class="p-5 rounded-xl bg-white border space-y-4" aria-labelledby="ih-words">
                <div>
                    <h3 id="ih-words" class="text-base font-bold text-gray-900">{{ __('Ad text word check') }}</h3>
                    <p class="hub-note mt-1">{{ __('Shows which word groups a headline uses: urgency, scarcity, trust and aspiration. It counts words from a fixed list. It does not predict how the ad will perform.') }}</p>
                </div>
                @if ($canManageHub)
                    <form wire:submit="scoreHook" class="space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-2">
                                <label for="ih-copy" class="block text-xs font-medium">{{ __('Ad hook or headline') }}</label>
                                <input id="ih-copy" type="text" maxlength="300" wire:model="adCopySample" class="mt-1 w-full" />
                                @error('adCopySample')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="ih-dialect" class="block text-xs font-medium">{{ __('Dialect') }}</label>
                                <select id="ih-dialect" wire:model="hookDialect" class="mt-1 w-full">
                                    <option value="gulf">{{ __('Gulf') }}</option>
                                    <option value="egyptian">{{ __('Egyptian') }}</option>
                                    <option value="levantine">{{ __('Levantine') }}</option>
                                    <option value="msa">{{ __('MSA') }}</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit">{{ __('Check the words') }}</button>
                    </form>
                @endif
                @forelse ($hookScores->take(3) as $hs)
                    <div class="p-3 rounded-lg bg-gray-50 text-sm">
                        <p><bdi>{{ $hs->original_copy }}</bdi></p>
                        <p class="mt-2">
                            @foreach ((array) $hs->trigger_scores as $group => $score)
                                <span class="hub-chip {{ (int) $score > 0 ? 'hub-ok' : '' }}">{{ $groupLabel[$group] ?? $group }}: {{ (int) $score > 0 ? __('found') : __('not found') }}</span>
                            @endforeach
                        </p>
                    </div>
                @empty
                    <p class="hub-note">{{ __('No word checks yet.') }}</p>
                @endforelse
            </section>
        </div>
    </div>
</x-filament-panels::page>
