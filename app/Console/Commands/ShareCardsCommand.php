<?php

namespace App\Console\Commands;

use App\Support\ShareCard;
use Illuminate\Console\Command;

/**
 * R2-47: writes the share-card text for every public page (both languages) as JSON, checks Growth's rules (length limits,
 * an Arabic line opens and ends with an Arabic word), and exits 1 on a broken line so the copy is fixed, never truncated.
 * Render with: node scripts/og/render.mjs storage/app/share-cards.json public/brand/og
 */
class ShareCardsCommand extends Command
{
    protected $signature = 'site:share-cards {--out=storage/app/share-cards.json}';

    protected $description = 'Write and check the share-image text for every public page (R2-47)';

    /** @var array<string, array{0: int, 1: int}> slot => [Arabic max, English max] */
    public const LIMITS = ['eyebrow' => [32, 34], 'headline' => [42, 48], 'subline' => [60, 70]];

    public function handle(): int
    {
        $cards = [];
        $errors = [];
        foreach (ShareCard::paths() as $path) {
            foreach ([true, false] as $ar) {
                $card = ShareCard::card($path, $ar);
                foreach (self::LIMITS as $slot => [$arMax, $enMax]) {
                    $text = $card[$slot];
                    if (mb_strlen($text) > ($ar ? $arMax : $enMax)) {
                        $errors[] = "{$path} ".($ar ? 'ar' : 'en')." {$slot} too long: {$text}";
                    }
                    if ($ar && $text !== '' && ! self::opensAndEndsArabic($text)) {
                        $errors[] = "{$path} ar {$slot} opens or ends with Latin: {$text}";
                    }
                }
                $cards[] = ['slug' => ShareCard::slug($path), 'lang' => $ar ? 'ar' : 'en'] + $card;
            }
        }
        file_put_contents(base_path($this->option('out')), json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        foreach ($errors as $error) {
            $this->error($error);
        }
        $this->line(count($cards).' cards, '.count($errors).' errors');

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    public static function opensAndEndsArabic(string $text): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text);

        return $letters !== '' && preg_match('/^\p{Arabic}/u', $letters) === 1 && preg_match('/\p{Arabic}$/u', $letters) === 1;
    }
}
