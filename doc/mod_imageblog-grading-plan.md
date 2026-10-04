# Plan: `mod_imageblog` — a graded clinical-case activity

**Status:** proposal / design sketch · **Author:** drafted for verzog
**Context:** `local_imageblog` v0.5.6 (site-wide blog, Moodle 5.0–5.3)

---

## 1. Goal

Make the clinical-case experience **gradable inside a course** — so a reader's
diagnosis, best-answer selection and participation can feed the Moodle
**gradebook**, drive **activity completion**, and appear in course reports.

The blocker today is structural, not logical: `local_imageblog` is a **local
plugin** that runs in **system context**, site-wide. The gradebook is
**course-scoped** — grades attach to a *grade item* owned by a *course module*
in a *course*. A local plugin has no course module, so it cannot own a grade
item in the normal way. The scoring logic ("CPD hours", best-answer bonus,
difficulty multiplier) already exists; what's missing is a course-level host
for it.

## 2. Recommendation: coexist, don't convert

Create a **new activity module `mod_imageblog`** and keep `local_imageblog` as
the public, site-wide blog. Reasons:

- Converting `local_imageblog` → `mod_imageblog` would destroy the "one
  site-wide blog" model and force a disruptive data migration for existing
  sites.
- The two serve different jobs: the local plugin is a public showcase; the
  activity is graded, in-course coursework.
- A shared library (below) lets both reuse the case logic without duplication.

```
local/imageblog/        ← stays: public, site-wide blog (unchanged)
mod/imageblog/          ← new: course activity, graded clinical cases
classes/local/case/     ← shared case engine, extracted to be context-neutral
```

> If you'd rather not ship two plugins, the fallback is a **Grade API bridge**
> from the local plugin (create grade items via `grade_update()` with
> `component='local_imageblog'`). It works but fights Moodle's grain — no
> course UI, no completion, awkward backup/restore — so it's a stopgap, not the
> destination.

## 3. What an activity module requires (and what we already have)

| Moodle activity requirement | Status in `local_imageblog` today |
| --- | --- |
| `mod/imageblog/version.php` (`component = mod_imageblog`) | new |
| `lib.php` with `imageblog_add_instance / update_instance / delete_instance` | new (instance CRUD) |
| `imageblog_supports()` advertising `FEATURE_GRADE_HAS_GRADE`, `FEATURE_COMPLETION_HAS_RULES`, `FEATURE_BACKUP_MOODLE2`, `FEATURE_MOD_INTRO` | new |
| `mod_form.php` (instance settings form) | adapt from `classes/form/post_form.php` |
| `view.php` in **module context** (`require_login($course, true, $cm)`) | adapt from root `view.php` (currently `CONTEXT_SYSTEM`) |
| Grade integration: `imageblog_grade_item_update`, `imageblog_update_grades`, `imageblog_get_user_grades` | new — **maps CPD → grade** |
| `db/install.xml` with an `imageblog` instance table (course, name, intro, grade, CPD rules) | new, but case tables port from existing schema |
| `db/access.php` capabilities in **`CONTEXT_MODULE`** | re-scope the 8 existing system capabilities |
| `backup/moodle2/` backup + restore handlers | new |
| `classes/privacy/provider.php` keyed by context/cm | adapt existing provider |
| `db/tasks.php` / adhoc tasks | reuse `award_case_cpd` pattern, grade-aware |
| Events, `lang/en/imageblog.php`, `templates/`, `pix/icon.svg`, `styles.css` | port from local plugin |

## 4. Reuse map — what ports with little change

The genuinely hard part (the scoring) is **already written** and ports almost
verbatim once it's made context-neutral:

- **`classes/case_post.php`** — diagnoses, Q&A, reveal, and CPD calculation
  (`REASON_PARTICIPATION` / `REASON_BEST` / `REASON_VIEW`, difficulty
  multipliers, best-answer bonus, kill-switch). Extract to
  `classes/local/case/engine.php` taking an explicit context/instance id
  instead of assuming the system blog.
- **Case tables** — `local_imageblog_case_diags`, `_case_qs`, `_case_cpd` map
  directly to `imageblog_case_diags` etc., gaining an `imageblogid`
  (instance) foreign key alongside the existing `postid`.
- **Templates** (`case_panel`, `post`, `lightbox`, `card`) and **AMD**
  (`panorama`, `lightbox`, `image_processor`, `filter`) are presentation-only
  and reuse as-is.
- **Pannellum** third-party viewer and `thirdpartylibs.xml` — copy over.

What must be **rewritten, not ported**: anything assuming system context —
`require_login`/`require_capability` calls, `context_system::instance()`,
`file_rewrite_pluginfile_urls` component/context args, the `local_imageblog`
navigation callbacks, and the admin settings pages (per-instance settings move
into `mod_form`, site defaults into admin settings).

## 5. Grading design — the core decision

The existing model awards **CPD hours** per user per case. For a grade we map
that to a numeric grade on the activity's grade item. Proposed:

