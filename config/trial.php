<?php

/*
 * D-61 trial lifecycle after the 14-day Pro trial (config/adsranger.php `trial_days`), for self-signup
 * agencies only (signup_source = self_signup). Agencies that came in by invite or were created by an admin
 * are never locked, closed or deleted by this lifecycle.
 *
 *   trial ends ──► read-only (read_only_days) ──► expired / closed (keep_days) ──► archived ──► purged
 *
 * Deletion is conservative and reversible by default:
 * - `archive` (default): the agency is soft-deleted (agencies.deleted_at). Nobody can open it, no job touches it,
 *   but every row is kept, so an admin can restore it with "Extend trial". After `purge_after_days` the archive
 *   is purged for good (D-61 "deleted"); null keeps archives until someone purges them.
 * - `delete`: purge on the due day, with no archive window (cannot be undone).
 * Member emails are never anonymised or rewritten; a purge deletes rows, it does not alter them.
 */
return [
    'read_only_days' => 14,
    'keep_days' => 30,
    'ending_notice_days' => 3,
    'deletion_notice_days' => 7,
    'delete_mode' => env('TRIAL_DELETE_MODE', 'archive'),
    'purge_after_days' => env('TRIAL_PURGE_AFTER_DAYS', 30),
    // UTC time of the daily lifecycle run (routes/console.php).
    'run_at_utc' => '03:10',
];
