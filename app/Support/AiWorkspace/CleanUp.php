<?php

namespace App\Support\AiWorkspace;

/**
 * AI Workspace M1 item 6 (spec v1 §15.1 step 1): the rule pass on every reply that reaches AdsRanger. No AI, our side
 * only. Hides filler (greetings, apologies, "as an AI", disclaimers, duplicate lines), folds generic advice, finds
 * actions and flags a reply with no fix. The original is never changed; the counts are real.
 */
class CleanUp
{
    /** Whole lines that carry nothing (AR + EN). */
    protected const FILLER = [
        '/^(sure|certainly|of course|absolutely|great question|good question)[!.,]?\b/iu',
        '/^(here (is|are)|below (is|are))\b.{0,60}:?$/iu',
        '/\b(as an ai|as a language model|i am an ai|i\'m an ai)\b/iu',
        '/^(i hope (this|that) helps|let me know if|feel free to|happy to help|hope this helps)/iu',
        '/^(i apologi[sz]e|sorry for|my apologies)/iu',
        '/^(please note that|disclaimer|it is important to note that|keep in mind that)\b.{0,80}(consult|professional|advice|vary)/iu',
        '/^(بالتأكيد|بكل سرور|سؤال رائع|سؤال ممتاز|إليك|فيما يلي)[!،.:]?/u',
        '/(بصفتي (نموذج|ذكاء)|كنموذج لغوي|أنا نموذج ذكاء)/u',
        '/^(آمل أن (يكون|يساعد)|أتمنى أن (يكون|يساعد)|لا تتردد في|أخبرني إذا)/u',
        '/^(أعتذر|عذرًا|عذرا)/u',
    ];

    /** Advice with no campaign, number or step («نصيحة عامة»). */
    protected const GENERIC = [
        '/^(-|\*|•)?\s*(always |make sure to |remember to |consider |try to )?(test|optimi[sz]e|monitor|focus on|improve) (your )?(ads|campaigns|creatives|targeting|performance)\.?$/iu',
        '/^(-|\*|•)?\s*(تأكد من|احرص على|ننصح ب)?\s*(تحسين|مراقبة|اختبار) (الإعلانات|الحملات|الأداء|الاستهداف)\.?$/u',
    ];

    /** A line that asks for a concrete step. */
    protected const ACTION = '/^(-|\*|•|\d+[.)])?\s*(pause|resume|raise|increase|lower|reduce|cut|move|shift|add|exclude|test|launch|duplicate|set|switch|أوقف|أعد تشغيل|ارفع|زد|خفّض|خفض|قلّل|انقل|أضف|استبعد|اختبر|أطلق|اضبط|حوّل)\b/iu';

    /**
     * @return array{text: string, hidden: int, generic: list<string>, actions: list<string>, no_fix: bool}
     */
    public static function pass(string $reply): array
    {
        $kept = [];
        $seen = [];
        $hidden = 0;
        $generic = [];
        $actions = [];
        foreach (preg_split('/\R/u', $reply) as $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }
            $plain = trim(preg_replace('/^[#>*\-•\s]+|\*\*/u', '', $line));
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $plain));
            if (isset($seen[$key]) || static::matchesAny($plain, self::FILLER)) {
                $hidden++;

                continue;
            }
            $seen[$key] = true;
            if (static::matchesAny($line, self::GENERIC)) {
                $generic[] = $plain;
                $hidden++;

                continue;
            }
            if (preg_match(self::ACTION, $line)) {
                $actions[] = $plain;
            }
            $kept[] = $line;
        }

        return ['text' => implode("\n", $kept), 'hidden' => $hidden, 'generic' => $generic, 'actions' => $actions, 'no_fix' => $actions === []];
    }

    /** @param list<string> $patterns */
    protected static function matchesAny(string $line, array $patterns): bool
    {
        foreach ($patterns as $p) {
            if (preg_match($p, $line)) {
                return true;
            }
        }

        return false;
    }
}
