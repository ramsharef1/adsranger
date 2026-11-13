<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/** NB-22 / XS-37 (R4): a printable Arabic or English client intake questionnaire with the agency's name (not ours). */
class IntakeFormPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Client intake form';

    protected static ?string $title = 'Client intake form';

    protected static ?string $slug = 'intake-form';

    protected static ?int $navigationSort = 25;

    protected string $view = 'filament.pages.intake-form';

    public string $lang = 'ar';

    /** @var list<string> */
    public array $sections = ['business', 'goals', 'budget', 'offers', 'brand', 'licences', 'access', 'contacts'];

    public function mount(): void
    {
        $this->lang = app()->getLocale() === 'en' ? 'en' : 'ar';
    }

    /** @return array<string, array{ar: string, en: string, q: list<array{ar: string, en: string}>}> */
    public static function catalog(): array
    {
        return [
            'business' => ['ar' => 'عن النشاط', 'en' => 'About the business', 'q' => [
                ['ar' => 'اسم النشاط كما يظهر للعملاء', 'en' => 'Business name as customers see it'], ['ar' => 'الدول والمدن التي تخدمونها', 'en' => 'Countries and cities you serve'],
                ['ar' => 'أهم 3 منتجات أو خدمات', 'en' => 'Top 3 products or services'], ['ar' => 'من هو عميلكم المثالي؟', 'en' => 'Who is your ideal customer?']]],
            'goals' => ['ar' => 'الأهداف', 'en' => 'Goals', 'q' => [
                ['ar' => 'ما النتيجة التي تهمكم أكثر (مبيعات، حجوزات، رسائل WhatsApp، مكالمات)؟', 'en' => 'Which result matters most (sales, bookings, WhatsApp chats, calls)?'],
                ['ar' => 'كم نتيجة تريدون شهريًا؟', 'en' => 'How many results a month?'], ['ar' => 'ما التكلفة المقبولة للنتيجة الواحدة؟', 'en' => 'What cost per result is acceptable?']]],
            'budget' => ['ar' => 'الميزانية', 'en' => 'Budget', 'q' => [
                ['ar' => 'الميزانية الإعلانية الشهرية وعملتها', 'en' => 'Monthly ad budget and its currency'], ['ar' => 'المواسم التي ترفعون فيها الميزانية (رمضان، العيد، الجمعة البيضاء…)', 'en' => 'Seasons when you raise the budget (Ramadan, Eid, White Friday…)']]],
            'offers' => ['ar' => 'العروض', 'en' => 'Offers', 'q' => [
                ['ar' => 'العروض الحالية وتاريخ انتهائها', 'en' => 'Current offers and when they end'], ['ar' => 'هل يُسمح بذكر الأسعار في الإعلانات؟', 'en' => 'May ads mention prices?'],
                ['ar' => 'هل تقدمون الدفع عند الاستلام؟ وما نسبة الطلبات المرتجعة تقريبًا؟', 'en' => 'Do you offer cash on delivery? Roughly what share of orders is returned?']]],
            'brand' => ['ar' => 'العلامة واللغة', 'en' => 'Brand and language', 'q' => [
                ['ar' => 'اللهجة المفضلة (خليجية، شامية، مصرية، فصحى…)', 'en' => 'Preferred dialect (Gulf, Levantine, Egyptian, MSA…)'], ['ar' => 'كلمات نستخدمها دائمًا', 'en' => 'Words to always use'],
                ['ar' => 'كلمات أو ادعاءات نتجنبها', 'en' => 'Words or claims to avoid']]],
            'licences' => ['ar' => 'التراخيص', 'en' => 'Licences', 'q' => [
                ['ar' => 'أرقام التراخيص التي يجب أن تظهر في الإعلانات وتاريخ انتهائها', 'en' => 'Licence numbers the ads must show, and when they expire']]],
            'access' => ['ar' => 'الحسابات والوصول', 'en' => 'Accounts and access', 'q' => [
                ['ar' => 'الحسابات الإعلانية التي سنديرها (Meta، Google Ads، TikTok، Snapchat)', 'en' => 'Ad accounts we will manage (Meta, Google Ads, TikTok, Snapchat)'],
                ['ar' => 'هل يوجد Pixel أو Google tag على الموقع؟', 'en' => 'Is there a Pixel or Google tag on the website?'], ['ar' => 'رابط الموقع وصفحات الهبوط', 'en' => 'Website and landing page links']]],
            'contacts' => ['ar' => 'التواصل', 'en' => 'Contacts', 'q' => [
                ['ar' => 'الشخص المسؤول عن الموافقات', 'en' => 'Who approves ads and budgets'], ['ar' => 'من يستلم التقارير وبأي لغة', 'en' => 'Who receives the reports, and in which language'],
                ['ar' => 'أفضل وقت ووسيلة للتواصل', 'en' => 'Best time and way to reach you']]],
        ];
    }

    protected function getViewData(): array
    {
        return ['catalog' => static::catalog(), 'agency' => Filament::getTenant()];
    }
}
