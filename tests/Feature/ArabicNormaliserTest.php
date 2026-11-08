<?php

namespace Tests\Feature;

use App\Support\Arabic\Normaliser;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * SEO slice 0 step 0.1 (Product SEO_Slice0_Spec): the Arabic text layer, its must-match / must-not-match fixtures
 * and PHP ↔ JS parity. Fixtures live in tests/fixtures/arabic-seo/.
 */
#[Group('arabic-seo')]
class ArabicNormaliserTest extends TestCase
{
    /** @return list<array<string, string>> */
    private function csv(string $file): array
    {
        $rows = array_map('str_getcsv', file(base_path('tests/fixtures/arabic-seo/'.$file), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $head = array_shift($rows);

        return array_map(fn ($r) => array_combine($head, $r), $rows);
    }

    public function test_must_match_spelling_pairs_all_match(): void
    {
        $rows = $this->csv('must_match_spelling.csv');
        $this->assertGreaterThanOrEqual(106, count($rows));
        foreach ($rows as $row) {
            $this->assertTrue(Normaliser::sameKeyword($row['a'], $row['b']), $row['a'].' ≠ '.$row['b']);
        }
    }

    public function test_must_not_match_pairs_never_merge(): void
    {
        foreach ($this->csv('must_not_match.csv') as $row) {
            $this->assertFalse(Normaliser::sameKeyword($row['a'], $row['b'], $row['country'] ?: null), $row['a'].' = '.$row['b'].' ('.$row['rule'].')');
        }
    }

    public function test_key_rules(): void
    {
        $this->assertSame('اعلانات مموله', Normaliser::key('  إعلاناتٌ   مُمَوَّلة '));
        $this->assertSame('رمضان 2027', Normaliser::key('رمضان ٢٠٢٧'));
        $this->assertSame('google ads', Normaliser::key('Google ADS'));
        $this->assertSame('اعلانات', Normaliser::key('إعـــلانات'));
    }

    public function test_variants_arabizi_dialects_cities_and_ratio(): void
    {
        $texts = array_column(Normaliser::variants('أريد مطعم'), 'text');
        $this->assertContains('أبغى مطعم', $texts);
        $this->assertContains('عايز مطعم', $texts);
        $this->assertContains('اريد مطعم', $texts);
        $this->assertSame(['candidate'], array_values(array_unique(array_column(Normaliser::variants('أريد مطعم'), 'status'))));

        $this->assertSame(['ق', 'غ'], Normaliser::arabiziToArabic('8'));
        $this->assertContains('حلو', Normaliser::arabiziToArabic('7elw'));
        $this->assertContains('حبيبي', Normaliser::arabiziToArabic('habibi'));
        $this->assertContains('مطعم', Normaliser::arabiziToArabic('mat3am'));
        $this->assertContains('قهوة', Normaliser::arabiziToArabic('8ahwa'));
        $this->assertContains('عيادة اسنان', Normaliser::arabiziToArabic('3yadat asnan'));

        $this->assertSame(['3yada 2asnan', 'aiada asnan'], array_slice(Normaliser::arabicToArabizi('عيادة أسنان'), 0, 2));
        $this->assertSame(['gulf'], Normaliser::dialectTags('أبغى جوال'));
        $this->assertSame(['egypt', 'maghreb'], Normaliser::dialectTags('المطعم فين'));
        $this->assertSame([], Normaliser::dialectTags('أين المطعم'));

        $this->assertSame(['city' => 'جدة', 'country' => 'SA'], Normaliser::cityOf('مطاعم جده'));
        $this->assertSame(['city' => 'عمان', 'country' => 'JO'], Normaliser::cityOf('فنادق عمّان'));
        $this->assertNull(Normaliser::cityOf('فنادق عمان'));
        $this->assertSame('JO', Normaliser::cityOf('فنادق عمان', 'JO')['country']);
        $this->assertNull(Normaliser::cityOf('فنادق عُمان', 'JO'));

        $this->assertSame(1.0, Normaliser::tashkeelRatio('اَلْعَرَبِيَّةُ لُغَةٌ'));
        $this->assertSame(0.5, Normaliser::tashkeelRatio('إعلاناتٌ ممولة'));
        $this->assertSame('2026-11-08', Normaliser::rules()['checked_on']);
    }

    public function test_php_and_js_give_identical_results(): void
    {
        $node = trim((string) shell_exec('command -v node'));
        if ($node === '') {
            $this->markTestSkipped('node is not installed');
        }
        $lines = [];
        foreach ($this->csv('must_match_spelling.csv') as $r) {
            $lines[] = [$r['a'], '', $r['b']];
        }
        foreach ($this->csv('must_not_match.csv') as $r) {
            $lines[] = [$r['a'], $r['country'], $r['b']];
        }
        foreach (file(base_path('tests/fixtures/arabic-seo/parity_extra.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $t) {
            $lines[] = [$t, 'JO'];
            $lines[] = [$t, ''];
        }

        $process = new Process([$node, base_path('tests/arabic/parity.mjs')], base_path());
        $process->setInput(json_encode($lines, JSON_UNESCAPED_UNICODE));
        $process->mustRun();
        $js = json_decode($process->getOutput(), true);

        $pairs = [];
        foreach ($lines as $i => [$text, $country]) {
            $php = [
                'key' => Normaliser::key($text),
                'variants' => Normaliser::variants($text),
                'arabizi' => Normaliser::arabiziToArabic($text),
                'toArabizi' => Normaliser::arabicToArabizi($text),
                'dialects' => Normaliser::dialectTags($text),
                'city' => Normaliser::cityOf($text, $country ?: null),
                'ratio' => number_format(Normaliser::tashkeelRatio($text), 4, '.', ''),
            ];
            $this->assertSame($php, $js['out'][$i], 'parity: '.$text);
            if (isset($lines[$i][2])) {
                $pairs[] = Normaliser::sameKeyword($text, $lines[$i][2], $country ?: null);
            }
        }
        $this->assertSame($pairs, $js['pairs']);
    }
}
