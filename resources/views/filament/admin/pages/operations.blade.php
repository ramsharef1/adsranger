@php
    $cell = 'padding:6px;text-align:start;vertical-align:top';
    $tz = 'Asia/Amman';
@endphp
<x-filament-panels::page>
    <x-filament::section :heading="__('Automatic launch checks')">
        <x-slot name="description">{{ __('Checked by the code each time this page opens. The hand-ticked rows stay on Costs & launch.') }}</x-slot>
        <div style="display:grid;gap:6px">
            @foreach ($checks as $c)
                <div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">
                    <span style="font-weight:600;{{ $c['ok'] ? 'color:#0d9488' : 'color:#dc2626' }}">{{ $c['ok'] ? __('OK') : __('Needs a look') }}</span>
                    <span>{{ $c['label'] }}</span>
                    @if ($c['detail'] !== '')<span style="opacity:.75;font-size:13px"><bdi>{{ $c['detail'] }}</bdi></span>@endif
                </div>
            @endforeach
        </div>
        <p style="margin-top:10px;font-size:13px;opacity:.75">{{ __('Queue lanes') }}: {{ $drivers['lanes'] ? __('on') : __('off') }} · {{ __('Cache') }}: <bdi dir="ltr">{{ $drivers['cache'] }}</bdi> · {{ __('Sessions') }}: <bdi dir="ltr">{{ $drivers['session'] }}</bdi></p>
    </x-filament::section>

    <x-filament::section :heading="__('App announcement')">
        <x-slot name="description">{{ __('One short line at the top of the app for every workspace. People can close it. A new line replaces the old one.') }}</x-slot>
        @if ($announcement)
            <p style="margin-bottom:10px"><strong>{{ __('Showing now') }}:</strong> <bdi>{{ $announcement->body_ar }}</bdi> · <bdi dir="ltr">{{ $announcement->body_en }}</bdi>
                · {{ __('until') }} <bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($announcement->ends_at)->timezone($tz)->format('Y-m-d H:i') }}</bdi>
                <x-filament::button size="xs" color="gray" wire:click="endAnnouncement">{{ __('End now') }}</x-filament::button></p>
        @endif
        <form wire:submit="postAnnouncement" style="display:grid;gap:8px;max-width:640px">
            <input wire:model="noteAr" class="fi-input" maxlength="240" dir="rtl" placeholder="{{ __('Arabic line') }}">
            @error('noteAr')<span style="color:#dc2626;font-size:13px">{{ $message }}</span>@enderror
            <input wire:model="noteEn" class="fi-input" maxlength="240" dir="ltr" placeholder="{{ __('English line') }}">
            @error('noteEn')<span style="color:#dc2626;font-size:13px">{{ $message }}</span>@enderror
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <select wire:model="noteTone" class="fi-input"><option value="info">{{ __('Information') }}</option><option value="warning">{{ __('Warning') }}</option></select>
                <label style="font-size:13px">{{ __('Days') }} <input type="number" min="1" max="30" wire:model="noteDays" class="fi-input" style="width:70px"></label>
                <x-filament::button type="submit" size="sm">{{ __('Show in the app') }}</x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section :heading="__('AI tools (all workspaces)')">
        <x-slot name="description">{{ __('Stop an AI tool for everyone at once, for bad output or provider trouble. Saved work stays.') }}</x-slot>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            @foreach ($aiTools as $tool => $t)
                <x-filament::button size="sm" :color="$t['off'] ? 'danger' : 'gray'" wire:click="toggleAi('{{ $tool }}')" data-ai-kill="{{ $tool }}">
                    {{ __($t['label']) }}: {{ $t['off'] ? __('stopped (restart)') : __('running (stop)') }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('Scheduled tasks')">
        <x-slot name="description">{{ __('Last finished run of each task. "Late" means a run that should have finished by now is missing.') }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:14px;border-collapse:collapse">
                <thead><tr>
                    <th style="{{ $cell }}">{{ __('Task') }}</th><th style="{{ $cell }}">{{ __('Schedule (UTC)') }}</th>
                    <th style="{{ $cell }}">{{ __('Last run') }}</th><th style="{{ $cell }}">{{ __('Time taken') }}</th><th style="{{ $cell }}">{{ __('Status') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($jobs as $j)
                        <tr style="border-top:1px solid rgba(127,127,127,.2)">
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $j['name'] }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $j['cron'] }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $j['last']?->timezone($tz)->format('Y-m-d H:i') ?? '—' }}</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $j['seconds'] !== null ? $j['seconds'].' s' : '—' }}</bdi></td>
                            <td style="{{ $cell }};{{ $j['missed'] || $j['status'] === 'failed' ? 'color:#dc2626;font-weight:600' : '' }}">
                                @if ($j['status'] === 'failed') {{ __('Failed') }}<br><span style="font-size:12px;font-weight:400"><bdi dir="ltr">{{ \Illuminate\Support\Str::limit($j['error'], 120) }}</bdi></span>
                                @elseif ($j['missed']) {{ __('Late') }}
                                @elseif ($j['last']) {{ __('OK') }}
                                @else {{ __('No run recorded yet') }} @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="margin-top:10px;font-size:13px;opacity:.75">{{ __('Scheduler heartbeat') }}: {{ $heartbeat['last'] ? __(':n minutes ago', ['n' => $heartbeat['age_minutes']]) : __('never') }} · <bdi dir="ltr">/up/heartbeat</bdi></p>
    </x-filament::section>

    <x-filament::section :heading="__('Live site check (nightly)')">
        <x-slot name="description">{{ __('Every page in the sitemap and every link in /llms.txt, read from the live site with the copy rules; plus the free tools run once on our home page.') }}</x-slot>
        @if (is_array($liveSite))
            <p style="font-size:13px;opacity:.75"><bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($liveSite['at'])->timezone($tz)->format('Y-m-d H:i') }}</bdi> · {{ __(':n pages', ['n' => $liveSite['pages']]) }} · {{ __(':n warnings', ['n' => $liveSite['warnings']]) }} · <bdi dir="ltr">{{ collect($liveSite['tools'] ?? [])->map(fn ($v, $k) => "$k: $v")->implode(' · ') }}</bdi></p>
            @forelse ($liveSite['errors'] as $e)
                <div style="color:#dc2626"><bdi>{{ $e }}</bdi></div>
            @empty
                <p style="color:#0d9488">{{ __('No errors.') }}</p>
            @endforelse
        @else
            <p style="opacity:.75">{{ __('Not run yet. It runs every night.') }}</p>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('Ad platform API deadlines')">
        <x-slot name="description">{{ __('From the networks register. A phone alert goes out 90, 30 and 7 days before each open one.') }}</x-slot>
        @foreach ($deadlines as $d)
            <div style="padding:4px 0;{{ $d['done'] ? 'opacity:.6' : '' }}">
                <span style="font-weight:600;{{ ! $d['done'] && $d['days'] <= 30 ? 'color:#dc2626' : '' }}">{{ $d['done'] ? __('Done') : __(':n days', ['n' => $d['days']]) }}</span>
                · <bdi dir="ltr">{{ $d['date'] }}</bdi> · <bdi>{{ $d['network'] }}</bdi>: <bdi dir="ltr">{{ $d['what'] }}</bdi> → <bdi dir="ltr">{{ $d['action'] }}</bdi>
            </div>
        @endforeach
    </x-filament::section>

    <x-filament::section :heading="__('Find an error by its reference')">
        <x-slot name="description">{{ __('Paste the reference shown on an error page. Searches the server log and the older log files.') }}</x-slot>
        <form wire:submit="lookup" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="text" wire:model="ref" dir="ltr" aria-label="{{ __('Reference') }}" placeholder="AB12CD34" style="min-height:44px;border-radius:8px;padding:6px 10px;border:1px solid rgba(127,127,127,.4);background:transparent">
            <x-filament::button type="submit" style="min-height:44px">{{ __('Find') }}</x-filament::button>
        </form>
        @if (is_array($found))
            @forelse ($found as $f)
                <pre dir="ltr" style="margin-top:12px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;padding:8px;border-radius:8px;background:rgba(127,127,127,.1)">{{ $f['file'] }}
{{ implode("\n", $f['lines']) }}</pre>
            @empty
                <p style="margin-top:12px;opacity:.75">{{ __('No log entry has this reference.') }}</p>
            @endforelse
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('What we keep and for how long')">
        <div style="display:grid;gap:4px">
            @foreach ($retention as $r)
                <div><span>{{ $r['what'] }}</span> · <span style="opacity:.75">{{ $r['keep'] }}</span></div>
            @endforeach
        </div>
        @if (is_array($lastPrune))
            <p style="margin-top:10px;font-size:13px;opacity:.75">{{ __('Last clean-up') }}: <bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($lastPrune['at'])->timezone($tz)->format('Y-m-d H:i') }}</bdi> · <bdi dir="ltr">{{ collect($lastPrune['deleted'] ?? [])->map(fn ($n, $t) => "$t $n")->implode(' · ') }}</bdi></p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
