# Image blog v0.5.5 — first release

A site-wide, image-led blog for Moodle. Authors publish posts with a featured
image, rich content and optional 360° panoramas; readers can work through
clinical cases and earn CPD.

**Requires:** Moodle 5.0+ · PHP 8.2+ · PostgreSQL / MySQL / MariaDB
**Licence:** GNU GPL v3 or later

## Highlights

- **Image-led blog** — featured cover image, rich body content, and a card-based
  listing with keyword, author, category, subcategory, tag, difficulty and date
  filters.
- **360° panoramas** — optional equirectangular images rendered with the bundled
  Pannellum viewer.
- **Publishing workflow** — draft, scheduled and published states, with a
  scheduled task that auto-publishes posts when their time arrives.
- **Taxonomy** — categories, subcategories, tags and difficulty levels, managed
  from dedicated admin pages.
- **Clinical-case mode** — readers submit a diagnosis, ask the author questions,
  and earn configurable CPD hours once the outcome is revealed, with difficulty
  multipliers, a best-answer bonus and a kill-switch.
- **Notifications** — opt-in email for new posts (an immediate per-post alert, or
  an aggregated daily or weekly digest) plus an optional public RSS feed.
- **Blog author role** — delegate authoring to trusted users without making them
  site managers.
- **Bulk import/export** — CLI tools sharing a common CSV format for blog posts.
- **Privacy** — full Privacy API support (export and erasure), with erasure that
  preserves other participants' case contributions.

## Security & privacy hardening

- Case actions verify a question/diagnosis belongs to the authorised post
  (prevents cross-case interference).
- The CLI importer contains image paths under `--imagedir` (blocks path
  traversal).
- Content and delete capabilities declare the appropriate `riskbitmask`, and
  authoring is delegated via the Blog author role and managers rather than every
  authenticated user.
- User erasure anonymises posts that carry other users' contributions instead of
  deleting those third-party records, and moves the retained shell to a
  non-public state.

**Full changelog:** [`CHANGELOG.md`](https://github.com/verzog/moodle-local_imageblog/blob/main/CHANGELOG.md)
