# Changelog

All notable changes to the Image blog plugin (`local_imageblog`) are documented
here. The format is based on [Keep a Changelog](https://keepachangelog.com/), and
the project aims to follow [Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-10-20

### Security
- Escape the author's display name when rendering the _Manage blog authors_
  table (`authors.php`). The name was emitted unescaped into an
  `html_writer::table` cell (the adjacent email was already escaped), a
  conditional stored-XSS sink for display names carrying markup from an
  external auth/sync source. Now wrapped in `s()`, matching Moodle's
  escape-on-output standard.
- Enforce the standard assignable-roles boundary when granting the Blog author
  role. The authors page previously called `role_assign()` directly, letting
  any holder of `moodle/role:assign` grant the role regardless of whether site
  policy permits them to assign it. The role is now registered as allow-assign
  for manager-archetype roles (via `author_role::ensure()`), and both assign
  paths check `get_assignable_roles()` first (v2026102000).

## [1.0.0] - 2026-10-18

First stable release.

### Changed
- Maturity promoted from beta to **stable** (`MATURITY_STABLE`).

### Removed
- Three redundant single-column indexes whose foreign key already provides the
  index: `idx_authorid` on `local_imageblog_posts`, and `idx_postid` on
  `local_imageblog_post_tags` and `local_imageblog_case_qs`. The foreign keys
  (`fk_author`, `fk_post`) continue to index those columns, so lookup
  performance is unchanged, and no data migration is needed (the surviving
  index is equivalent by column set). The unique `idx_userid` on
  `local_imageblog_subs` is kept — it enforces one subscription per user, which
  a foreign-key index would not.

### Testing
- CI now also runs the Moodle **PHP Mess Detector** (`phpmd`) check, matching
  the moodle.org plugin prechecker, and a **cross-version upgrade** job that
  installs the earliest released version and upgrades it to this revision
  against PostgreSQL, MySQL and MariaDB, so the upgrade path (not just fresh
  installs) is exercised on every change.

## [0.7.2] - 2026-10-16

### Fixed
- Upgrade crash (`Key fk_rubric collides with index idx_rubricid`) when
  installing the 0.7.0 evaluate tables on an existing site. The
  `local_imageblog_rubric_crit` table declared a separate `idx_rubricid` index
  on `rubricid`, the same column already indexed by the `fk_rubric` foreign
  key, which the XMLDB table builder rejects. The redundant index is removed
  from the schema (both `install.xml` and the upgrade step). No data migration
  is needed: the collision was purely in the schema definition and was caught
  while building the table object, so the database never physically held two
  indexes on the column — the foreign key continues to index it.

## [0.7.1] - 2026-10-15

### Added
- Post-type labels: the listing cards and the single-post view show a badge
  for the post type (every type except plain Blog Post, to avoid cluttering
  ordinary posts), while the subscription digest email (HTML and plain text)
  and the RSS feed (`<category>`) label every item regardless of type.

## [0.7.0] - 2026-10-15

### Added
- The **evaluate** interaction for the Tutorial, Critical Review and Design
  Evaluation post types. Readers rate the post against an admin-defined rubric
  and leave a comment; the author sets a reference ("model") rating per
  criterion. On reveal each reader is scored **completeness × agreement** — the
  share of the rubric they completed times how closely their ratings matched
  the reference.
- Admin-managed **rubric templates** (_Manage evaluation rubrics_): each has a
  1..N rating scale and a list of criteria. Authors pick a template per post.
- Assessable evaluate posts record participation CPD (same rates as cases);
  Practice posts show the score without recording CPD. Evaluate posts reuse the
  author-set / default reveal window, and the scheduled task now auto-reveals
  them once the author's reference ratings exist.
- A _Show other readers' evaluations_ setting controls post-reveal visibility of
  other readers' ratings and comments.
- New capability `local/imageblog:submitevaluation`; Privacy API coverage for
  reader evaluations and their per-criterion scores.

### Database
- Adds `local_imageblog_rubrics`, `local_imageblog_rubric_crit`,
  `local_imageblog_eval_refs`, `local_imageblog_evals` and
  `local_imageblog_eval_scores`, and a `rubricid` column on
  `local_imageblog_posts` (v2026101500).

## [0.6.0] - 2026-10-14

### Changed
- Restructured the post taxonomy. The free category/subcategory taxonomy is
  removed and replaced by two fixed dimensions:
  - **Post type** (eight types): Blog Post, News and Reference Article
    (read-only); Patient Case and Case Study (diagnose — readers submit a
    diagnosis and the author reveals the outcome); Tutorial, Critical Review
    and Design Evaluation (evaluate — reader appraisal, wired in a later phase).
  - **Assessment mode** on the interactive types: Assessable (records CPD
    hours) or Practice (sample score, no CPD), defaulting to Assessable. CPD is
    now only recorded for a diagnose-type post in Assessable mode. Read-only
    types carry no assessment.
- The listing filter bar swaps the category/subcategory dropdowns for post-type
  and assessment-mode filters; the CSV import/export columns `category` and
  `subcategory` become `post_type` and `assessment_mode`.
- Cases can auto-reveal: an author may set a reveal time per post, or the new
  _Default reveal window (days)_ site setting reveals a case automatically a
  fixed number of days after publication. The scheduled publishing task now
  also performs the due reveals.

### Migration
- The upgrade drops the `local_imageblog_categories`,
  `local_imageblog_subcategories` and `local_imageblog_post_cats` tables, and
  adds `assessmentmode` and `caserevealat` columns to `local_imageblog_posts`.
  Existing `case` posts become **Patient Case / Assessable**; all other posts
  become **Blog Post / Not assessed**.

## [0.5.8] - 2026-10-04

### Fixed
- Correct the copyright holder from "Vernon Apain / Educheckout" to "Vernon
  Spain" in every file header (PHP, templates, language strings and AMD
  sources, with the AMD build regenerated by grunt), and spell EduCheckout
  consistently in the README.

## [0.5.7] - 2026-10-04

### Changed
- Extract the clinical-case scoring maths into a context-neutral
  `\local_imageblog\local\scoring` engine (scale parsing, difficulty multiplier
  and the base x multiplier x factor hours formula). `case_post::compute_hours()`
  now delegates to it. No behaviour change; this gives one shared definition the
  companion `mod_imageblog` activity can reuse instead of duplicating.

## [0.5.6] - 2026-10-04

### Changed
- Declare support for Moodle 5.3: `$plugin->supported` now spans `[500, 503]`
  (Moodle 5.0–5.3 inclusive). No functional change; the codebase uses no APIs
  removed in the 5.1–5.3 cycle.
- CI now also runs against `MOODLE_503_STABLE` (PHP 8.3 and 8.4; PHP 8.2 is
  excluded there, as Moodle 5.3 requires PHP 8.3+). The PostgreSQL and MariaDB
  service containers are bumped to 17 and 11.4 respectively to meet Moodle
  5.3's raised minimum database versions.

## [0.5.5] - 2026-09-01

### Added
- Plugin icon (`pix/icon.svg`) for the navigation and admin listings.
- This changelog.

## [0.5.4] - 2026-09-01

Initial public beta.

### Features
- Site-wide, image-led blog with a featured image, rich body content and a
  card-based listing with keyword, author, category, subcategory, tag,
  difficulty-level and date filters.
- Optional 360° equirectangular panoramas, rendered with the bundled Pannellum
  viewer.
- Taxonomy of categories, subcategories, tags and difficulty levels, managed
  from dedicated admin pages.
- Draft, scheduled and published post states, with a scheduled task that
  auto-publishes posts when their time arrives.
- "Clinical case" mode: readers submit a diagnosis, ask the author questions,
  and earn configurable CPD hours once the outcome is revealed, with a
  best-answer bonus and a kill-switch for the CPD rules.
- Opt-in email digests (immediate, daily and weekly) and an optional public
  RSS feed of recently published posts.
- CLI bulk import and export sharing a common CSV format.
- A dedicated "Blog author" role so non-managers can create, publish and edit
  their own posts, managed from an admin page.
- Privacy API provider, and custom CSS scoped to the plugin's own pages.

### Security & privacy
- Case actions verify that a question or diagnosis belongs to the post the
  caller is authorised to manage, preventing cross-case interference.
- The CLI importer contains image paths under `--imagedir` with a `realpath()`
  check so an untrusted CSV cannot pull files from elsewhere on the server.
- Content and delete capabilities declare the appropriate `riskbitmask`, and
  post authoring is delegated through the Blog author role and managers rather
  than every authenticated user.
- User erasure anonymises posts that still carry other users' contributions
  instead of deleting those third-party diagnoses, questions and CPD records,
  and moves the retained shell to a non-public state so it is never published
  after erasure.

[0.5.7]: https://github.com/verzog/moodle-local_imageblog/releases/tag/v0.5.7
[0.5.6]: https://github.com/verzog/moodle-local_imageblog/releases/tag/v0.5.6
[0.5.5]: https://github.com/verzog/moodle-local_imageblog/releases/tag/v0.5.5
[0.5.4]: https://github.com/verzog/moodle-local_imageblog/releases/tag/v0.5.4
