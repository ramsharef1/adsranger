# AdsRanger shared work log (two AI engines)

Live copy on the server: `/home/adssaas/coord/WORKLOG.md` (a link to this file, `docs/WORKLOG.md` in the app). GitHub copy: `main` of github.com/ramsharef1/adsranger.

## Engines
| Tag | Who | Access | Can deploy |
|---|---|---|---|
| **E1** | Claude, AdsRanger master chat (Cowork) | server + GitHub | yes (only E1 deploys) |
| **E2** | Second AI, chosen by Rami | GitHub only, never the server (D-81) | no: pushes a branch, E1 reviews and deploys |

## Rules (both engines, every task)
1. **Read first.** Before any task, pull `main` and read **Active claims** and the newest 20 log entries.
2. **Claim before you edit.** Add a row to Active claims: files / feature (# from ADSR-060) / DB tables or migrations, your tag, branch, time (Asia/Amman). Don't start on anything another engine has claimed; write a `ASK` entry instead.
3. **One branch per task.** E2: `e2/<short-topic>` from the latest `main`. E1: works on `main` and deploys.
4. **Log when done.** Add one entry at the top of the Log (format below), remove your claim row, push. E2 opens a pull request; E1 reviews, merges, deploys and adds the `DEPLOYED` line.
5. **Who wrote it.** Every commit ends with `Engine: E1` or `Engine: E2`. To find who changed a line: `git log -L` / `git blame`, then the trailer.
6. **Build on, don't overwrite.** Changing code the other engine wrote: say so in the entry (`builds-on W-xxx`), keep its tests passing, don't reformat files you don't need to touch.
7. **Broken?** If you find something broken (yours or theirs): add a `BROKEN` entry first (what, where, how to reproduce), then claim and fix it as its own task, linking both entries.
8. **Database.** Schema changes only by a new migration (never edit an old one). E2 never runs anything against the live DB; E1 runs migrations at deploy after a pg_dump backup.
9. **Never in this file or in git:** passwords, API keys, tokens, `.env` values, client data.
10. **Tests.** Every task adds or updates a test in `tests/Feature`; `php artisan test` must pass before the push.

## Active claims
| Engine | Since | Branch | Files / feature / DB | Note |
|---|---|---|---|---|
| — | — | — | — | none |

## Log (newest first)
Format:
```
### W-xxx · YYYY-MM-DD HH:MM · E1|E2 · TYPE · title
- Files: …
- Feature: #… (ADSR-060) · DB: tables / migration
- Commit / branch: …
- Builds-on / fixes: W-…
- Tests: …
- State: CLAIMED | DONE | IN REVIEW | DEPLOYED <server commit> | BROKEN | ASK
```
TYPE = build · fix · refactor · BROKEN · ASK · deploy

### W-001 · 2026-11-05 06:40 · E1 · build · Shared work log created
- Files: docs/WORKLOG.md (+ server link /home/adssaas/coord/WORKLOG.md)
- Feature: process (D-81) · DB: none
- State: DONE. Live state at creation: `main` = 87a5a2a (#79 export, #106 email reports, trial lifecycle). Repo tests 131/131.
