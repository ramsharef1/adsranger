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
    </div>
</x-filament-panels::page>