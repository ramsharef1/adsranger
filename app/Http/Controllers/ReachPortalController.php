<?php

namespace App\Http\Controllers;

use App\Models\ReachCouponCode;
use App\Models\ReachPublisher;
use App\Models\ReachWidget;
use App\Support\Reach\OfferImporter;
use App\Support\Reach\PublisherKit;
use App\Support\Reach\Reach;
use App\Support\Reach\Serve;
use App\Support\Reach\Wallet;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * FR-REACH R-2 publisher portal (/reach/portal): four tabs (overview, placements, offers & codes, wallet). Signed-in
 * users only; the module must be on for the user (and the publisher's workspace). Money actions are recorded
 * requests with status "estimate" (D-262).
 */
class ReachPortalController extends Controller
{
    public const TABS = ['overview', 'placements', 'offers', 'wallet'];

    private function publisher(Request $request): ?ReachPublisher
    {
        return $request->user() ? ReachPublisher::where('user_id', $request->user()->id)->first() : null;
    }

    private function portalUrl(Request $request, string $tab = 'overview'): string
    {
        return url(($request->input('lang') === 'en' ? '/en' : '').'/reach/portal').'?tab='.$tab;
    }

    private function gate(Request $request, ?ReachPublisher $publisher): void
    {
        if (! $request->user()) {
            throw new HttpResponseException(redirect()->guest(route('filament.app.auth.login')));
        }
        abort_unless(Reach::on($request->user(), $publisher?->agency ?? $request->user()->agencies()->first()), 404);
    }

    public function show(Request $request, string $lang = 'ar'): Response
    {
        $publisher = $this->publisher($request);
        $this->gate($request, $publisher);
        app()->setLocale($lang === 'en' ? 'en' : 'ar');
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';

        return response()->view('reach.portal', [
            'ar' => $lang !== 'en', 'tab' => $tab, 'publisher' => $publisher,
            'wallet' => $publisher ? Wallet::for($publisher)->fresh() : null,
            'quote' => $publisher ? Wallet::earlyCashoutQuote($publisher) : null,
            'today' => $publisher ? Serve::stats($publisher->id) : null,
            'week' => $publisher ? Serve::stats($publisher->id, 7) : null,
            'ledger' => $publisher ? $publisher->ledger()->with('offer:id,title')->latest('id')->limit(30)->get() : collect(),
            'codes' => $publisher ? ReachCouponCode::with('offer:id,title')->where('publisher_id', $publisher->id)->get() : collect(),
            'referred' => $publisher ? ReachPublisher::where('referred_by_id', $publisher->id)->count() : 0,
            'ref' => (string) $request->session()->get('reach_ref', ''),
        ])->header('Cache-Control', 'no-store');
    }

    /** R-4: the publisher's own WordPress plugin, built for one of its web widgets (deterministic bytes). */
    public function wordpressPlugin(Request $request): Response
    {
        $publisher = $this->publisher($request);
        $this->gate($request, $publisher);
        abort_unless($publisher, 404);
        $widget = $publisher->widgets()->where('type', 'web_js')->where('public_key', (string) $request->query('w'))->first();
        abort_unless($widget, 404);

        return response(PublisherKit::wordpressZip($publisher, $widget), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.PublisherKit::PLUGIN_SLUG.'-'.$widget->placement.'.zip"',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $publisher = $this->publisher($request);
        $this->gate($request, $publisher);
        abort_unless($publisher, 404);

        return response()->json(['today' => Serve::stats($publisher->id), 'week' => Serve::stats($publisher->id, 7)])->header('Cache-Control', 'no-store');
    }

    public function join(Request $request): RedirectResponse
    {
        $this->gate($request, null);
        abort_if($this->publisher($request), 409);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain_or_app' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-_\/:]+$/'],
            'referral' => ['nullable', 'string', 'max:16'],
        ]);
        $referrer = ($data['referral'] ?? '') !== '' ? ReachPublisher::where('referral_code', strtolower($data['referral']))->first() : null;
        ReachPublisher::create([
            'user_id' => $request->user()->id, 'agency_id' => $request->user()->agencies()->first()?->id,
            'name' => $data['name'], 'domain_or_app' => strtolower($data['domain_or_app']), 'referred_by_id' => $referrer?->id,
        ]);
        $request->session()->forget('reach_ref');

        return redirect()->to($this->portalUrl($request))->with('reach_status', __('reach_ui.portal.joined'));
    }

    public function addWidget(Request $request): RedirectResponse
    {
        $publisher = $this->publisher($request);
        $this->gate($request, $publisher);
        abort_unless($publisher, 404);
        $data = $request->validate([
            'type' => ['required', Rule::in(ReachWidget::TYPES)],
            'placement' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-]+$/'],
        ]);
        abort_if($publisher->widgets()->count() >= 20, 422);
        $publisher->widgets()->create($data + ['settings' => ['auto_skim' => true]]);

        return redirect()->to($this->portalUrl($request, 'placements'))->with('reach_status', __('reach_ui.portal.widget_added'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $publisher = $this->publisher($request);
        $this->gate($request, $publisher);
        abort_unless($publisher, 404);
        $data = $request->validate(['blocked_domains' => ['nullable', 'string', 'max:2000'], 'family_safe' => ['nullable', 'boolean']]);
        $domains = array_values(array_unique(array_filter(array_map(
            fn ($d) => preg_replace('/^www\./', '', strtolower(trim(preg_replace('#^https?://#i', '', $d), " /\t"))),
            preg_split('/[\s,،]+/u', (string) ($data['blocked_domains'] ?? '')) ?: []
        ), fn ($d) => (bool) preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $d))));
        if (count($domains) > (int) config('reach.max_blocked_domains')) {
            return redirect()->to($this->portalUrl($request, 'placements'))->withErrors(['blocked_domains' => __('reach_ui.portal.too_many_domains', ['max' => config('reach.max_blocked_domains')])]);
        }
        $publisher->update(['blocked_domains' => $domains, 'family_safe' => (bool) ($data['family_safe'] ?? false)]);

        return redirect()->to($this->portalUrl($request, 'placements'))->with('reach_status', __('reach_ui.portal.saved'));
    }

    public function wallet(Request $request, string $action): RedirectResponse
    {
        $publisher = $this->publisher($request);
        $this->gate($request, $publisher);
        abort_unless($publisher && $publisher->isVerified(), 404);
        $amount = (float) OfferImporter::number($request->input('amount'));
        $result = match ($action) {
            'early-cashout' => Wallet::requestEarlyCashout($publisher) ?? 'nothing_pending',
            'withdraw' => Wallet::requestWithdrawal($publisher, $amount),
            'ad-credit' => Wallet::reinvestAsAdCredit($publisher, $amount),
            default => abort(404),
        };
        $msg = is_string($result) ? __('reach_ui.portal.wallet_error.'.$result, ['min' => config('reach.wallet.min_withdrawal')]) : __('reach_ui.portal.wallet_recorded');

        return redirect()->to($this->portalUrl($request, 'wallet'))->with('reach_status', $msg);
    }
}
