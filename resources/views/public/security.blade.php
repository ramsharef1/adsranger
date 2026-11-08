@php
// Website P-10 /security (U-20, UX-157, UX-158; O-222: access keys use encrypted casts; contact privacy@).
$ar = ($lang ?? 'ar') === 'ar';
$arPath = '/security';
$u = fn (string $p) => \App\Support\PublicUrl::path($p, $ar ? 'ar' : 'en');
$title = $ar ? 'كيف تحمي AdsRanger بيانات الوكالات وعملائها | AdsRanger' : 'How AdsRanger Protects Agency and Client Data | AdsRanger';
$meta = $ar
    ? 'كيف تحمي AdsRanger، وهي قيد التطوير، بيانات الوكالات وعملائها: لا تغيير في الحسابات الإعلانية دون موافقتكم، وفصل بيانات كل وكالة، وتحقق بخطوتين.'
    : 'How AdsRanger, now in development, protects agency and client data: no change to ad accounts without your approval, separate agency data and two-step sign-in.';
$schema = [['@type' => 'WebPage', 'name' => $title, 'url' => \App\Support\PublicUrl::absolute($arPath, $ar ? 'ar' : 'en'), 'inLanguage' => $ar ? 'ar' : 'en']];
// CL-1 rev 2 E-03 (Growth 08e §3). Every claim checked in code 2026-11-08: #74 authenticator app + recovery codes (User),
// encrypted Connection tokens, Client::visibleTo, LandingPages SSRF guard, AuditLog team.role_changed, ExportData, D-164 / D-166.
$bdi = fn (string $t) => '<bdi dir="ltr">'.$t.'</bdi>';
$sections = $ar ? [
    ['لا تغيير دون موافقتكم', 'تطلب بعض <a href="'.$u('/integrations').'">المنصات الإعلانية</a> صلاحية إدارة عند الربط، لكن التقارير تقرأ البيانات فقط، ولا تغيّر '.$bdi('AdsRanger').' أي ميزانية أو حملة دون موافقة أحد أعضاء فريقك. وأي تغيير تقترحه أداة لاحقًا يظهر لكم أولًا لتوافقوا عليه أو ترفضوه.'],
    ['فصل بيانات كل وكالة', 'كل سجل في '.$bdi('AdsRanger').' يخص وكالة واحدة، ولا يظهر لوكالة أخرى. وداخل الوكالة، لا يرى أعضاء الفريق إلا العملاء المسندين إليهم، ويحدد المالك أدوار الفريق.'],
    ['تسجيل دخول محمي', 'يمكنك تفعيل التحقق بخطوتين عبر تطبيق المصادقة، مع رموز استرداد تحفظها لديك. ومفاتيح الوصول إلى المنصات الإعلانية محفوظة مشفّرة، ولا تظهر لأي مستخدم.'],
    ['فحص آمن للروابط', 'حين نفحص صفحات الهبوط، لا نتصل أبدًا بعناوين خاصة أو داخلية، ولا نرسل بيانات تسجيل دخول. نقرأ الصفحة العامة فقط كما يراها الزائر.'],
    ['بياناتك تحت تصرفك', 'افصل أي حساب إعلاني في أي وقت، أو صدّر تقاريرك، أو اطلب منا حذف بياناتك. نحتفظ بمساحة العمل المحذوفة 30 يومًا يمكن خلالها استعادتها عبر الدعم، ثم يحذفها فريقنا.'],
    ['سجل النشاط', 'تُسجَّل تغييرات الأدوار والإعدادات الحساسة، مع وقت كل تغيير ومن قام به.'],
] : [
    ['Nothing changes without your approval', 'Some <a href="'.$u('/integrations').'">ad platforms</a> ask for management permission when you connect, but reports only read data, and AdsRanger changes no budget or campaign without a team member\'s approval. Any change a tool suggests later is shown to you first, to approve or reject.'],
    ['Each agency kept apart', 'Every record in AdsRanger belongs to one agency and is never shown to another. Inside an agency, team members see only the clients they\'re given, and the owner sets each person\'s role.'],
    ['Protected sign-in', 'You can turn on two-step verification with an authenticator app, with recovery codes you keep. Access keys for the ad platforms are stored encrypted and are never shown to any user.'],
    ['Safe link checks', 'When we check landing pages, we never connect to private or internal addresses and never send login details. We read only the public page, as a visitor sees it.'],
    ['Your data, your control', 'Disconnect any ad account at any time, export your reports or ask us to delete your data. A deleted workspace is kept for 30 days, when support can restore it; after that our team deletes it.'],
    ['Activity log', 'Changes to roles and sensitive settings are logged, with the time of each change and who made it.'],
];
$never = $ar
    ? ['لا نغيّر حملاتك أو ميزانياتك دون موافقة أحد أعضاء فريقك.', 'لا نبيع بياناتك أو بيانات عملائك.', 'لا نستخدم بيانات عملائك لتدريب نماذج الذكاء الاصطناعي دون موافقتك.']
    : ['We don\'t change your campaigns or budgets without a team member\'s approval.', 'We don\'t sell your data or your clients\' data.', 'We don\'t use your clients\' data to train AI models without your consent.'];
