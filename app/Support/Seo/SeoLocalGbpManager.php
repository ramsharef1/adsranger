<?php

namespace App\Support\Seo;

use App\Models\Client;
use App\Models\SeoLocalLocation;
use App\Support\TextClean;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * E2-070: Multi-branch Local SEO & Google Business Profile manager:
 * RTL/E.164 NAP & hours consistency checker, contract-tested GBP reviews sync, and bilingual reply drafts.
 */
class SeoLocalGbpManager
{
    public const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /**
     * Audits bilingual NAP & business hours for RTL bidi issues, Eastern digits, E.164 formatting, and invalid hours.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{code: string, field: string, detail: string}>
     */
    public static function auditNap(array $data): array
    {
        $issues = [];
        $rawPhone = (string) ($data['phone_e164'] ?? '');
        $nameAr = trim((string) ($data['name_ar'] ?? ''));
        $nameEn = trim((string) ($data['name_en'] ?? ''));
        $addrAr = trim((string) ($data['address_ar'] ?? ''));
        $addrEn = trim((string) ($data['address_en'] ?? ''));
        $hours = is_array($data['hours_json'] ?? null) ? $data['hours_json'] : [];

        if (preg_match('/[٠-٩۰-۹]/u', $rawPhone) || TextClean::hasInvisible($rawPhone)) {
            $issues[] = [
                'code' => 'rtl_phone_digits_or_bidi',
                'field' => 'phone_e164',
                'detail' => 'Phone contains Eastern digits or invisible bidi marks that reverse in RTL layouts.',
            ];
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', trim($rawPhone))) {
            $issues[] = [
                'code' => 'invalid_e164_phone',
                'field' => 'phone_e164',
                'detail' => 'Phone must follow strict E.164 (+countrycode and Western digits without spaces) for tel: and GBP consistency.',
            ];
        }

        if ($nameAr === '' || $nameEn === '') {
            $issues[] = [
                'code' => 'missing_bilingual_name',
                'field' => 'name',
                'detail' => 'Both Arabic and English branch names are required for bilingual local listings.',
            ];
        }

        if ($addrAr === '' || $addrEn === '') {
            $issues[] = [
                'code' => 'missing_bilingual_address',
                'field' => 'address',
                'detail' => 'Both Arabic and English branch street addresses are required.',
            ];
        }

        if ($hours === []) {
            $issues[] = [
                'code' => 'missing_hours',
                'field' => 'hours_json',
                'detail' => 'Operating hours are not specified.',
            ];
        } else {
            foreach ($hours as $day => $slot) {
                $slotStr = is_array($slot) ? (($slot['open'] ?? '').'-'.($slot['close'] ?? '')) : (string) $slot;
                if (preg_match('/[٠-٩۰-۹]/u', $slotStr)) {
                    $issues[] = [
                        'code' => 'rtl_hours_digits',
                        'field' => 'hours_json.'.$day,
                        'detail' => "Day {$day} uses Eastern digits in operating hours.",
                    ];

                    continue;
                }
                if (strtolower(trim($slotStr)) === 'closed' || $slotStr === '-') {
                    continue;
                }
                if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d-([01]\d|2[0-3]):[0-5]\d$/', trim($slotStr), $m)) {
                    $issues[] = [
                        'code' => 'invalid_hours_range',
                        'field' => 'hours_json.'.$day,
                        'detail' => "Day {$day} hours should be 24h HH:MM-HH:MM or closed.",
                    ];
                } elseif ($m[2] <= $m[1] && empty($slot['overnight'])) {
                    $issues[] = [
                        'code' => 'inverted_hours_range',
                        'field' => 'hours_json.'.$day,
                        'detail' => "Day {$day} closing hour is earlier than or equal to opening hour.",
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function saveLocation(Client $client, array $attributes, ?int $locationId = null): SeoLocalLocation
    {
        $issues = self::auditNap($attributes);
        $cleanPhone = preg_replace('/[^\d+]/', '', (string) TextClean::western(TextClean::invisible((string) ($attributes['phone_e164'] ?? '')))) ?? '';

        $payload = [
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'name_ar' => mb_substr(trim((string) ($attributes['name_ar'] ?? '')), 0, 190),
            'name_en' => mb_substr(trim((string) ($attributes['name_en'] ?? '')), 0, 190),
            'phone_e164' => mb_substr($cleanPhone !== '' ? $cleanPhone : trim((string) ($attributes['phone_e164'] ?? '')), 0, 32),
            'address_ar' => mb_substr(trim((string) ($attributes['address_ar'] ?? '')), 0, 300),
            'address_en' => mb_substr(trim((string) ($attributes['address_en'] ?? '')), 0, 300),
            'hours_json' => is_array($attributes['hours_json'] ?? null) ? $attributes['hours_json'] : null,
            'gbp_location_id' => ! empty($attributes['gbp_location_id']) ? mb_substr(trim((string) $attributes['gbp_location_id']), 0, 120) : null,
            'nap_issues' => $issues,
            'reviews_json' => is_array($attributes['reviews_json'] ?? null) ? $attributes['reviews_json'] : [],
        ];

        if ($locationId !== null) {
            $loc = SeoLocalLocation::where('agency_id', $client->agency_id)->where('client_id', $client->id)->find($locationId);
            if ($loc) {
                $loc->update($payload);

                return $loc;
            }
        }

        return SeoLocalLocation::create($payload);
    }

    /**
     * Generates deterministic bilingual review reply drafts (labelled as draft templates for team review).
     *
     * @return array{draft_ar: string, draft_en: string, sentiment: string}
     */
    public static function draftBilingualReviewReply(
        string $reviewerName,
        int $rating,
        string $comment = '',
        string $branchAr = '',
        string $branchEn = '',
    ): array {
        $name = trim($reviewerName) !== '' ? trim($reviewerName) : 'عميلنا الكريم';
        $nameEn = trim($reviewerName) !== '' ? trim($reviewerName) : 'Valued guest';
        $placeAr = trim($branchAr) !== '' ? trim($branchAr) : 'فرعنا';
        $placeEn = trim($branchEn) !== '' ? trim($branchEn) : 'our branch';

        if ($rating >= 4) {
            return [
                'sentiment' => 'positive',
                'draft_ar' => "مسودة للرد: شكرًا جزيلًا {$name} على تقييمك لزيارتك في {$placeAr}. يسعدنا رضاك ونتطلع لخدمتك مجددًا.",
                'draft_en' => "Draft reply: Thank you {$nameEn} for rating your visit to {$placeEn}. We appreciate your feedback and look forward to welcoming you again.",
            ];
        }

        if ($rating === 3) {
            return [
                'sentiment' => 'neutral',
                'draft_ar' => "مسودة للرد: شكرًا {$name} لمشاركتنا ملاحظاتك حول {$placeAr}. نعمل على تحسين التجربة باستمرار ونسعد بتواصلك المباشر معنا.",
                'draft_en' => "Draft reply: Thank you {$nameEn} for sharing your notes on {$placeEn}. We are reviewing the details to improve your next visit.",
            ];
        }

        return [
            'sentiment' => 'attention_needed',
            'draft_ar' => "مسودة للرد: نعتذر منك {$name} عن أي قصور واجهته في {$placeAr}. نرجو التواصل مع إدارة الفرع مباشرة لمعالجة الأمر فورًا.",
            'draft_en' => "Draft reply: We apologize {$nameEn} that your experience at {$placeEn} fell short. Please reach our branch manager directly so we can resolve this promptly.",
        ];
    }

    /**
     * Contract-tested Google Business Profile reviews sync (never fakes success when unconfigured).
     *
     * @return array{ok: bool, status: string, synced_count: int}
     */
    public static function syncGbpReviews(SeoLocalLocation $location, ?string $accessToken): array
    {
        $gbpId = trim((string) $location->gbp_location_id);
        $token = trim((string) $accessToken);
        if ($gbpId === '' || $token === '') {
            return ['ok' => false, 'status' => 'unconfigured', 'synced_count' => 0];
        }

        $url = 'https://mybusiness.googleapis.com/v4/'.ltrim($gbpId, '/').'/reviews';

        try {
            $res = Http::withToken($token)->acceptJson()->timeout(15)->get($url);
        } catch (Throwable) {
            return ['ok' => false, 'status' => 'failed', 'synced_count' => 0];
        }

        if (! $res->successful()) {
            return ['ok' => false, 'status' => 'failed', 'synced_count' => 0];
        }

        $payload = $res->json();
        $rawReviews = is_array($payload) ? (array) ($payload['reviews'] ?? []) : [];
        $mapStar = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];

        $stored = [];
        foreach ($rawReviews as $r) {
            if (! is_array($r)) {
                continue;
            }
            $reviewer = (string) ($r['reviewer']['displayName'] ?? 'Guest');
            $ratingRaw = $r['starRating'] ?? 5;
            $rating = is_numeric($ratingRaw) ? (int) $ratingRaw : ($mapStar[strtoupper((string) $ratingRaw)] ?? 5);
            $comment = (string) ($r['comment'] ?? '');
            $drafts = self::draftBilingualReviewReply($reviewer, $rating, $comment, $location->name_ar, $location->name_en);

            $stored[] = [
                'review_id' => (string) ($r['reviewId'] ?? uniqid('rev_', true)),
                'reviewer' => $reviewer,
                'rating' => $rating,
                'comment' => $comment,
                'draft_ar' => $drafts['draft_ar'],
                'draft_en' => $drafts['draft_en'],
            ];
        }

        $location->update(['reviews_json' => $stored]);

        return ['ok' => true, 'status' => 'synced', 'synced_count' => count($stored)];
    }
}
