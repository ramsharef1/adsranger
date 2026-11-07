@php
    // I-X-1 (D-98): always know where you are. UX-82 workspace + role (EX-47) · UX-81 current client chip (EX-08,
    // ?client= on any page, kept per workspace) · UX-83 data freshness (EX-23, oldest linked account, amber > 24 h)
    // · #71 "View as" role / member preview switcher for owners & admins (UX-97: read-only preview + banner).
    use App\Support\CurrentClient;
    use App\Support\TeamRoles;
    use App\Support\ViewAs;
    $agency = \Filament\Facades\Filament::getTenant();
    $user = auth()->user();
@endphp
@if ($agency && $user)
    @php
        $viewAs = ViewAs::current($user, $agency);
        $canViewAs = ViewAs::canUse($user, $agency);
        $role = $user->roleIn($agency);
        $clients = \App\Models\Client::visibleTo($user, $agency)->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $current = CurrentClient::id($agency);
        $fresh = CurrentClient::freshness($agency);
        $amber = 'background:rgba(245,158,11,.16);border-color:rgba(245,158,11,.55)';
        $chip = 'display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:2px 10px;border-radius:99px;border:1px solid rgba(148,163,184,.35);font-size:13px';
        $teamMembers = $canViewAs ? $agency->members()->where('users.id', '!=', $user->id)->orderBy('users.name')->get(['users.id', 'users.name']) : collect();
    @endphp
    @if ($viewAs)
        <div data-view-as-banner role="status"
             style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px 14px;padding:8px 16px;background:rgba(245,158,11,.16);border-bottom:1px solid rgba(245,158,11,.55);font-size:13px">
            <span>
                <strong>{{ __('Viewing as :target', ['target' => $viewAs['label']]) }}</strong>
                · {{ __('Preview mode is read-only') }}
            </span>
            <a href="?view_as=off" style="font-weight:700;text-decoration:underline;min-height:32px;display:inline-flex;align-items:center">{{ __('Exit preview') }}</a>
        </div>
    @endif
    <div data-context-bar role="region" aria-label="{{ __('Where you are') }}"
         style="display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;padding:6px 16px;border-bottom:1px solid rgba(148,163,184,.25);font-size:13px">
        <span style="{{ $chip }}" data-context-workspace>
            <x-filament::icon icon="heroicon-o-building-office-2" style="width:16px;height:16px;opacity:.7" />
            <strong>{{ $agency->name }}</strong>
            @if ($role)
                <x-filament::badge :color="$role === 'analyst' ? 'gray' : 'primary'" size="sm">{{ __(TeamRoles::label($role)) }}</x-filament::badge>
            @endif
        </span>

        <label style="{{ $chip }}{{ $current ? ';border-color:#0F766E' : '' }}" data-context-client>
            <x-filament::icon icon="heroicon-o-user-circle" style="width:16px;height:16px;opacity:.7" />
            <span>{{ __('Client') }}:</span>
            <select aria-label="{{ __('Current client') }}"
                    onchange="(function (v) { var u = new URL(window.location.href); u.searchParams.set('client', v); window.location.href = u.toString(); })(this.value)"
                    style="min-height:32px;border:0;background:transparent;font:inherit;font-weight:600;max-width:220px;cursor:pointer">
                <option value="all" @selected(! $current)>{{ __('All clients') }}</option>
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}" @selected($current === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </label>

        <span style="{{ $chip }}{{ $fresh['stale'] && ! $fresh['none'] ? ';'.$amber : '' }}" data-context-freshness
              @if ($fresh['oldest']) title="{{ __('Oldest account: :account', ['account' => $fresh['oldest']]) }}" @endif>
            <x-filament::icon :icon="$fresh['stale'] && ! $fresh['none'] ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-arrow-path'" style="width:16px;height:16px;opacity:.75" />
            @if ($fresh['none'])
                {{ __('No linked ad accounts yet') }}
            @elseif ($fresh['at'])
                {{ __('Data updated :when', ['when' => $fresh['at']->locale(app()->getLocale())->diffForHumans()]) }}
            @else
                {{ __('An account has not synced yet') }}
            @endif
        </span>

        @if ($canViewAs)
            <label style="{{ $chip }}{{ $viewAs ? ';'.$amber : '' }}" data-context-view-as>
                <x-filament::icon icon="heroicon-o-eye" style="width:16px;height:16px;opacity:.75" />
                <span>{{ __('View as') }}:</span>
                <select aria-label="{{ __('View as') }}"
                        onchange="(function (v) { var u = new URL(window.location.href); u.searchParams.set('view_as', v); window.location.href = u.toString(); })(this.value)"
                        style="min-height:32px;border:0;background:transparent;font:inherit;font-weight:600;max-width:210px;cursor:pointer">
                    <option value="off" @selected(! $viewAs)>{{ __('Myself') }}</option>
                    @foreach (['admin', 'manager', 'analyst'] as $rOpt)
                        @if (ViewAs::mayPreviewRole($user, $agency, $rOpt))
                            <option value="r:{{ $rOpt }}" @selected(($viewAs['target'] ?? '') === 'r:'.$rOpt)>{{ __(TeamRoles::label($rOpt)) }}</option>
                        @endif
                    @endforeach
                    @foreach ($teamMembers as $m)
                        @if (ViewAs::mayPreviewRole($user, $agency, $m->pivot?->role ?: 'analyst'))
                            <option value="u:{{ $m->id }}" @selected(($viewAs['target'] ?? '') === 'u:'.$m->id)>{{ $m->name }} ({{ __(TeamRoles::label($m->pivot?->role)) }})</option>
                        @endif
                    @endforeach
                </select>
                @if ($viewAs)
                    <a href="?view_as=off" style="font-weight:600;text-decoration:underline;margin-inline-start:4px">{{ __('Exit preview') }}</a>
                @endif
            </label>
        @endif
    </div>
@endif
