@if (\App\Support\AdminShell::on())
    @php
        $r = \App\Support\AdminShell::ribbon();
        $w = fn ($v): string => (string) \App\Support\TextClean::western((string) $v);
        $enabledCount = count($r['modules_enabled']);
        $enabledPreviewList = $r['preview_agencies'] !== [] ? implode(', ', array_slice($r['preview_agencies'], 0, 3)) : null;
        $enabledModulesLabel = $enabledCount > 0 ? implode(', ', array_slice($r['modules_enabled'], 0, 3)).($enabledCount > 3 ? ' +'.$w($enabledCount - 3) : '') : null;
        $dotClass = match ($r['ops_state']) {
            'warn' => 'ar-adm-dot ar-adm-dot--warn',
            'pending' => 'ar-adm-dot ar-adm-dot--pending',
            default => 'ar-adm-dot',
        };
    @endphp
    {{-- E2-068: ground-truth live telemetry ribbon for /admin. --}}
    <div class="ar-adm-ribbon" data-admin-shell-ribbon role="region" aria-label="{{ __('Live platform telemetry') }}">
        <a class="ar-adm-pill" href="{{ url('/admin/agencies') }}" data-telemetry="workspaces">
            <span class="ar-adm-dot" aria-hidden="true"></span>
            <span>{{ __('Active workspaces') }}: <strong><bdi dir="ltr">{{ $w($r['workspaces_active']) }}/{{ $w($r['workspaces_total']) }}</bdi></strong></span>
        </a>

        <a class="ar-adm-pill" href="{{ url('/admin/platform-settings') }}" data-telemetry="modules">
            <span>{{ __('Global modules on') }}: <strong><bdi dir="ltr">{{ $w($enabledCount) }}/{{ $w($r['modules_total']) }}</bdi></strong>@if ($enabledModulesLabel) (<bdi dir="ltr">{{ $enabledModulesLabel }}</bdi>)@endif</span>
            <span>· {{ __('Preview') }}: @if ($enabledPreviewList)<strong><bdi dir="ltr">{{ $enabledPreviewList }}</bdi></strong>@else{{ __('none') }}@endif</span>
        </a>

        <a class="ar-adm-pill" href="{{ url('/admin/operations') }}" data-telemetry="heartbeat">
            <span class="{{ $dotClass }}" aria-hidden="true"></span>
            <span>
                {{ __('Scheduler & queue') }}:
                @if ($r['ops_state'] === 'ok')
                    <strong>{{ __('OK') }}</strong>
                @elseif ($r['ops_state'] === 'pending')
                    <strong>{{ __('No beat yet') }}</strong>
                @else
                    <strong>{{ __('Check needed') }}</strong>
                @endif
                · <bdi dir="ltr">{{ $r['queue_driver'] }}</bdi>
                @if ($r['failed_jobs_24h'] > 0)
                    · {{ __('Failed 24h') }}: <bdi dir="ltr">{{ $w($r['failed_jobs_24h']) }}</bdi>
                @endif
            </span>
        </a>

        <a class="ar-adm-pill" href="{{ url('/admin/reach') }}" data-telemetry="reach-cache">
            <span>{{ \App\Support\Seo\ToolCopy::html(__('Reach cache store'), app()->getLocale() === 'ar') }}: <strong><bdi dir="ltr">{{ $r['reach_cache_store'] }}</bdi></strong></span>
        </a>

        <a class="ar-adm-jump" href="{{ $r['app_url'] }}" data-admin-jump-app>
            <span>{{ __('Agency app') }}</span>
            <span aria-hidden="true">↗</span>
        </a>
    </div>
@endif
