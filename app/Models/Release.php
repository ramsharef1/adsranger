<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * #76 In-app What's new release note (`releases` table, spec.md line 94).
 */
class Release extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'items' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->orderByDesc('date')->orderByDesc('id');
    }

    public function title(?string $locale = null): string
    {
        $loc = $locale ?? app()->getLocale();

        return $loc === 'ar'
            ? (trim((string) $this->title_ar) !== '' ? $this->title_ar : $this->title_en)
            : (trim((string) $this->title_en) !== '' ? $this->title_en : $this->title_ar);
    }

    /**
     * Normalised bullet lines for the active locale.
     * Supports either `['en' => [...], 'ar' => [...]]` or a list of `['en' => '...', 'ar' => '...']` rows.
     *
     * @return list<string>
     */
    public function itemsForLocale(?string $locale = null): array
    {
        $loc = $locale ?? app()->getLocale();
        $raw = is_array($this->items) ? $this->items : [];

        if (isset($raw[$loc]) && is_array($raw[$loc])) {
            return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $raw[$loc]), fn ($v) => $v !== ''));
        }

        $out = [];
        foreach ($raw as $row) {
            if (is_string($row) && trim($row) !== '') {
                $out[] = trim($row);
            } elseif (is_array($row)) {
                $text = $loc === 'ar'
                    ? (trim((string) ($row['ar'] ?? '')) !== '' ? $row['ar'] : ($row['en'] ?? ''))
                    : (trim((string) ($row['en'] ?? '')) !== '' ? $row['en'] : ($row['ar'] ?? ''));
                if (trim((string) $text) !== '') {
                    $out[] = trim((string) $text);
                }
            }
        }

        return $out;
    }

    /**
     * Published releases from the DB, or the built-in launch timeline when the table has no rows yet.
     *
     * @return Collection<int, self>
     */
    public static function feed(): Collection
    {
        $rows = static::query()->published()->limit(50)->get();
        if ($rows->isNotEmpty()) {
            return $rows;
        }

        return collect(static::defaults())->map(function (array $r): self {
            $m = new static([
                'version' => $r['version'],
                'title_en' => $r['title_en'],
                'title_ar' => $r['title_ar'],
                'items' => $r['items'],
                'is_published' => true,
            ]);
            $m->date = CarbonImmutable::parse($r['date']);

            return $m;
        });
    }

    /**
     * @return list<array{version: string, date: string, title_en: string, title_ar: string, items: array{en: list<string>, ar: list<string>}}>
     */
    public static function defaults(): array
    {
        return [
            [
                'version' => 'v1.4',
                'date' => '2026-11-05',
                'title_en' => 'Scheduled email reports, CSV/ZIP data exports & two-step verification',
                'title_ar' => 'التقارير الدورية بالبريد، وتصدير البيانات CSV/ZIP، والتحقق بخطوتين',
                'items' => [
                    'en' => [
                        'Weekly and monthly scheduled PDF reports emailed automatically to your client list.',
                        'Self-serve CSV exports for daily metrics, clients, and ad accounts, plus full workspace ZIP archive for owners.',
                        'Authenticator app two-step verification (TOTP) with recovery codes and optional workspace-wide enforcement.',
                    ],
                    'ar' => [
                        'إرسال التقارير الشهرية والأسبوعية بصيغة PDF تلقائيًا بالبريد الإلكتروني لكل عميل.',
                        'تصدير البيانات بصيغة CSV للنتائج اليومية والعملاء والحسابات الإعلانية، مع أرشيف ZIP كامل لمالك مساحة العمل.',
                        'التحقق بخطوتين عبر تطبيق المصادقة مع رموز الاسترداد وإمكانية إلزام جميع أعضاء الفريق.',
                    ],
                ],
            ],
            [
                'version' => 'v1.3',
                'date' => '2026-11-03',
                'title_en' => 'MENA season planner, landing page health & Stage 2 ad networks',
                'title_ar' => 'مخطِّط المواسم للمنطقة، وفحص صفحات الهبوط، ومنصات الإعلانات الإضافية',
                'items' => [
                    'en' => [
                        'MENA calendar with Hijri dates and Ramadan / Eid budget scenario planner.',
                        'Landing page health checks for broken, slow, redirecting, or insecure URLs behind your active ads.',
                        'Google Ads search terms, Quality Score, and Performance Max asset group insights.',
                    ],
                    'ar' => [
                        'تقويم المنطقة بالتواريخ الهجرية ومخطِّط سيناريوهات ميزانيات رمضان والأعياد.',
                        'فحص سلامة صفحات الهبوط لاكتشاف الروابط المعطّلة أو البطيئة أو غير الآمنة.',
                        'تحليلات عبارات البحث ونقاط الجودة ومجموعات أصول Performance Max في Google Ads.',
                    ],
                ],
            ],
        ];
    }
}
