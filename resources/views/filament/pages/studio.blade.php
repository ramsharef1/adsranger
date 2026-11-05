<x-filament-panels::page>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
        <label style="font-size:14px;font-weight:600">{{ __('Client') }}:</label>
        <select wire:model.live="clientId" style="padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
            @foreach ($clients as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
    </div>

    @if (! $client)
        <x-filament::section>
            <p style="opacity:.75">{{ __('Add an active client first to use Creative Studio.') }}</p>
        </x-filament::section>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:18px">
            {{-- #134 Brand Kit --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('Brand kit') }}</x-slot>
                <x-slot name="description">{{ __('Colors, fonts, default dialect and banned words checked on every draft.') }}</x-slot>
                <div style="display:grid;gap:10px;font-size:14px">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div>
                            <label style="display:block;font-size:12px;opacity:.75">{{ __('Brand color') }}</label>
                            <input type="text" wire:model="primaryColor" dir="ltr" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;opacity:.75">{{ __('Default dialect') }}</label>
                            <select wire:model="defaultDialect" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                                @foreach ($dialects as $k => $lbl)
                                    <option value="{{ $k }}">{{ __($lbl) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;opacity:.75">{{ __('Banned words (comma-separated)') }}</label>
                        <input type="text" wire:model.live="bannedWordsInput" placeholder="{{ __('e.g. guaranteed, cheapest, مجاناً') }}" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                    </div>
                    @if ($canEdit)
                        <div>
                            <x-filament::button size="sm" wire:click="saveBrandKit">{{ __('Save brand kit') }}</x-filament::button>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            {{-- #135 Asset Library --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('Asset library') }} ({{ $assets->count() }})</x-slot>
                <x-slot name="description">{{ __('Track creative files, aspect ratios and tags for this client.') }}</x-slot>
                @if ($canEdit)
                    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px;margin-bottom:10px">
                        <input type="text" wire:model="assetTitle" placeholder="{{ __('Asset title') }}" style="padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                        <select wire:model="assetFormat" style="padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                            <option value="image">{{ __('Image') }}</option>
                            <option value="video">{{ __('Video') }}</option>
                            <option value="carousel">{{ __('Carousel') }}</option>
                        </select>
                        <select wire:model="assetRatio" dir="ltr" style="padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                            <option value="1:1">1:1</option>
                            <option value="9:16">9:16</option>
                            <option value="16:9">16:9</option>
                            <option value="4:5">4:5</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;margin-bottom:12px">
                        <input type="text" wire:model="assetTags" placeholder="{{ __('Tags: ramadan, video, offer') }}" style="flex:1;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                        <x-filament::button size="sm" color="gray" wire:click="addAsset">{{ __('Add asset') }}</x-filament::button>
                    </div>
                @endif
                @foreach ($assets as $a)
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid rgba(148,163,184,.18);font-size:13px">
                        <span><strong>{{ $a->title }}</strong> · {{ strtoupper($a->format) }} (<bdi dir="ltr">{{ $a->aspect_ratio }}</bdi>)</span>
                        <span style="opacity:.7">{{ implode(', ', (array) ($a->tags ?? [])) }}</span>
                    </div>
                @endforeach
            </x-filament::section>
        </div>

        {{-- #131 AI Ad Copy in Dialects + #136 Placement Previews + #54 Save Brief --}}
        <x-filament::section style="margin-top:18px">
            <x-slot name="heading">{{ __('AI ad copy & placement preview') }}</x-slot>
            <x-slot name="description">{{ __('Draft in Gulf, Levantine, Egyptian or MSA Arabic, English or French, and preview across placements.') }}</x-slot>

            @if ($violations)
                <div style="background:rgba(239,68,68,.12);border:1px solid #ef4444;color:#fca5a5;padding:10px 14px;border-radius:10px;margin-bottom:12px;font-size:13px">
                    {{ __('Banned words detected in copy') }}: <strong>{{ implode(', ', $violations) }}</strong>
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px">
                <div style="display:grid;gap:10px;font-size:14px">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div>
                            <label style="display:block;font-size:12px;opacity:.75">{{ __('Dialect / language') }}</label>
                            <select wire:model.live="dialect" wire:change="generateDraft" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                                @foreach ($dialects as $k => $lbl)
                                    <option value="{{ $k }}">{{ __($lbl) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;opacity:.75">{{ __('Placement preview') }}</label>
                            <select wire:model.live="placement" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                                @foreach ($placements as $k => $lbl)
                                    <option value="{{ $k }}">{{ __($lbl) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;opacity:.75">{{ __('Offer / key message') }}</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" wire:model="offer" placeholder="{{ __('e.g. 25% off Ramadan collection') }}" style="flex:1;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                            <x-filament::button size="sm" color="gray" wire:click="generateDraft">{{ __('Generate copy') }}</x-filament::button>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;opacity:.75">{{ __('Brief title') }}</label>
                        <input type="text" wire:model="briefTitle" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;opacity:.75">{{ __('Hook (first line)') }}</label>
                        <input type="text" wire:model.live="hook" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;opacity:.75">{{ __('Primary text') }}</label>
                        <textarea wire:model.live="primaryText" rows="3" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px">
                        <div>
                            <label style="display:block;font-size:12px;opacity:.75">{{ __('Headline') }}</label>
                            <input type="text" wire:model.live="headline" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;opacity:.75">{{ __('CTA button') }}</label>
                            <input type="text" wire:model.live="cta" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.3);background:transparent;color:inherit">
                        </div>
                    </div>
                    @if ($canEdit)
                        <div>
                            <x-filament::button wire:click="saveBrief">{{ __('Save brief & create client approval link') }}</x-filament::button>
                        </div>
                    @endif
                </div>

                {{-- Live Placement Card (#136) --}}
                <div style="border:1px solid rgba(148,163,184,.28);border-radius:14px;padding:18px;background:rgba(15,23,42,.45)">
                    <div style="font-size:12px;opacity:.7;margin-bottom:10px">
                        {{ __('Live placement preview') }} · <strong>{{ $placements[$placement] ?? $placement }}</strong>
                    </div>
                    <div style="font-weight:700;margin-bottom:6px;color:{{ $primaryColor }}">{{ $client->name }}</div>
                    @if ($hook)
                        <div style="font-weight:600;margin-bottom:6px">{{ $hook }}</div>
                    @endif
                    <p style="font-size:14px;opacity:.9;white-space:pre-line;margin:0 0 12px">{{ $primaryText }}</p>
                    <div style="aspect-ratio:{{ $placement === 'story' ? '9/12' : ($placement === 'native' ? '16/9' : '16/10') }};max-height:180px;border-radius:10px;background:linear-gradient(135deg, {{ $primaryColor }}22, rgba(148,163,184,.12));border:1px dashed rgba(148,163,184,.3);display:flex;align-items:center;justify-content:center;font-size:13px;opacity:.75;margin-bottom:12px">
                        {{ strtoupper($placement) }} CREATIVE PREVIEW
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding-top:10px;border-top:1px solid rgba(148,163,184,.2)">
                        <strong style="font-size:14px">{{ $headline }}</strong>
                        <span style="padding:6px 12px;border-radius:8px;background:{{ $primaryColor }};color:#0b1020;font-weight:600;font-size:12px">{{ $cta }}</span>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- #54 Creative Briefs & Signed Client Approval Links --}}
        <x-filament::section style="margin-top:18px">
            <x-slot name="heading">{{ __('Creative briefs & client approvals') }} ({{ $briefs->count() }})</x-slot>
            @if ($briefs->isEmpty())
                <p style="opacity:.7;font-size:14px">{{ __('No briefs yet. Generate copy above and save a brief to get a shareable client approval link.') }}</p>
            @else
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <thead>
                        <tr style="text-align:start;border-bottom:1px solid rgba(148,163,184,.25)">
                            <th style="padding:8px">{{ __('Title') }}</th>
                            <th style="padding:8px">{{ __('Dialect') }}</th>
                            <th style="padding:8px">{{ __('Placement') }}</th>
                            <th style="padding:8px">{{ __('Status') }}</th>
                            <th style="padding:8px">{{ __('Client feedback') }}</th>
                            <th style="padding:8px">{{ __('Approval link') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($briefs as $b)
                            <tr style="border-top:1px solid rgba(148,163,184,.18)">
                                <td style="padding:8px;font-weight:600">{{ $b->title }} (v{{ $b->version }})</td>
                                <td style="padding:8px">{{ strtoupper($b->dialect) }}</td>
                                <td style="padding:8px">{{ $b->placement }}</td>
                                <td style="padding:8px"><x-filament::badge :color="$b->status === 'approved' ? 'success' : ($b->status === 'changes_requested' ? 'warning' : 'info')">{{ $b->status }}</x-filament::badge></td>
                                <td style="padding:8px">{{ $b->client_feedback ?: '—' }}</td>
                                <td style="padding:8px"><a href="{{ $b->approvalUrl() }}" target="_blank" style="color:#14B8A6;text-decoration:underline"><bdi dir="ltr">{{ __('Open client link') }} ↗</bdi></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
