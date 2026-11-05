{{-- #175 phone alerts: installable /app (manifest) + the push service worker and a small client helper. --}}
<link rel="manifest" href="/app.webmanifest">
<meta name="theme-color" content="#0F766E">
<link rel="apple-touch-icon" href="/brand/favicon-180.png">
@php($pushAgency = \Filament\Facades\Filament::getTenant())
@if ($pushAgency && auth()->check() && config('push.enabled'))
    @php($pushAllowed = \App\Support\PlanGate::allows($pushAgency, 'phone_push'))
    <script>
        window.adsrPush = (function () {
            const cfg = {
                allowed: @json($pushAllowed),
                key: @json($pushAllowed ? \App\Support\WebPush::publicKey() : null),
                csrf: @json(csrf_token()),
                sub: @json(route('filament.app.push.subscribe', ['tenant' => $pushAgency->slug])),
                unsub: @json(route('filament.app.push.unsubscribe', ['tenant' => $pushAgency->slug])),
                test: @json(route('filament.app.push.test', ['tenant' => $pushAgency->slug])),
            };
            const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
            const b64 = (s) => { const p = '='.repeat((4 - s.length % 4) % 4); const r = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...r].map((c) => c.charCodeAt(0))); };
            const reg = () => navigator.serviceWorker.register('/push-sw.js', { scope: '/app/' });
            const post = (url, body) => fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' }, body: JSON.stringify(body || {}) });
            async function current() { if (!supported) return null; const r = await navigator.serviceWorker.getRegistration('/app/'); return r ? r.pushManager.getSubscription() : null; }
            async function state() {
                if (!cfg.allowed) return 'locked';
                if (!supported) return 'unsupported';
                if (Notification.permission === 'denied') return 'denied';
                return (await current()) ? 'on' : 'off';
            }
            async function enable() {
                if (!cfg.allowed || !supported) return state();
                const perm = await Notification.requestPermission();
                if (perm !== 'granted') return state();
                const r = await reg();
                await navigator.serviceWorker.ready;
                const s = (await r.pushManager.getSubscription()) || (await r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(cfg.key) }));
                await post(cfg.sub, s.toJSON());
                return 'on';
            }
            async function disable() {
                const s = await current();
                if (s) { await post(cfg.unsub, { endpoint: s.endpoint }); await s.unsubscribe(); }
                return state();
            }
            async function test() { const r = await post(cfg.test); return r.ok ? (await r.json()).ok : false; }
            if (supported && cfg.allowed && Notification.permission === 'granted') { reg().catch(() => {}); }
            return { state, enable, disable, test, isPhone: () => window.matchMedia('(max-width: 768px)').matches };
        })();
    </script>
@endif
