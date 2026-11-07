<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-500">{{ __('Blended ROAS & MER (#18)') }}</h3>
                    <p class="mt-2 text-2xl font-bold text-teal-600">{{ $blendedMetrics->count() }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Cross-channel MER snapshots') }}</p>
                </div>
                <button type="button" wire:click="computeBlendedRoas" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-teal-600 text-white hover:bg-teal-500">
                    {{ __('Compute MER') }}
                </button>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('AI Keyword Matrices (#16)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $keywordMaps->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Google & Microsoft Ads intent clusters') }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Psychological Hook Scores (#17)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $hookScores->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Dialect-adapted emotional trigger audits') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Competitor Trend Radars (#22)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $trendRadars->count() }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('UX Funnel Heatmaps (#19)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $uxHeatmaps->count() }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Network Accelerators (#23–#26)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $accelerators->count() }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="p-5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('Generate AI Keyword Intent Matrix') }}</h3>
                <form wire:submit="generateKeywords" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Client') }}</label>
                        <select wire:model="clientId" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Core service or product') }}</label>
                            <input type="text" wire:model="seedKeyword" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" placeholder="{{ __('e.g. Dental Implants') }}" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Language') }}</label>
                            <select wire:model="keywordLocale" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                                <option value="ar">{{ __('Arabic') }}</option>
                                <option value="en">{{ __('English') }}</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-xs font-semibold hover:bg-teal-500">
                        {{ __('Generate keyword clusters') }}
                    </button>
                </form>

                @if($keywordMaps->isNotEmpty())
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2">
                        @foreach($keywordMaps->take(3) as $km)
                            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 text-xs flex justify-between items-center">
                                <span class="font-semibold">{{ $km->seed_keyword }}</span>
                                <span class="text-gray-500">{{ strtoupper($km->locale) }} · {{ count($km->negative_keywords ?? []) }} {{ __('negatives') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="p-5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('Score Ad Copy Psychological Triggers') }}</h3>
                <form wire:submit="scoreHook" class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Ad hook or headline') }}</label>
                            <input type="text" wire:model="adCopySample" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Dialect') }}</label>
                            <select wire:model="hookDialect" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                                <option value="gulf">{{ __('Gulf') }}</option>
                                <option value="egyptian">{{ __('Egyptian') }}</option>
                                <option value="levantine">{{ __('Levantine') }}</option>
                                <option value="msa">{{ __('MSA') }}</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-xs font-semibold hover:bg-teal-500">
                        {{ __('Analyze & score hook') }}
                    </button>
                </form>

                @if($hookScores->isNotEmpty())
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2">
                        @foreach($hookScores->take(3) as $hs)
                            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 text-xs flex justify-between items-center">
                                <span class="truncate max-w-xs">{{ $hs->original_copy }}</span>
                                <span class="font-bold text-teal-600">{{ $hs->overall_score }}/100</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

        {{-- #69 Ask the Platform (AR/EN) & #99 Weekly AI Account Review --}}
        <div style="background:rgba(15,23,42,.04);border:1px solid rgba(148,163,184,.28);border-radius:12px;padding:16px">
            <h3 style="margin:0 0 8px;font-size:15px;font-weight:700">{{ __("Ask the Platform & Weekly AI Review") }}</h3>
            <form wire:submit="askPlatform" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px">
                <input type="text" wire:model="platformQuestion" placeholder="{{ __("Ask in Arabic or English about CPA, MER, or creative fatigue...") }}"
                       style="flex:1;min-width:220px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                <button type="submit" style="padding:8px 14px;border-radius:8px;background:#0F766E;color:#fff;border:0;font-weight:600;cursor:pointer">
                    {{ __("Ask AI Analyst") }}
                </button>
                <button type="button" wire:click="generateWeeklyReview" style="padding:8px 14px;border-radius:8px;border:1px solid #0F766E;color:#0F766E;background:transparent;font-weight:600;cursor:pointer">
                    {{ __("Top 3 Weekly Actions") }}
                </button>
            </form>
            @if ($this->platformAnswer)
                <div data-ai-platform-answer style="padding:10px 12px;border-radius:8px;background:rgba(20,184,166,.12);margin-bottom:8px;font-size:13px">
                    {{ $this->platformAnswer }}
                </div>
            @endif
            @if (! empty($this->weeklyActions))
                <ul data-weekly-ai-actions style="margin:6px 0 0;padding-inline-start:18px;font-size:13px">
                    @foreach ($this->weeklyActions as $act)
                        <li style="margin-bottom:4px">{{ $act }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
</x-filament-panels::page>
