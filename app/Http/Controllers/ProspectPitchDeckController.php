<?php

namespace App\Http\Controllers;

use App\Models\ProspectPitchDeck;
use App\Support\AgencyOps\PitchDeckBuilder;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ProspectPitchDeckController extends Controller
{
    protected function resolveDeck(string $token): ProspectPitchDeck
    {
        $deck = ProspectPitchDeck::with('agency')->where('share_token', $token)->first();
        abort_unless($deck && $deck->agency, 404);
        abort_unless(Modules::on('agency_ops_hub', null, $deck->agency), 404);

        return $deck;
    }

    public function show(Request $request, string $token): Response
    {
        $deck = $this->resolveDeck($token);
        $lang = in_array($request->query('lang'), ['ar', 'en', 'both'], true)
            ? (string) $request->query('lang')
            : ($deck->agency->language === 'en' ? 'en' : 'both');

        return response()
            ->view('public.pitch-deck', [
                'deck' => $deck,
                'agency' => $deck->agency,
                'lang' => $lang,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'private, no-store');
    }

    public function pdf(Request $request, string $token): Response
    {
        $deck = $this->resolveDeck($token);
        $lang = in_array($request->query('lang'), ['ar', 'en', 'both'], true)
            ? (string) $request->query('lang')
            : 'both';

        $slug = Str::slug($deck->prospect_name) ?: 'proposal';
        $bytes = PitchDeckBuilder::renderPdf($deck, $lang);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="proposal-'.$slug.'-'.$lang.'.pdf"',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
