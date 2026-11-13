<?php

namespace App\Support\AiWorkspace;

use App\Models\Agency;
use App\Models\AiPrompt;

/**
 * WS-32 (D-301): the agency's saved AI box prompts, plus a fixed Arabic starter set. A prompt is the person's words for
 * one playbook; our rules, context and output schema still come first (Playbooks::envelope), so it can't replace them.
 */
class PromptLibrary
{
    /** @return list<array{key: string, title: string, playbook: string, body: string}> */
    public static function starters(): array
    {
        return [
            ['key' => 's1', 'title' => 'ركّز على تكلفة العميل المحتمل', 'playbook' => 'weekly_summary', 'body' => 'ركّز على تكلفة العميل المحتمل وعدد الطلبات من واتساب، واقترح خطوة واحدة لهذا الأسبوع.'],
            ['key' => 's2', 'title' => 'عرض نهاية الشهر', 'playbook' => 'dialect_ad_copy', 'body' => 'العرض: خصم 20% حتى نهاية الشهر. لا تذكر الأسعار. الدعوة: احجز موعدك عبر واتساب.'],
            ['key' => 's3', 'title' => 'رمضان بميزانية ثابتة', 'playbook' => 'ramadan_plan', 'body' => 'الميزانية الشهرية ثابتة. نريد تركيزًا أكبر على العشر الأواخر والعيد.'],
            ['key' => 's4', 'title' => 'سرد شهري للإدارة', 'playbook' => 'report_narrative', 'body' => 'القارئ مدير غير متخصص في الإعلانات. لغة بسيطة وجمل قصيرة.'],
        ];
    }

    /** @return list<array{key: string, title: string, playbook: string, body: string, starter: bool, id: ?int}> starters first, then the agency's own */
    public static function for(Agency $agency): array
    {
        $own = AiPrompt::where('agency_id', $agency->id)->orderBy('title')->limit(100)->get()
            ->map(fn (AiPrompt $p) => ['key' => 'p'.$p->id, 'title' => $p->title, 'playbook' => (string) $p->playbook, 'body' => $p->body, 'starter' => false, 'id' => $p->id])->all();

        return [...array_map(fn ($s) => $s + ['starter' => true, 'id' => null], static::starters()), ...$own];
    }

    /** @return array{key: string, title: string, playbook: string, body: string, starter: bool, id: ?int}|null */
    public static function find(Agency $agency, string $key): ?array
    {
        return collect(static::for($agency))->firstWhere('key', $key);
    }
}
