# Changelog

All notable changes to the Image blog plugin (`local_imageblog`) are documented
here. The format is based on [Keep a Changelog](https://keepachangelog.com/), and
the project aims to follow [Semantic Versioning](https://semver.org/).

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

[0.5.5]: https://github.com/verzog/moodle-local_imageblog/releases/tag/v0.5.5
[0.5.4]: https://github.com/verzog/moodle-local_imageblog/releases/tag/v0.5.4
