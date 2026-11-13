<?php

namespace Tests\Feature;

use App\Support\Seo\PublicCatalog;
use Tests\TestCase;

/** O-394 / O-413: glossary titles «ما هو / ما هي» by gender and number, each ≤ 60 characters with " | AdsRanger". */
class GlossaryHeadingTest extends TestCase
{
    public function test_every_glossary_title_fits_and_uses_the_right_form(): void
    {
        foreach (PublicCatalog::glossary() as $slug => $item) {
            foreach ([true, false] as $ar) {
                $h = PublicCatalog::glossaryHeading($item, $ar, $slug);
                $this->assertLessThanOrEqual(60, mb_strlen($h.' | AdsRanger'), "$slug ".($ar ? 'ar' : 'en'));
            }
        }
        $g = PublicCatalog::glossary();
        $this->assertStringStartsWith('ما هي تكلفة', PublicCatalog::glossaryHeading($g['cpr'], true, 'cpr'));
        $this->assertStringStartsWith('ما هو العائد', PublicCatalog::glossaryHeading($g['roas'], true, 'roas'));
        $this->assertStringStartsWith('ما هي المقارنات', PublicCatalog::glossaryHeading($g['benchmarks'], true, 'benchmarks'));
        $this->assertSame([], array_diff(PublicCatalog::AR_FEMININE, array_keys($g)), 'every listed slug exists');
    }
}