@endphp
@extends('layouts.public')

@push('styles')
<style>
.sec{padding-top:24px;max-width:960px}
.sec a.pl{display:inline-flex;align-items:center;min-height:44px}
.sec-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr));gap:16px;margin-top:24px}
.sec-grid .card h2{font-size:19px;margin:0 0 8px}
.sec-grid .card p{margin:0;color:var(--muted)}
</style>
@endpush

@section('hero')
<h1>{{ $ar ? 'كيف نحمي بيانات وكالتك وعملائك' : 'How we protect your agency and client data' }}</h1>
<p class="muted" style="font-size:17px">{!! $ar
? 'ما زالت <bdi dir="ltr">AdsRanger</bdi> قيد التطوير، وهذه وسائل الحماية المبنية فيها اليوم.'
: 'AdsRanger is in development. These are the safeguards built into it today.' !!}</p>
@endsection

@section('content')
<section class="sec">
    <div class="sec-grid">
        @foreach ($sections as $i => [$heading, $body])
            <div class="card">
                <h2>{{ $heading }}</h2>
                <p>{!! $body !!}
                    @if ($i === 4)
                        <br><a class="pl" href="{{ $u('/privacy') }}">{{ $ar ? 'سياسة الخصوصية' : 'Privacy policy' }}</a> ·
                        <a class="pl" href="{{ $u('/data-deletion') }}">{{ $ar ? 'حذف البيانات' : 'Data deletion' }}</a>
                    @endif
                </p>
            </div>
        @endforeach
    </div>
    <h2 style="margin-top:32px">{{ $ar ? 'ما لا نفعله أبدًا' : 'What we never do' }}</h2>
    <ul>
        @foreach ($never as $line)<li>{{ $line }}</li>@endforeach
    </ul>
    <div class="card" style="margin-top:24px;background:var(--surface)">
        <h2 style="font-size:19px;margin:0 0 8px">{{ $ar ? 'إن وجدت ثغرة' : 'Found a vulnerability?' }}</h2>
        <p style="margin:0">{!! $ar
            ? 'راسلنا على <a href="mailto:privacy@adsranger.com"><bdi dir="ltr">privacy@adsranger.com</bdi></a> بخطوات تكرار المشكلة، ولا تنشرها قبل أن نصلحها. نشكر كل من يبلغنا بمسؤولية.'
            : 'Email <a href="mailto:privacy@adsranger.com">privacy@adsranger.com</a> with the steps to reproduce it, and please don\'t publish it before we fix it. We thank everyone who reports responsibly.' !!}</p>
    </div>
    <p style="margin-top:20px">{!! $ar
        ? 'لأي سؤال، <a href="'.$u('/contact').'">تواصل معنا</a>، أو اقرأ <a href="'.$u('/about').'">عن <bdi dir="ltr">AdsRanger</bdi></a>.'
        : 'Any question? <a href="'.$u('/contact').'">Contact us</a> or read <a href="'.$u('/about').'">about AdsRanger</a>.' !!}</p>
</section>
@endsection
