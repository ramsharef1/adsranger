<?php

namespace App\Support;

use App\Filament\Pages\PlanUsage;
use App\Models\Agency;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * D-61 / EX-50 style notice when a read-only (trial ended) workspace refuses a change: what happened, then
 * "See plans" for owner/admin, or "ask the owner" for everyone else (EX-37). Plus the contact link (waitlist when
 * live, else the support@ mailto, D-53).
 */
class TrialNotice
{
    public static function readOnly(Agency $a, ?User $user): Notification
    {
        $until = Trial::readOnlyUntil($a);
        $body = Trial::isClosed($a)
            ? __('This workspace is closed. Your data is kept until :date; the owner can still download it.', ['date' => static::date(Trial::deleteOn($a))])
            : __('This workspace is read-only until :date. You can view and download your data; changes, syncs and alerts are paused.', ['date' => static::date($until)]);

        $n = Notification::make('trial-read-only')->title(__('Your trial ended — choose a plan'))->warning()->persistent();
        if (PlanGate::canUpgrade($user, $a)) {
            return $n->body($body)->actions([
                Action::make('plans')->label(__('See plans'))->button()->url(PlanUsage::getUrl(tenant: $a)),
            ]);
        }

        return $n->body($body.' '.__('Ask your workspace owner to choose a plan.'));
    }

    /** Dates in the reader's language, Western digits (EX-20). */
    public static function date($d, ?string $locale = null): string
    {
        if (! $d) {
            return '—';
        }
        $locale = $locale ?? (app()->getLocale() === 'en' ? 'en' : 'ar');

        return $d->copy()->locale($locale)->translatedFormat('j F Y');
    }

    /** D-53: the waitlist link when /waitlist is live (config adsranger.waitlist_url), else the support@ mailto. */
    public static function contactHtml(?string $locale = null): HtmlString
    {
        $url = config('adsranger.waitlist_url');
        if (filled($url)) {
            return new HtmlString('<a href="'.e($url).'" style="text-decoration:underline">'.e(__('Join the waitlist', [], $locale)).'</a>');
        }
        $email = e(config('adsranger.signup.support_email'));

        return new HtmlString(e(__('Ask for an invite at', [], $locale)).' <a href="mailto:'.$email.'" style="text-decoration:underline"><bdi dir="ltr">'.$email.'</bdi></a>');
    }

    /** Help line for trial notices: "Questions? support@…" or the waitlist link. */
    public static function helpHtml(?string $locale = null): HtmlString
    {
        $url = config('adsranger.waitlist_url');
        if (filled($url)) {
            return new HtmlString('<a href="'.e($url).'" style="text-decoration:underline">'.e(__('Join the waitlist', [], $locale)).'</a>');
        }
        $email = e(config('adsranger.signup.support_email'));

        return new HtmlString(e(__('Questions? Write to', [], $locale)).' <a href="mailto:'.$email.'" style="text-decoration:underline"><bdi dir="ltr">'.$email.'</bdi></a>');
    }
}
