<?php

/*
 * BX-01 (B-43 / B-44): open datasets hosted on adsranger.com/data, so links and citations come to our own domain. The
 * files in public/data are the exact published release (GitHub is a mirror). DatasetsTest fails if the source config
 * changes without a new release here.
 */
return [
    'mena-ad-calendar-2027' => [
        'title_ar' => 'تقويم المواسم الإعلانية 2027',
        'title_en' => 'MENA ad calendar 2027',
        'about_ar' => 'أهم مواعيد الإعلان في الشرق الأوسط وشمال أفريقيا لعام 2027: رمضان والأعياد والأيام الوطنية ومواسم التخفيضات، مع موعد بدء التخطيط ومصدر لكل موعد.',
        'about_en' => 'Key 2027 advertising dates across the Middle East and North Africa: Ramadan, Eid, national days and sale seasons, with when to start planning and a source for every date.',
        'version' => '1.0',
        'released' => '2026-11-08',
        'rows' => 33,
        'license' => ['name' => 'CC BY 4.0', 'url' => 'https://creativecommons.org/licenses/by/4.0/'],
        'source_path' => '/mena-calendar',
        'mirror' => 'https://github.com/ramsharef1/mena-ad-calendar-2027',
        'coverage' => ['from' => '2026-12-01', 'to' => '2027-12-31', 'place' => 'Middle East and North Africa'],
        'files' => [
            ['csv', '/data/mena-ad-calendar-2027.csv', 'text/csv', 'CSV (UTF-8, Excel-ready)'],
            ['json', '/data/mena-ad-calendar-2027.json', 'application/json', 'JSON'],
            ['readme', '/data/mena-ad-calendar-2027-README.md', 'text/markdown', 'README'],
            ['license', '/data/mena-ad-calendar-2027-LICENSE.txt', 'text/plain', 'LICENSE'],
        ],
        'changelog' => [
            ['2026-11-08', '1.0', 'أول إصدار: 33 موعدًا بمصادرها.', 'First release: 33 dates with sources.'],
        ],
    ],
];
