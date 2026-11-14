@php
    $w = fn ($v): string => (string) \App\Support\TextClean::western((string) $v);
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __(':passing of :total register rows verified', ['passing' => $w($passing), 'total' => $w($total)]) }}</x-slot>
        <x-slot name="description">{{ \App\Support\Seo\ToolCopy::html(__('Every feature marked Done in config/feature_register.php must resolve to live code anchors and a passing test file.'), app()->getLocale() === 'ar') }}</x-slot>

        <div style="overflow-x:auto">
            <table class="adm-dense-table" style="width:100%;border-collapse:collapse;font-size:13.5px">
                <caption class="sr-only">{{ __('Register-to-code guard') }}</caption>
                <thead>
                    <tr style="border-bottom:1px solid rgba(148,163,184,.28)">
                        <th scope="col" style="padding:8px 10px;text-align:start">{{ __('ID') }}</th>
                        <th scope="col" style="padding:8px 10px;text-align:start">{{ __('Feature') }}</th>
                        <th scope="col" style="padding:8px 10px;text-align:start">{{ __('Code anchors') }}</th>
                        <th scope="col" style="padding:8px 10px;text-align:start">{{ __('Tests') }}</th>
                        <th scope="col" style="padding:8px 10px;text-align:start">{{ __('State') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr style="border-top:1px solid rgba(148,163,184,.18)">
                            <td style="padding:8px 10px;font-weight:700;white-space:nowrap"><bdi dir="ltr">{{ $row['id'] }}</bdi></td>
                            <td style="padding:8px 10px"><bdi>{{ $row['title'] }}</bdi></td>
                            <td style="padding:8px 10px;font-size:12.5px"><bdi dir="ltr">{{ implode(', ', $row['code']) }}</bdi></td>
                            <td style="padding:8px 10px;font-size:12.5px"><bdi dir="ltr">{{ implode(', ', $row['tests']) }}</bdi></td>
                            <td style="padding:8px 10px;white-space:nowrap;font-weight:700;color:{{ $row['ok'] ? '#0d9488' : '#b91c1c' }}">
                                {{ $row['ok'] ? __('Verified') : __('Missing anchor') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
