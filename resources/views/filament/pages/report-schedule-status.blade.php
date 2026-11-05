@php
    $unsub = $s ? $s->unsubscribed() : [];
    $next = $s?->nextSendDate();
    $tz = $client->agency->timezone ?: 'UTC';
    $sends = $s ? $s->sends()->latest('id')->limit(10)->get() : collect();
    $statusColor = ['sent' => 'success', 'queued' => 'info', 'skipped' => 'gray', 'failed' => 'danger'];
@endphp
<div style="display:grid;gap:10px;font-size:14px">
    @if ($s)
        <div style="display:flex;flex-wrap:wrap;gap:16px">
            <div><span style="opacity:.7">{{ __('Last sent') }}:</span>
                <strong><bdi dir="ltr">{{ $s->last_sent_at ? $s->last_sent_at->setTimezone($tz)->format('Y-m-d H:i') : '–' }}</bdi></strong></div>
            <div><span style="opacity:.7">{{ __('Next send') }}:</span>
                <strong>@if ($next)<bdi dir="ltr">{{ $next->toDateString() }}</bdi>@else{{ $s->active ? '–' : __('Not sending for now') }}@endif</strong></div>
        </div>
        @if ($s->include_link && ! $s->activeLink())
            <div style="font-size:13px;color:rgb(217 119 6)">{{ __('The client link for these emails was revoked or expired. Save the schedule to create a new one.') }}</div>
        @endif
        @if ($unsub)
            <div style="font-size:13px">
                <span style="opacity:.7">{{ __('Asked to stop these emails') }}:</span>
                @foreach ($unsub as $e)<bdi dir="ltr">{{ $e }}</bdi>@if (! $loop->last), @endif @endforeach
            </div>
        @endif
        @if ($sends->isNotEmpty())
            <details>
                <summary style="cursor:pointer">{{ __('Send history') }}</summary>
                <table style="width:100%;font-size:13px;margin-top:6px;border-collapse:collapse">
                    @foreach ($sends as $row)
                        <tr style="border-top:1px solid rgba(148,163,184,.25)">
                            <td style="padding:4px"><bdi dir="ltr">{{ ($row->sent_at ?? $row->created_at)->setTimezone($tz)->format('Y-m-d') }}</bdi></td>
                            <td style="padding:4px">{{ $row->kind === 'monthly' ? __('Monthly report') : __('Weekly summary') }}</td>
                            <td style="padding:4px"><bdi dir="ltr">{{ $row->email }}</bdi></td>
                            <td style="padding:4px"><x-filament::badge :color="$statusColor[$row->status] ?? 'gray'" style="display:inline-flex">{{ __(['sent' => 'Sent', 'queued' => 'Queued', 'skipped' => 'Skipped', 'failed' => 'Not sent'][$row->status] ?? $row->status) }}</x-filament::badge></td>
                        </tr>
                    @endforeach
                </table>
            </details>
        @endif
    @else
        <div style="opacity:.75">{{ __('No schedule yet. Add recipients and choose when to send.') }}</div>
    @endif
</div>
