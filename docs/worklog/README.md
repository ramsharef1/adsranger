# AdsRanger engine reports (two AI engines)

Each engine writes **only its own file** and **reads the other's** before every task (D-81, D-82). Nothing is shared-edited, so the files never clash.

| Engine | Who | Its file (it writes) | Where it lives | Live on the server |
|---|---|---|---|---|
| **E1** | Claude, AdsRanger master chat | `docs/worklog/E1.md` | branch `main` | `/home/adssaas/coord/E1.md` (link into the app) |
| **E2** | Second AI, chosen by Rami | `docs/worklog/E2.md` | branch **`e2/log`** (only this file changes there) | `/home/adssaas/coord/E2.md` (copied from `origin/e2/log` every 5 minutes) |

E2 has no server or database access: it pushes code on `e2/<topic>` branches as pull requests; E1 reviews, migrates, deploys.

## Every task, both engines
1. **Read the other engine's file** (E1 reads `origin/e2/log:docs/worklog/E2.md` or the server copy; E2 reads `docs/worklog/E1.md` on `main`). Look at its **Working on now** list and its newest 20 entries.
2. **Don't start** anything the other engine lists under Working on now (files, feature #, DB tables). Write an `ASK` entry in your own file and tell Rami.
3. **Announce:** add your task to **Working on now** in your own file and push it *before* you edit code (E2: commit on `e2/log`, push).
4. Do the work. Before changing code the other engine wrote, check `git log` / `git blame` (commit trailer `Engine: E1` / `Engine: E2`). Build on it, keep its tests passing, don't reformat untouched code; say `builds-on <E1-xxx|E2-xxx>` in your entry.
5. **Report:** add one entry at the top of your Log, remove the Working on now row, push. E2 also opens its code PR; E1 adds `DEPLOYED <commit>` to its own file when it ships E2's PR.
6. **Broken?** Log a `BROKEN` entry in your own file first (what, where, steps, which engine's commit), then fix it as its own task, linking both. Never silently rewrite the other engine's work.

## Hard limits
- No passwords, keys, tokens, `.env` values or client data in these files or in git.
- Schema only by a **new** migration; only E1 runs migrations, after a pg_dump backup.
- Every commit ends with `Engine: E1` or `Engine: E2`. Every task adds or updates a test; `php artisan test` passes before a push.

## Entry format (both files)
```
### E1-xxx | E2-xxx · YYYY-MM-DD HH:MM (Asia/Amman) · TYPE · title
- Files: …
- Feature: #… (ADSR-060) · DB: tables / migration
- Commit / branch / PR: …
- Builds-on / fixes: …
- Tests: …
- State: WORKING | DONE | IN REVIEW | DEPLOYED <commit> | BROKEN | ASK
```
TYPE = build · fix · refactor · plan · BROKEN · ASK · deploy
