<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CreativeBrief;
use Illuminate\Http\Request;

/** #54: Signed no-login client approval screen for Creative Studio briefs. */
class StudioBriefController extends Controller
{
    public function show(string $token)
    {
        $brief = CreativeBrief::with(['client.brandKit', 'agency'])->where('share_token', $token)->firstOrFail();

        return view('public.studio-brief', ['brief' => $brief, 'kit' => $brief->client?->brandKit]);
    }

    public function decide(Request $request, string $token)
    {
        $brief = CreativeBrief::with(['client', 'agency'])->where('share_token', $token)->firstOrFail();
        $data = $request->validate([
            'decision' => ['required', 'in:approved,changes_requested'],
            'client_feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $brief->update([
            'status' => $data['decision'],
            'client_feedback' => isset($data['client_feedback']) ? trim((string) $data['client_feedback']) : null,
            'approved_at' => $data['decision'] === 'approved' ? now() : null,
        ]);

        AuditLog::record('studio.brief_'.$data['decision'], $brief->client, ['brief_id' => $brief->id]);

        return redirect('/studio/brief/'.$brief->share_token)->with('decision_saved', true);
    }
}
