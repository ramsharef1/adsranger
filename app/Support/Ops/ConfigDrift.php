<?php

namespace App\Support\Ops;

/** EG-06: settings production must keep. Any drift goes out on the same alert path as A-3. */
class ConfigDrift
{
    public static function applies(): bool
    {
        return app()->environment('production') || (bool) config('ops.force_checks');
    }

    /** @return list<string> one line per setting that drifted */
    public static function problems(): array
    {
        $p = [];
        if (config('app.debug')) {
            $p[] = __('Debug mode is on (APP_DEBUG). Visitors could see error details.');
        }
        if (config('app.env') !== 'production') {
            $p[] = __('APP_ENV is :env, not production.', ['env' => (string) config('app.env')]);
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $p[] = __('APP_URL is not https.');
        }
        if (config('session.secure') !== true) {
            $p[] = __('Session cookies are not marked secure (SESSION_SECURE_COOKIE).');
        }
        if (config('queue.default') === 'sync') {
            $p[] = __('The queue runs in the web request (QUEUE_CONNECTION=sync).');
        }
        $expect = (string) config('ops.expect_mailer', 'log');
        if ($expect !== '' && config('mail.default') !== $expect) {
            $p[] = __('Mail is set to :now, expected :expect while no email provider is set up (D-266).', ['now' => (string) config('mail.default'), 'expect' => $expect]);
        }

        return $p;
    }
}
