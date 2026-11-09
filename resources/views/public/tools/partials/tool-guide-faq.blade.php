@php
    $tc = \App\Support\Seo\ToolCopy::for($toolSlug ?? 'serp-preview', $ar ?? true);
    $faqJson = \App\Support\Seo\ToolCopy::faqSchema($toolSlug ?? 'serp-preview', $ar ?? true);
    $showEmailCapture = in_array($toolSlug ?? '', ['page-audit', 'hreflang-checker'], true);
@endphp
<section class="card" style="margin-top:28px;padding:22px">
    <h2 style="font-size:20px;margin-top:0">{{ $tc['how_title'] }}</h2>
    <ol style="margin:12px 0 0;padding-inline-start:20px;line-height:1.75">
        @foreach ($tc['how_steps'] as $step)
            <li style="margin-bottom:6px">{{ $step }}</li>
        @endforeach
    </ol>
</section>

<section class="card" style="margin-top:18px;padding:22px">
    <h2 style="font-size:20px;margin-top:0">{{ $tc['faq_title'] }}</h2>
    <div style="display:grid;gap:14px;margin-top:12px">
        @foreach ($tc['faqs'] as $f)
            <div>
                <h3 style="font-size:16px;margin:0 0 4px">{{ $f['q'] }}</h3>
                <p class="muted" style="margin:0;line-height:1.65">{{ $f['a'] }}</p>
            </div>
        @endforeach
    </div>
</section>

@if ($showEmailCapture)
<section class="card" style="margin-top:18px;padding:22px" id="tool-email-capture">
    <h2 style="font-size:19px;margin-top:0">{{ $ar ? 'احفظ تذكيرًا بتقرير الفحص وإشعار الإطلاق' : 'Save an audit reminder & launch update' }}</h2>
    <p class="muted" style="margin:6px 0 14px">{!! $ar ? 'أدخل بريدك الإلكتروني واسم وكالتك أو شركتك لنراسلك عند تفعيل إرسال تقارير الفحص عبر البريد وإطلاق <bdi dir="ltr">AdsRanger</bdi>.' : 'Enter your work email and agency or company name so we can notify you when emailed audit reports and AdsRanger launch.' !!}</p>
    @if (session('tool_report_saved'))
        <p style="font-weight:600;color:var(--acc)">{{ $ar ? 'شكرًا، سجّلنا طلبك.' : 'Thanks, we saved your request.' }}</p>
    @else
        <form method="POST" action="{{ $ar ? '/tools/audit-report-request' : '/en/tools/audit-report-request' }}" style="display:grid;gap:12px;max-width:540px">
            @csrf
            <input type="hidden" name="tool" value="{{ $toolSlug }}">
            <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                <label for="tr-website">Website</label>
                <input id="tr-website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div>
                <label for="tr-email" style="display:block;font-weight:600;margin-bottom:4px">{{ $ar ? 'البريد الإلكتروني للعمل' : 'Work email' }}</label>
                <input id="tr-email" type="email" name="email" required maxlength="190" placeholder="name@agency.com" dir="ltr">
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
                <div>
                    <label for="tr-agency" style="display:block;font-weight:600;margin-bottom:4px">{{ $ar ? 'اسم الوكالة أو الشركة' : 'Agency or company name' }}</label>
                    <input id="tr-agency" type="text" name="agency_name" required minlength="2" maxlength="140">
                </div>
                <div>
                    <label for="tr-country" style="display:block;font-weight:600;margin-bottom:4px">{{ $ar ? 'الدولة' : 'Country' }}</label>
                    <select id="tr-country" name="country" required>
                        <option value="JO">{{ $ar ? 'الأردن' : 'Jordan' }}</option>
                        <option value="SA">{{ $ar ? 'السعودية' : 'Saudi Arabia' }}</option>
                        <option value="AE">{{ $ar ? 'الإمارات' : 'UAE' }}</option>
                        <option value="EG">{{ $ar ? 'مصر' : 'Egypt' }}</option>
                        <option value="KW">{{ $ar ? 'الكويت' : 'Kuwait' }}</option>
                        <option value="QA">{{ $ar ? 'قطر' : 'Qatar' }}</option>
                        <option value="OTHER">{{ $ar ? 'دولة أخرى' : 'Other' }}</option>
                    </select>
                </div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;min-height:44px">
                <input type="checkbox" name="consent" value="1" required style="width:auto;min-height:0;margin:0">
                <span>{!! $ar ? 'أوافق على تلقي رسائل بريد إلكتروني حول إطلاق <bdi dir="ltr">AdsRanger</bdi>.' : 'I agree to receive launch emails from AdsRanger.' !!}</span>
            </label>
            <div>
                <button type="submit" class="btn">{{ $ar ? 'احفظ طلبي' : 'Save my request' }}</button>
            </div>
        </form>
    @endif
</section>
@endif
<script type="application/ld+json">{!! json_encode($faqJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
