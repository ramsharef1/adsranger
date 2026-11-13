<?php

namespace App\Support;

/**
 * /llms.txt (R2-48, D-263). Copy: Growth, claude/site/Site_Adds_R2-46-48_2026-11-12.md §1. The "Free tools" list follows the
 * published tools (config seo_tools + PublicSite::publicTools), so a renamed, held or new tool updates the file with no copy change.
 */
class LlmsTxt
{
    /** @var array<string, array{0: string, 1: string}> slug => [link name, note] in Growth's order */
    private const TOOL_NOTES = [
        'page-audit' => ['Arabic page SEO audit', 'Checks a page\'s title, description, Arabic right-to-left direction, structured data and keyword match.'],
        'hreflang-checker' => ['Hreflang checker', 'Checks a page\'s language links, return links, x-default and the Arabic page\'s direction.'],
        'arabic-keyword-generator' => ['Arabic keyword generator', 'Turns one word into its common Arabic forms: spellings, Arabizi, city names and questions.'],
        'arabizi-converter' => ['Arabizi to Arabic converter', 'Turns a word written in Arabizi (like 7elw) into its likely Arabic spellings.'],
        'serp-preview' => ['SERP preview', 'Shows how an Arabic or English title and description may look in search results, and where they get cut.'],
        'meta-length-checker' => ['Title and meta description length checker', 'Finds titles and descriptions that are too long, too short, duplicated or start with a Latin word.'],
        'robots-sitemap-checker' => ['Robots.txt, sitemap and canonical checker', 'Finds conflicts between robots.txt rules, declared sitemaps and canonical tags.'],
        'llms-txt-generator' => ['llms.txt generator', 'Drafts an llms.txt file and shows which AI crawlers a site\'s robots.txt allows.'],
        'schema-generator' => ['Schema generator', 'Builds Arabic and English JSON-LD for organisations, local businesses, FAQs, products and apps.'],
        'ad-pixel-checker' => ['Ad pixel and tag checker', 'Shows which ad pixels and tags a public page loads, such as Meta Pixel, GTM and GA4.'],
        'roas-calculator' => ['ROAS calculator', 'Works out return on ad spend, break-even ROAS and profit from your own numbers.'],
        'cod-poas-calculator' => ['COD profit and POAS calculator', 'Works out real profit for cash-on-delivery and buy-now-pay-later orders, with delivery rates, returns and fees.'],
        'cpm-cpc-cpa-calculator' => ['CPM, CPC, CTR and CPA calculator', 'Converts between budget, impressions, clicks, conversions and their costs.'],
        'budget-pacing-calculator' => ['Ad budget pacing calculator', 'Shows whether an account is spending to the month\'s plan and what to spend a day until month-end.'],
        'ab-significance-calculator' => ['A/B test significance calculator', 'Checks whether a split test result is likely real and how many visitors a test needs.'],
        'agency-fee-vat-calculator' => ['Agency fee and VAT calculator', 'Compares a fixed fee with a share of ad spend and adds VAT for the selected country.'],
        'utm-builder' => ['UTM link builder', 'Builds a campaign tracking link and warns about spaces and Arabic letters in the values.'],
        'whatsapp-link-generator' => ['WhatsApp link generator', 'Builds a Click-to-WhatsApp link with a pre-filled Arabic or English message, UTM tags and a QR code.'],
        'ad-mockup-preview' => ['Ad mockup preview', 'Previews Arabic and English ad text in feed, Reels, TikTok, Snapchat and Google search layouts.'],
        'safe-zone-checker' => ['9:16 safe-zone checker', 'Lays the Reels, TikTok and Snapchat screen areas over a vertical frame in your browser; nothing is uploaded.'],
        'gulf-ad-disclosure-checklist' => ['KSA and UAE ad disclosure checklist', 'A reminder checklist for common ad disclosures in Saudi Arabia and the UAE. It is not a legal check.'],
    ];

