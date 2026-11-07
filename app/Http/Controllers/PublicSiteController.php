<?php

namespace App\Http\Controllers;

use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicSiteController extends Controller
{
    public function waitlist(string $lang = 'ar')
    {
        return view('public.waitlist', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function submitWaitlist(Request $request, string $lang = 'ar')
    {
        $lang = $lang === 'en' ? 'en' : 'ar';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'agency_name' => ['nullable', 'string', 'max:140'],
            'email' => ['required', 'email:filter', 'max:190'],
            'monthly_spend' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        WaitlistEntry::updateOrCreate(
            ['email' => strtolower(trim($data['email']))],
            [
                'name' => trim($data['name']),
                'agency_name' => isset($data['agency_name']) ? trim((string) $data['agency_name']) : null,
                'monthly_spend' => $data['monthly_spend'] ?? null,
                'lang' => $lang,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
            ]
        );

        $target = $lang === 'en' ? '/en/waitlist' : '/waitlist';

        return redirect($target)->with('waitlist_saved', true);
    }

    public function ramadan(string $lang = 'ar')
    {
        return view('public.ramadan-2027', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function pricing(string $lang = 'ar')
    {
        return view('public.pricing', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function integrations(string $lang = 'ar')
    {
        return view('public.integrations', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function demoReport(string $lang = 'ar')
    {
        return view('public.demo-report', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function menaCalendar(string $lang = 'ar')
    {
        return view('public.mena-calendar', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function security(string $lang = 'ar')
    {
        return view('public.security', ['lang' => $lang === 'en' ? 'en' : 'ar']);
    }

    public function sitemap(): Response
    {
        $urls = [
            ['https://adsranger.com/', 'https://adsranger.com/en'],
            ['https://adsranger.com/pricing', 'https://adsranger.com/en/pricing'],
            ['https://adsranger.com/integrations', 'https://adsranger.com/en/integrations'],
            ['https://adsranger.com/demo-report', 'https://adsranger.com/en/demo-report'],
            ['https://adsranger.com/mena-calendar', 'https://adsranger.com/en/mena-calendar'],
            ['https://adsranger.com/ramadan-2027', 'https://adsranger.com/en/ramadan-2027'],
            ['https://adsranger.com/waitlist', 'https://adsranger.com/en/waitlist'],
            ['https://adsranger.com/security', 'https://adsranger.com/en/security'],
            ['https://adsranger.com/privacy', 'https://adsranger.com/en/privacy'],
            ['https://adsranger.com/terms', 'https://adsranger.com/en/terms'],
            ['https://adsranger.com/data-deletion', 'https://adsranger.com/en/data-deletion'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach ($urls as [$arUrl, $enUrl]) {
            foreach ([$arUrl, $enUrl] as $loc) {
                $xml .= "  <url>\n"
                      . "    <loc>{$loc}</loc>\n"
                      . "    <xhtml:link rel=\"alternate\" hreflang=\"ar\" href=\"{$arUrl}\"/>\n"
                      . "    <xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"{$enUrl}\"/>\n"
                      . "  </url>\n";
            }
        }
        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
