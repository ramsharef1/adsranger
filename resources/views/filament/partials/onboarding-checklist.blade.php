@php($list = \App\Support\Onboarding::checklist(\Filament\Facades\Filament::getTenant(), auth()->user()))
@if ($list)
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;align-items:center">
            <strong>{{ __('onboarding.checklist.title') }} · {{ __('onboarding.checklist.count', ['done' => $list['done'], 'total' => $list['total']]) }}</strong>
            <x-filament::button size="sm" color="gray" wire:click="hideOnboardingChecklist">{{ __('onboarding.checklist.hide') }}</x-filament::button>
        </div>
        <ul style="list-style:none;padding:0;margin:10px 0 0;display:grid;gap:4px">
            @foreach ($list['items'] as $item)
                <li>
                    <a href="{{ $item['url'] }}" style="display:inline-flex;align-items:center;gap:8px;min-height:44px;{{ $item['done'] ? 'opacity:.6;text-decoration:line-through' : 'font-weight:600' }}">
                        <span aria-hidden="true">{{ $item['done'] ? '✓' : '○' }}</span>{{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
@endif
@php($tenant = \Filament\Facades\Filament::getTenant())
@if ($tenant && ! $tenant->connections()->exists() && ! $tenant->dataImports()->exists() && ! $tenant->adAccounts()->exists())
    <div role="status" style="border:1px dashed rgba(148,163,184,.45);border-radius:12px;padding:12px 14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
        <span>{{ __('onboarding.desk_empty') }}</span>
        <x-filament::button size="sm" tag="a" :href="\App\Filament\Pages\Networks::getUrl(tenant: $tenant)">{{ __('onboarding.desk_empty_link') }}</x-filament::button>
    </div>
@endif