    public static function text(): string
    {
        $u = fn (string $path): string => 'https://adsranger.com/en'.$path;
        $published = PublicSite::publicTools();
        $toolLines = [];
        if (config('seo_tools.public')) {
            $toolLines[] = '- ['.'All free tools]('.$u('/tools').'): Free Arabic SEO and ad tools, no sign-up.';
        }
        foreach (self::TOOL_NOTES as $slug => [$name, $note]) {
            if (in_array($slug, $published, true)) {
                $toolLines[] = "- [{$name}](".$u('/tools/'.$slug)."): {$note}";
            }
        }
        foreach ($published as $slug) {
            if (! isset(self::TOOL_NOTES[$slug])) {
                $toolLines[] = '- ['.PublicSite::name('/tools/'.$slug, false).']('.$u('/tools/'.$slug).')';
            }
        }
        $toolLines[] = '- [Ramadan 2027 ad budget calculator]('.$u('/tools/ramadan-budget-calculator').'): Estimates a Ramadan budget, cost per result and results from your own numbers and assumptions.';

        $lines = [
            '# AdsRanger',
            '',
            '> AdsRanger is an Arabic-first platform for agencies in the Middle East and North Africa, for ads and search: every client\'s ad spend, results and search visibility in one workspace, with reports in Arabic and English. It is in development and launches in 2027. Today, adsranger.com offers free Arabic SEO and ad tools, planning guides and a MENA ad calendar.',
            '',
            'Notes for AI tools and readers:',
            '- Every page has an Arabic version at the same path without /en/ (for example https://adsranger.com/tools/roas-calculator). Arabic is the main language of the site.',
            '- The workspace (client reports, alerts, budget pacing, search tools for agencies) is in development. Pages under "Product (in development)" describe what is planned, not what is live.',
            '- Prices are not published yet; they will be shared at launch.',
            '- The free tools need no sign-up. Most run in the browser and store nothing; the checkers that open a URL only fetch the public page you enter.',
            '- Dates in the calendar and the Ramadan guide are expected dates with a source for each; Ramadan 2027 is expected to start on 8 February 2027.',
            '',
            '## Free tools',
            ...$toolLines,
            '',
            '## Guides and planning',
            '- [Ramadan 2027 advertising guide]('.$u('/ramadan-2027').'): A phase-by-phase Ramadan plan for agencies: prep dates, budget pacing, creative and reporting.',
            '- [MENA ad calendar 2027]('.$u('/mena-calendar').'): Key 2027 advertising dates across the region, with when to start planning and a source for each date.',
            '- [Open data]('.$u('/data').'): The MENA ad calendar 2027 as CSV and JSON under CC BY 4.0, with how to cite it.',
            ...(PublicSite::articlesPublic() ? ['- [Guides]('.$u('/guides').'): Guides for agencies on ad platforms, client reporting and budget pacing.'] : []),
            '- [Glossary]('.$u('/glossary').'): Ad and SEO terms with formulas and examples from the region.',
            '',
            '## Product (in development)',
            '- [Features]('.$u('/features').'): What AdsRanger is building for agencies.',
            '- [Client reports]('.$u('/reports').'): Planned client reports in Arabic and English, with read-only client links and PDF exports.',
            '- [Sample client report]('.$u('/demo-report').'): A sample report with demo data.',
            '- [Ad platforms]('.$u('/integrations').'): The ad platforms AdsRanger plans to connect through each network\'s official API.',
            '- [Roadmap]('.$u('/roadmap').'): What is live today, what is in development and what is planned for 2027.',
            '- [Plans]('.$u('/pricing').'): What each plan is planned to include. Prices are shared at launch.',
            '- [For agencies]('.$u('/solutions/agencies').')',
            '- [For freelancers]('.$u('/solutions/freelancers').')',
            '- [For business owners]('.$u('/solutions/business-owners').')',
            '- [Waitlist]('.$u('/waitlist').'): Join the launch list for 2027.',
            '',
            '## About and policies',
            '- [About]('.$u('/about').'): Who builds AdsRanger and why. Built in Amman, Jordan.',
            '- [Security]('.$u('/security').'): How agency and client data is protected; nothing changes in an ad account without a team member\'s approval.',
            '- [AdsRangerBot]('.$u('/bot').'): What our checker bot is, when it opens pages and how to block it.',
            '- [Accessibility]('.$u('/accessibility').'): What we do to make the site usable for everyone, and how to report a problem.',
            '- [Privacy]('.$u('/privacy').')',
            '- [Terms]('.$u('/terms').')',
            '- [Contact]('.$u('/contact').')',
            '',
            '## Optional',
            '- [Partners]('.$u('/partners').'): For agencies that want to help shape the product before launch.',
            '- [For clients of agencies]('.$u('/for-clients').'): How shared report links work for an agency\'s clients.',
        ];

        return implode("\n", $lines)."\n";
    }
}
