<?php

/*
 * #127 Network catalog: networks that are planned but not built yet (no row in ad_networks).
 * Source: ADSR-060 rows #117–#126. Shown as "Planned" with no date (claims checklist).
 * category uses the same labels as config/ad_networks.php.
 */
return [
    'planned' => [
        'yandex_direct' => ['name' => 'Yandex Direct', 'category' => 'Search'],
        'naver' => ['name' => 'Naver Search Ads', 'category' => 'Search'],
        'baidu' => ['name' => 'Baidu Ads', 'category' => 'Search'],
        'telegram' => ['name' => 'Telegram Ads', 'category' => 'Social'],
        'youtube' => ['name' => 'YouTube (reported on its own)', 'category' => 'Social'],
        'arabyads' => ['name' => 'ArabyAds', 'category' => 'Native'],
        'anghami' => ['name' => 'Anghami Ads', 'category' => 'Audio & video'],
        'noon' => ['name' => 'Noon Ads', 'category' => 'Retail'],
        'talabat' => ['name' => 'Talabat Ads', 'category' => 'Retail'],
        'careem' => ['name' => 'Careem Ads', 'category' => 'Retail'],
        'dv360' => ['name' => 'Display & Video 360', 'category' => 'Programmatic'],
        'the_trade_desk' => ['name' => 'The Trade Desk', 'category' => 'Programmatic'],
        'amazon_dsp' => ['name' => 'Amazon DSP', 'category' => 'Programmatic'],
        'stackadapt' => ['name' => 'StackAdapt', 'category' => 'Programmatic'],
        'propellerads' => ['name' => 'PropellerAds', 'category' => 'Push & pop'],
        'adsterra' => ['name' => 'Adsterra', 'category' => 'Push & pop'],
        'richads' => ['name' => 'RichAds', 'category' => 'Push & pop'],
        'zeropark' => ['name' => 'Zeropark', 'category' => 'Push & pop'],
        'popads' => ['name' => 'PopAds', 'category' => 'Push & pop'],
        'clickadu' => ['name' => 'Clickadu', 'category' => 'Push & pop'],
        'evadav' => ['name' => 'Evadav', 'category' => 'Push & pop'],
        'hilltopads' => ['name' => 'HilltopAds', 'category' => 'Push & pop'],
        'applovin' => ['name' => 'AppLovin', 'category' => 'Mobile apps'],
        'unity_ads' => ['name' => 'Unity Ads', 'category' => 'Mobile apps'],
        'ironsource' => ['name' => 'ironSource', 'category' => 'Mobile apps'],
        'mintegral' => ['name' => 'Mintegral', 'category' => 'Mobile apps'],
        'moloco' => ['name' => 'Moloco', 'category' => 'Mobile apps'],
        'appsflyer' => ['name' => 'AppsFlyer', 'category' => 'Mobile apps'],
        'adjust' => ['name' => 'Adjust', 'category' => 'Mobile apps'],
        'spotify' => ['name' => 'Spotify Ads', 'category' => 'Audio & video'],
        'shahid' => ['name' => 'Shahid (MBC)', 'category' => 'Audio & video'],
        'osn' => ['name' => 'OSN+', 'category' => 'Audio & video'],
        'admitad' => ['name' => 'Admitad', 'category' => 'Affiliate'],
        'arabclicks' => ['name' => 'ArabClicks', 'category' => 'Affiliate'],
        'impact' => ['name' => 'Impact', 'category' => 'Affiliate'],
        'cj' => ['name' => 'CJ Affiliate', 'category' => 'Affiliate'],
    ],

    // Order of the category sections on the page.
    'categories' => ['Social', 'Search', 'Analytics', 'Retail', 'Native', 'Programmatic', 'Push & pop', 'Mobile apps', 'Audio & video', 'Affiliate'],
];
