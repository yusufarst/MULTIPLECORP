# Branch protection of main

Status: REVIEW | Updated: 2026-10-30 | Owner: Planning

Written by [TASK-073](../../11-tasks/TASK-073.md) under DIR-096 for the Owner to apply: protecting `main` is a repository setting the Owner makes ([RELEASE_PLAN §2](../../10-release/RELEASE_PLAN.md#2-the-branch-and-environment-model)), a go-live item (GLC-12), recorded applied in TASK-084. No agent changes a repository setting.

## The settings

In the repository's settings, a branch protection rule — or a ruleset targeting the default branch — for `main`:

| Setting | Value | Grounds |
| --- | --- | --- |
| Branch name pattern | `main` | RELEASE_PLAN §2 |
| Require a pull request before merging | on | every change through a pull request and its CI |
| Required approvals | 1 — the Owner's approval of the pull request | RELEASE_PLAN §2; GLC-12 |
| Dismiss stale approvals when new commits are pushed | on | the approval covers the commit merged |
| Require status checks to pass before merging | on | TEST_STRATEGY Layer 1 |
| Required checks, by their exact names | `Verify — gates, TL-01–TL-08, TL-13, TL-14`; `Browser — TL-09–TL-12 on Chromium and WebKit`; `Performance and migration — TL-15, TL-16` | the three jobs of `.github/workflows/ci.yml` (TASK-003) |
| Require branches to be up to date before merging | on | the checks ran on what is merged |
| Allow force pushes | off | no rewritten history on `main` |
| Allow deletions | off | `main` is the one long-lived branch |
| Restrict who can push to matching branches | the Owner only | changes reach `main` only by a merged pull request |
| Tags `v*` | created only by the Owner's account, which the executor works under | a release tag is a decision on a commit the gate then checks |

## What the Owner decides when applying it

- **The approval and the executor's merges.** Under DIR-096 the executor opens and merges pull requests with the Owner's account; the repository host never counts an author's approval of their own pull request. With *Required approvals: 1*, either the Owner approves each pull request from a second account, or the Owner keeps the rule and merges as an administrator with the bypass recorded — the Owner's choice at GLC-12, recorded in TASK-084.
- **Administrators.** *Do not allow bypassing the above settings* is recommended once the merge path above is settled; until then the bypass is the Owner's alone.

## Checking it applied

After the Owner applies the rule, a push of a commit straight to `main`, a force push and a merge with a failing required check are each refused by the host; TASK-084 records the three refusals as GLC-12's evidence.
