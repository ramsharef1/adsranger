<x-filament-panels::page>
    <div style="max-width:720px">
            <div style="background:rgba(15,23,42,.04);border:1px solid rgba(148,163,184,.28);border-radius:12px;padding:16px">
                <h3 style="margin:0 0 8px;font-size:15px;font-weight:700">{{ __("Webhooks & Looker Studio / Sheets Feed") }}</h3>
                <p style="font-size:12px;opacity:.75;margin:0 0 8px">{{ __("Sends critical alerts and sent reports to a public https address, signed with its own secret. The feed URL gives read-only data: keep it private.") }}</p>
                @if ($canManageWebhooks)
                <form wire:submit="createWebhook" style="display:grid;gap:8px;margin-bottom:12px">
                    <select wire:model="clientId" style="min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                        <option value="">{{ __('All clients') }}</option>
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    <input type="text" wire:model="webhookName" maxlength="140" placeholder="{{ __("Integration Name (e.g. Zapier / Looker Feed)") }}"
                           style="min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                    <input type="url" wire:model="webhookTargetUrl" dir="ltr" placeholder="https://hooks.zapier.com/..."
                           style="min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent">
                    <button type="submit" style="min-height:44px;padding:8px 14px;border-radius:8px;background:#0F766E;color:#fff;border:0;font-weight:600;cursor:pointer">
                        {{ __("Create Webhook & Data Feed") }}
                    </button>
                </form>
                @endif
                @foreach ($webhooks as $wh)
                    <div style="font-size:12px;padding:6px 0;border-top:1px solid rgba(148,163,184,.2);display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap">
                        <span>
                            <strong>{{ $wh->name }}</strong> — <bdi dir="ltr">{{ parse_url($wh->target_url, PHP_URL_HOST) }}</bdi>
                            · {{ $wh->is_active ? __('On') : __('Off') }}
                            @if ($wh->last_status) · <bdi dir="ltr">HTTP {{ $wh->last_status }}</bdi>@endif
                            @if ($canManageWebhooks && $wh->is_active && $wh->feedUrl()) <br><code dir="ltr">{{ $wh->feedUrl() }}</code>@endif
                        </span>
                        @if ($canManageWebhooks && $wh->is_active)
                            <button type="button" wire:click="deactivateWebhook({{ $wh->id }})" style="min-height:44px;padding:6px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent;cursor:pointer">{{ __('Switch off') }}</button>
                        @endif
                    </div>
                @endforeach
            </div>
    </div>
</x-filament-panels::page>
