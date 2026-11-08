<?php

namespace App\Support;

/** Shared head data for the free tool pages (title, meta, schema, privacy line), so each page only sets its own copy. */
class ToolPage
{
    /**
     * @param  array{0: string, 1: string}  $titles  [AR, EN]
     * @param  array{0: string, 1: string}  $metas  [AR, EN]
     * @return array{ar: bool, lang: string, arPath: string, title: string, meta: string, schema: list<array<string, mixed>>, privacy: string}
     */
    public static function head(string $lang, string $arPath, array $titles, array $metas): array
    {
        $ar = $lang !== 'en';
        $lang = $ar ? 'ar' : 'en';
        $title = $ar ? $titles[0] : $titles[1];
        $selfUrl = PublicUrl::absolute($arPath, $lang);

        return [
            'ar' => $ar,
            'lang' => $lang,
            'arPath' => $arPath,
            'title' => $title,
            'meta' => $ar ? $metas[0] : $metas[1],
            'schema' => [
                ['@type' => 'WebPage', 'name' => $title, 'url' => $selfUrl, 'inLanguage' => $lang],
                ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => $ar ? 'الرئيسية' : 'Home', 'item' => PublicUrl::absolute('/', $lang)],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => PublicSite::name($arPath, $ar), 'item' => $selfUrl],
                ]],
            ],
            'privacy' => $ar ? 'دون تسجيل، ولا نحفظ أي شيء تدخله.' : 'No sign-up. Nothing you enter is stored.',
        ];
    }
}
