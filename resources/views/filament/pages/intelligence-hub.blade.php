<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-500">{{ __('Blended ROAS & MER (#18)') }}</h3>
                <p class="mt-2 text-2xl font-bold text-teal-600">{{ $blendedMetrics->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Cross-channel MER snapshots') }}</p>
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
    </div>
</x-filament-panels::page>