<x-filament-panels::page>
    @if (! $client)
        <x-filament::section>
            <p style="font-size:14px;opacity:.8">{{ __('Add or select a client workspace first to use AEO & GEO visibility tools.') }}</p>
        </x-filament::section>
    @else
        {{-- D-229 Honesty & Scope Banner --}}
        <div style="padding:12px 16px;border-radius:12px;border:1px solid rgba(20,184,166,.35);background:rgba(15,118,110,.08);font-size:13.5px;line-height:1.6">
            <strong>{{ $client->name }}</strong> ·
            {{ __('Unconnected AI engines run in honest deterministic readiness mode — no live LLM rankings or crawl volumes are ever fabricated.') }}
        </div>

        {{-- #182 Share-of-Model Matrix & #183 LLM Referral Slice --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-top:14px">
            <x-filament::section>
                <x-slot name="heading">{{ __('Share-of-Model & Citation Matrix') }}</x-slot>
                <div style="display:grid;gap:8px;font-size:13.5px">
                    <div style="display:flex;justify-content:space-between">
                        <span>{{ __('Client citation share') }}</span>
                        <strong dir="ltr">{{ number_format($matrix['client_share_pct'], 1) }}%</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span>{{ __('Average mention rank') }}</span>
                        <strong dir="ltr">{{ $matrix['avg_position'] ?? '—' }}</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span>{{ __('Live vs deterministic runs') }}</span>
                        <strong dir="ltr">{{ $matrix['live_runs'] }} / {{ $matrix['deterministic_runs'] }}</strong>
                    </div>
                    @foreach ($matrix['competitors'] as $comp)
                        <div style="display:flex;justify-content:space-between;border-top:1px solid rgba(148,163,184,.18);padding-top:6px">
                            <span>{{ $comp['name'] }}</span>
                            <strong dir="ltr">{{ number_format($comp['share_pct'], 1) }}% ({{ $comp['mentions'] }})</strong>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('LLM Referral Revenue Slice') }}</x-slot>
                <x-slot name="description">
                    @if ($referrals['status'] === 'no_synced_rows')
                        {{ __('No synced GA4 / UTM AI referrer rows yet.') }}
                    @else
                        {{ __('Attributed from synced analytics rows.') }}
                    @endif
                </x-slot>
                <div style="display:grid;gap:8px;font-size:13.5px">
                    @foreach ($referrals['by_engine'] as $engHost => $vals)
                        <div style="display:flex;justify-content:space-between">
                            <span><bdi dir="ltr">{{ $engHost }}</bdi></span>
                            <strong dir="ltr">{{ $vals['sessions'] }} · {{ $vals['conversions'] }} · {{ number_format($vals['revenue'], 2) }} {{ $client->currency }}</strong>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        {{-- #181 Multi-Engine Brand Citation Tracker --}}
        <x-filament::section style="margin-top:16px">
            <x-slot name="heading">{{ __('AI Brand Citation Prompts') }}</x-slot>
            <x-slot name="description">{!! __('Track prompts across <bdi dir="ltr">ChatGPT</bdi>, <bdi dir="ltr">Perplexity</bdi>, <bdi dir="ltr">Gemini</bdi>, <bdi dir="ltr">Claude</bdi>, and <bdi dir="ltr">AI Overviews</bdi>.') !!}</x-slot>

            @if ($canEdit)
                <form wire:submit="addTrackedPrompt" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:14px">
                    <input type="text" wire:model="promptText" placeholder="{{ __('Enter Arabic or English search prompt...') }}" style="min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                    <input type="text" wire:model="competitorsCsv" placeholder="{{ __('Up to 5 competitors (comma-separated)') }}" style="min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                    <select wire:model="promptLocale" style="min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        <option value="ar">العربية (AR)</option>
                        <option value="en">English (EN)</option>
                    </select>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button type="submit" style="min-height:44px;padding:0 16px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">{{ __('Save prompt') }}</button>
                        <button type="button" wire:click="runCitationTracker" style="min-height:44px;padding:0 16px;border-radius:8px;border:1px solid #0f766e;background:transparent;font-weight:600;cursor:pointer">{{ __('Run citation check') }}</button>
                    </div>
                </form>
            @endif

            @forelse ($prompts as $p)
                <div style="padding:12px;border:1px solid rgba(148,163,184,.22);border-radius:10px;margin-top:8px">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                        <strong>{{ $p->prompt_text }}</strong>
                        <div style="display:flex;gap:6px;align-items:center">
                            @foreach ($p->citationRuns as $r)
                                <span style="padding:3px 8px;border-radius:99px;background:{{ $r->mode === 'live' ? 'rgba(20,184,166,.18)' : 'rgba(148,163,184,.2)' }};font-size:12px">
                                    <bdi dir="ltr">{{ $r->engine }} ({{ $r->mode }})</bdi>
                                </span>
                            @endforeach
                            @if ($canEdit)
                                <button type="button" wire:click="deleteTrackedPrompt({{ $p->id }})" style="min-height:44px;padding:0 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:12px;cursor:pointer">{{ __('Remove') }}</button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p style="font-size:13.5px;opacity:.7;margin:0">{{ __('No tracked prompts saved for this client yet.') }}</p>
            @endforelse
        </x-filament::section>

        {{-- #184, #185, #188, #189, #191 Domain Audit & SafeFetcher Inspector --}}
        <x-filament::section style="margin-top:16px">
            <x-slot name="heading">{!! __('Domain <bdi dir="ltr">llms.txt</bdi>, AI Crawler & Internal Link Auditor') !!}</x-slot>
            @if ($canEdit)
                <form wire:submit="runDomainAeoAudit" style="display:grid;gap:10px;max-width:640px">
                    <input type="url" wire:model="auditDomainUrl" dir="ltr" placeholder="https://client-domain.com" style="min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                    <textarea wire:model="highConvertingUrlsText" rows="2" dir="ltr" placeholder="https://client-domain.com/pricing (one high-converting URL per line)" style="padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px"></textarea>
                    <div>
                        <button type="submit" style="min-height:44px;padding:0 18px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">{{ __('Run domain AEO audit') }}</button>
                    </div>
                </form>
            @endif

            @if ($latestAudit)
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:14px">
                    <div style="border:1px solid rgba(148,163,184,.25);border-radius:10px;padding:12px">
                        <strong>{{ __('AI crawler permissions') }} (<bdi dir="ltr">{{ $latestAudit->domain_url }}</bdi>)</strong>
                        <div style="display:grid;gap:6px;margin-top:8px;font-size:13px" dir="ltr">
                            @foreach (($latestAudit->robots_bot_matrix_json ?? []) as $bot => $st)
                                <div style="display:flex;justify-content:space-between"><span>{{ $bot }}</span><strong>{{ $st }}</strong></div>
                            @endforeach
                        </div>
                    </div>
                    <div style="border:1px solid rgba(148,163,184,.25);border-radius:10px;padding:12px">
                        <strong><bdi dir="ltr">llms.txt</bdi></strong>
                        <pre dir="ltr" style="font-size:12px;white-space:pre-wrap;margin:8px 0 0">{{ $latestAudit->llms_txt_markdown }}</pre>
                    </div>
                </div>
            @endif
        </x-filament::section>

        {{-- #186 Query Fan-Out & #192 Programmatic Comparison Brief Previews --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:16px">
            <x-filament::section>
                <x-slot name="heading">{{ __('Conversational Query Fan-Out') }}</x-slot>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <input type="text" wire:model="fanOutSeed" placeholder="{{ __('Seed keyword...') }}" style="flex:1;min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                    <button type="button" wire:click="previewFanOut" style="min-height:44px;padding:0 16px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">{{ __('Expand prompts') }}</button>
                </div>
                @if (! empty($fanOutResults['prompts']))
                    <ul style="margin:12px 0 0;padding-inline-start:18px;font-size:13.5px;line-height:1.7">
                        @foreach ($fanOutResults['prompts'] as $fp)
                            <li><bdi dir="ltr">[{{ $fp['market'] }} · {{ $fp['stage'] }}]</bdi> {{ $fp['prompt'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Comparison & Alternatives Brief Builder') }}</x-slot>
                <div style="display:grid;gap:8px">
                    <input type="text" wire:model="briefCompetitor" placeholder="{{ __('Competitor or category...') }}" style="min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                    <div style="display:flex;gap:8px">
                        <select wire:model="briefCity" style="flex:1;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                            <option value="Riyadh">{{ __('Riyadh') }}</option>
                            <option value="Dubai">{{ __('Dubai') }}</option>
                            <option value="Amman">{{ __('Amman') }}</option>
                            <option value="Cairo">{{ __('Cairo') }}</option>
                        </select>
                        <button type="button" wire:click="previewComparisonBrief" style="min-height:44px;padding:0 16px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">{{ __('Generate brief') }}</button>
                    </div>
                </div>
                @if (! empty($briefPreview['h1']))
                    <div style="margin-top:12px;font-size:13.5px;line-height:1.65">
                        <strong>{{ $briefPreview['h1'] }}</strong>
                        <p style="margin:6px 0;opacity:.85">{{ $briefPreview['answer_first_lead'] }}</p>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
