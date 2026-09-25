# Moodle Plugins directory listing

Copy for submitting **Image blog** (`local_imageblog`) to the
[Moodle Plugins directory](https://moodle.org/plugins/). Keep this in step with
`version.php` and `CHANGELOG.md` when preparing a release.

## Short description

Shown in search results — keep it under ~255 characters.

> A site-wide, image-led blog for Moodle: posts with a featured image, rich
> content and optional 360° panoramas, organised by categories, tags and
> difficulty. An optional clinical-case mode lets readers diagnose, ask questions
> and earn CPD.

## Description

Paste into the directory's Description field (Markdown is supported there).

> **Image blog** turns Moodle into a polished, image-first blog that lives
> alongside your courses.
>
> **Authoring**
> - Posts with a featured cover image, rich body content and an image-led card listing.
> - Optional **360° equirectangular panoramas**, rendered with the bundled Pannellum viewer.
> - Draft, **scheduled** and published states, with automatic publishing when a scheduled time arrives.
> - A taxonomy of **categories, subcategories, tags and difficulty levels**, with keyword, author, category, tag and date filters on the listing.
> - A dedicated **"Blog author" role** so trusted users can create, publish and edit their *own* posts without being site managers.
>
> **Clinical-case mode**
> - Turn any post into a **case**: readers submit a diagnosis, ask the author questions, and earn configurable **CPD hours** once the outcome is revealed.
> - Difficulty multipliers, a best-answer bonus, and a kill-switch to disable CPD awarding without a code change.
>
> **Engagement**
> - Opt-in **email notifications** for new posts — an immediate per-post alert, or an aggregated daily or weekly digest.
> - An optional public **RSS feed** of recent posts.
>
> **Administration**
> - CLI **bulk import and export** of posts via a shared CSV format. (The CSV carries the core post fields plus the featured and panorama images; clinical-case data and images embedded in the post body are not included.)
> - Custom CSS scoped to the plugin's own pages.
> - Full **Privacy API** support (export and erasure), with erasure that preserves other participants' case contributions.
>
> Requires Moodle 5.0+ and PHP 8.2+. Tested on PostgreSQL, MySQL and MariaDB.
> Released under the GNU GPL v3 or later.

## Screenshots

Capture as PNG, ~1280px wide, cropped to the content area. Use a demo site with a
few sample posts and one case so the shots look populated; light theme for
consistency.

1. **Blog listing** (`/local/imageblog/index.php`) — card grid with the filter bar.
   _Caption: "Image-led blog listing with category, tag and difficulty filters."_
2. **Single post** (`view.php`) — featured image + rich body.
   _Caption: "A blog post with a featured image and rich content."_
3. **360° panorama** — a post with a panorama loaded (drag-to-look).
   _Caption: "Optional 360° equirectangular panoramas."_
4. **Clinical case panel** — diagnosis form, Q&A and a revealed outcome/CPD.
   _Caption: "Clinical-case mode: diagnose, ask questions and earn CPD."_
5. **Settings** (Site admin → Plugins → Local plugins → Image blog) — CPD,
   subscription-digest and RSS options.
   _Caption: "CPD, subscription-digest and RSS settings."_

## Submission checklist

| Field | Value |
| --- | --- |
| Plugin type | Local plugin |
| Component (frankenstyle) | `local_imageblog` |
| Supported Moodle versions | 5.0, 5.1, 5.2 |
| Source control URL | https://github.com/verzog/moodle-local_imageblog |
| Bug tracker URL | https://github.com/verzog/moodle-local_imageblog/issues |
| Documentation | repository README |
| Licence | GNU GPL v3 or later |
| Short description / Description | see sections above |
| Screenshots | see section above |

Steps:

1. **Check the name is free** — visit https://moodle.org/plugins/local_imageblog;
   a "not found" page means the frankenstyle name is available.
2. **Build the release ZIP** — zip the plugin so the top-level folder is
   `imageblog` (i.e. `imageblog/version.php`), excluding `.git`. Tag the matching
   release on GitHub so the directory can track versions.
3. **Maturity** — the plugin is registered as `MATURITY_BETA`; confirm that is
   the intended maturity before submitting, or bump to stable first.
4. Submit at **moodle.org/plugins → Register a plugin**: paste the short
   description and description, upload the ZIP and screenshots, and set the
   version-support and licence fields.
5. An approver runs the automated prechecker (mirrored by this repo's CI) and may
   leave review comments in the tracker — respond there.