- **Grade item**: one per `imageblog` instance, `gradetype = GRADE_TYPE_VALUE`,
  `grademax` configurable (default 100), or `GRADE_TYPE_SCALE` for a CPD-hours
  scale.
- **Score composition** (all configurable in `mod_form`, defaulting to the
  current CPD rules):
  - participation (submitted a diagnosis) → base points
  - best-answer bonus → added when the author marks the diagnosis best
  - difficulty multiplier → scales the base by the case's difficulty level
  - optional "view revealed outcome" credit
- **When grades post**: extend the existing `award_case_cpd` adhoc task
  (queued on case reveal) to also call `imageblog_update_grades($imageblog,
  $userid)`, which pushes the computed value to the gradebook via
  `grade_update()`.
- **Regrade path**: `imageblog_get_user_grades()` recomputes from the case
  tables so a settings change or manual recalculation reproduces grades
  deterministically.
- **Completion**: add a custom completion rule "submitted a diagnosis" (and
  optionally "case revealed"), surfaced through
  `FEATURE_COMPLETION_HAS_RULES` + `mod_completion` callbacks.

CPD-as-reporting can stay too: keep writing `imageblog_case_cpd` rows for the
existing CPD report, with the grade as the gradebook-facing projection of the
same event. One source of truth (the case tables), two consumers (CPD report +
gradebook).

## 6. Suggested file layout

```
mod/imageblog/
  version.php                      component=mod_imageblog, requires 5.0, supported [500,503]
  lib.php                          *_supports, *_add/update/delete_instance,
                                   *_grade_item_update, *_update_grades,
                                   *_get_user_grades, *_pluginfile, completion
  mod_form.php                     instance settings (grade, CPD rule weights)
  view.php                         module-context view (require_login($course,true,$cm))
  case_action.php                  diagnosis/question/reveal in module context
  index.php                        list instances in a course
  db/
    install.xml                    imageblog + imageblog_case_* tables
    access.php                     capabilities in CONTEXT_MODULE
    upgrade.php
    tasks.php
  classes/
    local/case/engine.php          shared case/CPD/grade engine (from case_post.php)
    grades.php                     CPD→grade mapping helpers
    privacy/provider.php
    task/award_case_grades.php     adhoc: award CPD + push grades on reveal
    output/renderer.php
  backup/moodle2/
    backup_imageblog_stepslib.php  / backup_imageblog_activity_task.php
    restore_imageblog_stepslib.php / restore_imageblog_activity_task.php
  templates/  amd/  lang/en/  pix/  thirdparty/
  tests/   (phpunit: engine, grades, backup; behat: submit→reveal→grade)
```

## 7. Risks and decisions to make first

1. **Shared engine vs. duplication.** Cleanest is a context-neutral case engine
   both plugins call. Decide whether to refactor `local_imageblog` to consume it
   too (more work now, less drift later) or fork the logic into `mod_` (faster,
   two copies to maintain). **Recommendation: shared engine.**
2. **Grade shape.** Points out of 100, or a CPD-hours scale? Affects gradebook
   display and existing CPD reporting. Needs your call.
3. **Taxonomy scope.** The local blog's categories/levels are site-wide. In the
   activity, are difficulty levels per-course, per-instance, or still site-wide?
4. **Backup/restore + user data.** Decide whether diagnoses/Q&A/CPD are included
   in course backups with user data (they usually should be, with an
   anonymise-on-restore path consistent with the existing privacy provider).
5. **Not for this scope:** multi-activity aggregation, cross-course CPD
   dashboards, or SCORM/LTI export — note but defer.

## 8. Effort estimate

Roughly **one focused week** for a first working version, assuming the shared
engine refactor:

| Phase | Work | Rough size |
| --- | --- | --- |
| 1 | Module scaffold: version/lib/`supports`/mod_form, instance CRUD, module-context view | 1–1.5 days |
| 2 | Extract shared case engine from `case_post.php`; wire module to it | 1–1.5 days |
| 3 | Grade integration: grade item, `update_grades`/`get_user_grades`, adhoc task, completion rules | 1.5 days |
| 4 | Backup/restore + privacy provider in module context | 1 day |
| 5 | Capabilities re-scope, lang, templates/AMD port, settings split | 0.5–1 day |
| 6 | PHPUnit (engine, grades, backup) + Behat (submit→reveal→grade), green CI across 5.0–5.3 | 1 day |

The conceptually hard part — scoring diagnoses, best-answer, difficulty
weighting — is already built; most of the week is Moodle activity plumbing
(context, gradebook callbacks, backup/restore), which is well-trodden.

## 9. Suggested first step

Land a **walking skeleton**: `mod/imageblog/` that installs, adds to a course,
shows one case, accepts a diagnosis, and on reveal writes a grade to the
gradebook — no taxonomy, no notifications, no panoramas yet. That proves the
context + gradebook wiring end-to-end; everything else is then an
incremental port from `local_imageblog`.
