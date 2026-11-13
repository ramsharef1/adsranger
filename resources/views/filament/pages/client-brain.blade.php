@php
    use App\Models\AiBrainItem;
    use App\Support\AiWorkspace\QualityGate;
    $muted = 'opacity:.72;font-size:12.5px';
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:13px;width:100%';
    $label = 'display:block;font-size:12px;font-weight:600;margin-bottom:4px';
    $card = 'border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:14px;display:grid;gap:8px';
    $ltr = fn ($v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
    $types = ['rule' => __('Rules'), 'decision' => __('Decisions'), 'brief' => __('Briefs'), 'learning' => __('Learnings'), 'output' => __('Outputs'), 'chat_link' => __('Chat links')];
    $bandStyle = ['ready' => 'color:#0F766E', 'review' => 'color:#B45309', 'blocked' => 'color:#B91C1C'];
    $bandIcon = ['ready' => '✓', 'review' => '⚠', 'blocked' => '✗'];
    $families = ['Q1' => __('Numbers'), 'Q2' => __('Arabic'), 'Q3' => __('Claims'), 'Q5' => __('Structure'), 'Q9' => __('Integrity')];
@endphp
<x-filament-panels::page>
    <div style="{{ $muted }};font-size:13px;line-height:1.7">{{ __('Everything you or your AI saved about this client. Every item is checked by AdsRanger before it is kept.') }}</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end">
        <div>
            <label for="cb-client" style="{{ $label }}">{{ __('Client') }}</label>
            <select id="cb-client" wire:model.live="clientId" style="{{ $field }}">
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="cb-type" style="{{ $label }}">{{ __('Type') }}</label>
            <select id="cb-type" wire:model.live="type" style="{{ $field }}">
                <option value="">{{ __('All') }}</option>
                @foreach ($types as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="cb-ai" style="{{ $label }}">{{ __('AI') }}</label>
            <select id="cb-ai" wire:model.live="ai" style="{{ $field }}">
                <option value="">{{ __('All') }}</option>
                @foreach (AiBrainItem::PROVIDERS as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($pack)
        <details style="{{ $card }}">
            <summary style="cursor:pointer;min-height:44px;display:flex;align-items:center;font-weight:600">{{ __('What your AI sees about this client') }}</summary>
            <div style="{{ $muted }}">{!! __('Sent with every request. Data until :date, currency :cur.', ['date' => $pack['data_until'] ? $ltr($pack['data_until']) : '—', 'cur' => $ltr($pack['currency'])]) !!}</div>
            @if ($pack['facts'] === [])
                <p style="{{ $muted }}">{{ __('No synced data for this client yet, so your AI has no numbers to quote.') }}</p>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:13px;border-collapse:collapse">
                        <thead><tr><th style="text-align:start;padding:6px">{{ __('Metric') }}</th><th style="text-align:start;padding:6px">{{ __('Period') }}</th><th style="text-align:end;padding:6px">{{ __('Value') }}</th></tr></thead>
                        <tbody>
                            @foreach ($pack['facts'] as $f)
                                <tr style="border-top:1px solid rgba(148,163,184,.2)">
                                    <td style="padding:6px">{{ __($f['label']) }}</td>
                                    <td style="padding:6px">{!! $ltr($f['period']) !!}</td>
                                    <td style="padding:6px;text-align:end">{!! $ltr(rtrim(rtrim(number_format($f['value'], 2), '0'), '.')) !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if ($pack['rules'] !== [])
                <div><strong>{{ __('Client rules') }}:</strong> @foreach ($pack['rules'] as $r)<span style="display:inline-block;margin-inline-end:8px"><bdi>{{ $r }}</bdi></span>@endforeach</div>
            @endif
            {{-- FS-02 (D-299): what may go to AI about this client; everything is on until someone unticks it. --}}
            @if ($canRun)
                <form wire:submit="saveScope" data-ai-scope style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;border-top:1px solid rgba(148,163,184,.2);padding-top:10px">
                    <strong style="font-size:13px">{{ __('Your AI may read') }}:</strong>
                    @foreach (['spend' => __('Spend, cost and revenue'), 'results' => __('Results, clicks and impressions'), 'rules' => __('Client rules')] as $part => $label)
                        <label style="display:inline-flex;gap:6px;align-items:center;min-height:44px;font-size:13px"><input type="checkbox" wire:model="scope" value="{{ $part }}"> {{ $label }}</label>
                    @endforeach
                    <x-filament::button type="submit" size="sm" color="gray">{{ __('Save') }}</x-filament::button>
                </form>
                <p style="{{ $muted }};font-size:12px;margin:0">{{ __('Phone numbers, emails and ID-like numbers are always hidden before anything is sent.') }}</p>
            @endif
        </details>
    @endif

    @if ($client && ! $full)
        <p style="{{ $muted }};font-size:13px">{!! __('Organising the client brain, the AI box and the night shift come with :plan.', ['plan' => e(\App\Support\PlanGate::tierLabel('pro'))]) !!}</p>
    @endif
    @if ($client && $canRun && $full)
        <section style="{{ $card }}" aria-label="{{ __('AI box') }}">
            <h3 style="font-size:15px;font-weight:700;margin:0">{{ __('AI box') }}</h3>
            @if ($keys->isEmpty())
                <p style="margin:0;font-size:14px">{{ __('Add your own API key to run AI here.') }} <a href="{{ \App\Filament\Pages\AiKeysPage::getUrl() }}" style="color:#0F766E;text-decoration:underline">{{ __('My API keys') }}</a></p>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
                    <div><label for="bx-pb" style="{{ $label }}">{{ __('Playbook') }}</label>
                        <select id="bx-pb" wire:model.live="boxPlaybook" style="{{ $field }}">@foreach (\App\Support\AiWorkspace\Playbooks::all() as $k => $p)<option value="{{ $k }}">{{ app()->getLocale() === 'ar' ? $p['title_ar'] : $p['title_en'] }}</option>@endforeach</select></div>
                    <div><label for="bx-key" style="{{ $label }}">{{ __('Key') }}</label>
                        <select id="bx-key" wire:model.live="boxKey" style="{{ $field }}">@foreach ($keys as $kk)<option value="{{ $kk->provider }}">{{ \App\Models\AiKey::PROVIDERS[$kk->provider] }} · {{ \App\Support\AiWorkspace\OwnKey::modelLabel($kk->provider, $kk->model) }}</option>@endforeach</select></div>
                    @if ($boxPlaybook === 'dialect_ad_copy')
                        <div><label for="bx-d" style="{{ $label }}">{{ __('Dialect') }}</label>
                            <select id="bx-d" wire:model.live="boxDialect" style="{{ $field }}">@foreach (['gulf' => __('Gulf'), 'levantine' => __('Levantine'), 'egyptian' => __('Egyptian'), 'maghrebi' => __('Maghrebi')] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                        <div><label for="bx-p" style="{{ $label }}">{{ __('Platform') }}</label>
                            <select id="bx-p" wire:model.live="boxPlatform" style="{{ $field }}">@foreach (['meta' => 'Meta', 'google_ads' => 'Google Ads', 'tiktok' => 'TikTok', 'snapchat' => 'Snapchat'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    @endif
                </div>
                @php
                    $boxLabel = ['translate' => __('Text to translate (Arabic or English)'), 'dialect_negatives' => __('Search terms, one per line')][$boxPlaybook] ?? __('Anything to add (goal, offer, period)');
                @endphp
                <div><label for="bx-n" style="{{ $label }}">{{ $boxLabel }}</label>
                    <textarea id="bx-n" wire:model.blur="boxNotes" rows="{{ isset(\App\Support\AiWorkspace\Playbooks::MATERIAL[$boxPlaybook]) ? 6 : 2 }}" maxlength="{{ \App\Support\AiWorkspace\Playbooks::notesMax($boxPlaybook) }}" style="{{ $field }};min-height:64px" dir="auto"></textarea>
                    @if ($boxPlaybook === 'dialect_negatives')<p style="{{ $muted }};font-size:12px;margin:4px 0 0">{{ __('Clear cases (jobs, free, downloads, courses) are decided here at no cost; only the rest go to your AI.') }}</p>@endif
                    @if (in_array($boxPlaybook, ['alt_text', 'audit_summary'], true))<p style="{{ $muted }};font-size:12px;margin:4px 0 0">{{ __('Uses this client\'s last finished site audit.') }}</p>@endif
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;font-size:12.5px">
                    <span style="border:1px solid rgba(148,163,184,.35);border-radius:999px;padding:4px 10px">✓ <bdi>{{ $client->name }}</bdi></span>
                    @if (in_array($boxPlaybook, ['weekly_summary', 'dialect_ad_copy', 'ramadan_plan'], true))<span style="border:1px solid rgba(148,163,184,.35);border-radius:999px;padding:4px 10px">{{ __('Last 7 days vs the 7 before') }}</span>@endif
                    <span style="border:1px solid rgba(148,163,184,.35);border-radius:999px;padding:4px 10px">{{ __('Checked by AdsRanger before it is saved') }}</span>
                </div>
                @if ($key)
                    <div style="{{ $muted }}">
                        @if ($estimate !== null){!! __('Estimated cost on your key: about :usd (estimate).', ['usd' => $ltr('≤ '.number_format($estimate, 3).' USD')]) !!} · @endif
                        {!! __('This month: :spent of :cap', ['spent' => $ltr(number_format($key->spentThisMonth(), 2).' USD'), 'cap' => $ltr(number_format((float) $key->monthly_cap_usd, 2).' USD')]) !!}
                    </div>
                @endif
                @if ($boxError)
                    <div role="alert" style="border:1px solid rgba(185,28,28,.4);border-radius:10px;padding:10px;font-size:13.5px">
                        {{ $boxError }}
                        @if ($boxBilling) <a href="{{ $boxBilling }}" target="_blank" rel="noopener noreferrer" style="color:#0F766E;text-decoration:underline">{{ __('Open your provider\'s billing page') }}</a>@endif
                    </div>
                @endif
                <div><x-filament::button wire:click="runBox" wire:loading.attr="disabled" style="min-height:44px">
                    <span wire:loading.remove wire:target="runBox">{{ __('Run') }}</span><span wire:loading wire:target="runBox">{{ __('Running…') }}</span></x-filament::button></div>
            @endif
        </section>
    @endif

    @if (! $client)
        <p style="{{ $muted }}">{{ __('Add a client first.') }}</p>
    @elseif ($items->isEmpty())
        <div style="{{ $card }}">
            <p>{{ __('Nothing saved yet. Ask your AI to save what matters about this client.') }}</p>
            <p style="{{ $muted }}">{{ __('For example: "Save this as a rule: never mention prices in this client\'s ads."') }}</p>
        </div>
    @else
        <div style="display:grid;gap:12px">
            @foreach ($items as $item)
                @php
                    $c = $item->cleanup ?? [];
                    $failed = collect($item->checks ?? [])->where('fix', '!=', 'auto');
                    $showOriginal = in_array($item->id, $original, true);
                @endphp
                <article style="{{ $card }}{{ $item->state === 'needs_formatting' ? ';background:rgba(148,163,184,.12)' : '' }}" aria-label="{{ $item->title }}">
                    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
                        <span style="{{ $muted }}">
                            {{ $types[$item->type] ?? $item->type }}
                            @if ($item->providerName())
                                · {!! __('Written by your AI (:ai) · checked by :brand', ['ai' => $ltr($item->providerName()), 'brand' => $ltr('AdsRanger')]) !!}
                            @endif
                        </span>
                        <time datetime="{{ $item->created_at->toIso8601String() }}" style="{{ $muted }}">{{ $item->created_at->locale(app()->getLocale())->translatedFormat(app()->getLocale() === 'ar' ? 'j F Y، g:i A' : 'j M Y, g:i a') }}@if ($item->user) · <bdi>{{ $item->user->name }}</bdi>@endif</time>
                    </div>
                    <h3 style="font-size:15px;font-weight:700;margin:0"><bdi>{{ $item->title }}</bdi></h3>

                    @if ($item->state === 'needs_formatting')
                        <div style="font-weight:600">{{ __('Needs formatting · internal only') }}</div>
                        <ul style="margin:0;padding-inline-start:18px;font-size:13px">
                            @foreach ($failed->where('family', 'Q5') as $chk)<li>{!! $ltr($chk['found']) !!}</li>@endforeach
                        </ul>
                    @elseif ($item->score !== null && $item->type !== 'chat_link')
                        <div style="font-weight:600;{{ $bandStyle[$item->band] ?? '' }}">
                            {{ $bandIcon[$item->band] ?? '' }} {!! __('Quality score :score · :band', ['score' => $ltr($item->score), 'band' => e(QualityGate::bandLabel($item->band))]) !!}
                            @if ($failed->isNotEmpty())<span style="{{ $muted }}"> · {!! __('Failed checks: :n', ['n' => $ltr($failed->count())]) !!}</span>@endif
                        </div>
                        <div style="{{ $muted }}">{!! __('Filler lines hidden: :h · Actions: :a · Fixed automatically: :f', ['h' => $ltr($c['hidden'] ?? 0), 'a' => $ltr(count($c['actions'] ?? [])), 'f' => $ltr($c['fixed'] ?? 0)]) !!}</div>
                    @endif

                    @if ($item->type === 'chat_link')
                        <p style="margin:0;font-size:13px">
                            @if ($item->url)<bdi dir="ltr">{{ parse_url($item->url, PHP_URL_HOST) }}</bdi> · @endif{{ __('Opening it needs your own login to that AI.') }}
                        </p>
                    @elseif ($showOriginal)
                        <pre style="white-space:pre-wrap;font-family:inherit;font-size:13px;margin:0;opacity:.85" dir="auto">{{ $item->original }}</pre>
                    @elseif ($editingId === $item->id)
                        <form wire:submit="saveEdit" style="display:grid;gap:6px" data-edit-form>
                            <textarea wire:model="editText" rows="6" dir="auto" class="fi-input" style="width:100%;font-size:14px;line-height:1.8"></textarea>
                            <div style="display:flex;gap:8px"><x-filament::button type="submit" size="sm">{{ __('Save edit') }}</x-filament::button>
                                <x-filament::button size="sm" color="gray" wire:click="$set('editingId', null)">{{ __('Cancel') }}</x-filament::button></div>
                            <p style="{{ $muted }};font-size:12px;margin:0">{{ __('Word changes you make are kept as style notes for this client, so your AI writes it your way next time.') }}</p>
                        </form>
                    @else
                        <div style="font-size:14px;line-height:1.8" dir="auto">{!! nl2br(e($item->clean ?? $item->original)) !!}</div>
                        @if (! empty($item->meta['edited_at']))<div style="{{ $muted }};font-size:12px">{{ __('Edited by your team') }}</div>@endif
                    @endif

                    {{-- FS-03: where each number came from. --}}
                    @if (! empty($item->meta['sources']))
                        <details data-sources style="font-size:12.5px">
                            <summary style="cursor:pointer;min-height:32px">{{ __('Where the numbers come from (:n)', ['n' => count($item->meta['sources'])]) }}</summary>
                            <ul style="margin:4px 0 0;padding-inline-start:18px">
                                @foreach ($item->meta['sources'] as $src)
                                    <li>{!! $ltr($src['raw']) !!} ← {{ __($src['label']) }} · {!! $ltr($src['period']) !!} · {{ $src['source'] }}@if ($src['date']) · {!! $ltr($src['date']) !!}@endif</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    {{-- FS-06: glossary check on a translation. --}}
                    @if (! empty($item->meta['glossary']))
                        @php($g = $item->meta['glossary'])
                        <div data-glossary style="font-size:12.5px">
                            {!! __('Terms kept: :kept of :total', ['kept' => $ltr($g['kept']), 'total' => $ltr($g['total'])]) !!}
                            @if (! empty($g['missing']))<span style="color:#B45309"> · {{ __('Check:') }} <bdi>{{ implode(' · ', $g['missing']) }}</bdi></span>@endif
                            @foreach ($g['lint'] ?? [] as $l)<div style="color:#B45309">{{ $l }}</div>@endforeach
                        </div>
                    @endif
                    @if (! empty($item->meta['redacted']))<div style="{{ $muted }};font-size:12.5px">{{ __('Personal data hidden before sending: :n', ['n' => $item->meta['redacted']]) }}</div>@endif

                    <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:12.5px">
                        @if (! empty($c['actions']))<span>✓ {{ __('Actionable') }}</span>@endif
                        @if (($c['no_fix'] ?? false) && in_array($item->type, ['output', 'learning'], true))<span>⚠ {{ __('No fix found') }}</span>@endif
                        @if (! empty($c['generic']))<span>{!! __('Generic advice folded: :n', ['n' => $ltr(count($c['generic']))]) !!}</span>@endif
                    </div>

                    @if ($failed->isNotEmpty() && $item->state !== 'needs_formatting')
                        <ul style="margin:0;padding-inline-start:18px;font-size:13px">
                            @foreach ($failed as $chk)
                                <li>{{ $families[$chk['family']] ?? $chk['family'] }}: {{ trans('ai_check.'.$chk['rule']) }} {!! $ltr($chk['found']) !!}@if ($chk['fix'] === 'suggest') · <em>{{ __('Suggestion') }}</em>@endif</li>
                            @endforeach
                        </ul>
                    @endif

                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        @if ($canRun && in_array($item->type, ['output', 'brief', 'learning'], true) && $editingId !== $item->id)
                            <x-filament::button color="gray" size="sm" wire:click="startEdit({{ $item->id }})" style="min-height:44px">{{ __('Edit') }}</x-filament::button>
                        @endif
                        @if ($item->type !== 'chat_link')
                            <x-filament::button color="gray" size="sm" wire:click="toggleOriginal({{ $item->id }})" style="min-height:44px">{{ $showOriginal ? __('Show clean card') : __('Show original reply') }}</x-filament::button>
                        @endif
                        @if ($item->url)
                            <x-filament::button color="gray" size="sm" tag="a" href="{{ $item->url }}" target="_blank" rel="noopener noreferrer nofollow" style="min-height:44px">{{ __('Open chat') }}</x-filament::button>
                        @endif
                        @if ($canManage)
                            <x-filament::button color="danger" size="sm" wire:click="remove({{ $item->id }})" wire:confirm="{{ __('Remove this item from the client brain?') }}" style="min-height:44px">{{ __('Remove') }}</x-filament::button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
