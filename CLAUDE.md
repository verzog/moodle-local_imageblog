# CLAUDE.md — Working on this Moodle plugin

Guidance for Claude when developing `local_imageblog` (and Moodle plugins
generally). The rules below are written from real mistakes made on this
codebase — treat them as a pre-flight checklist, not background reading.

## Hard-won rules (we got these wrong before)

### 1. Language files: strict ordering, no section comments
`phpcs` runs in CI as `moodle-plugin-ci phpcs --max-warnings 0`, so
**warnings fail the build**. The `moodle.Files.LangFilesOrdering` sniff
requires:

- Every `$string['key']` in **ascending byte order** (`strcmp` / `LC_ALL=C
  sort` — case-sensitive, `:` < `A` < `_` < `a`).
- **No comments interspersed** between strings (the `// Navigation.`
  style dividers are flagged as `UnexpectedComment`). Only the license
  header comment is allowed.

When adding strings, add them anywhere then re-sort the whole file. Verify:

```bash
grep -oP "^\$string\['\K[^']+" lang/en/local_imageblog.php > /tmp/k
LC_ALL=C sort /tmp/k | diff - /tmp/k && echo "ORDER OK"
```

Lang files do **not** get a `defined('MOODLE_INTERNAL') || die();` guard.

### 2. `fullname()` needs the full name field set
Never hand-pick `u.firstname, u.lastname` for a record passed to
`fullname()` — it raises an `E_USER_NOTICE` about missing
`firstnamephonetic, lastnamephonetic, middlename, alternatename`. Use:

```php
$namefields = \core_user\fields::for_name()->get_sql('u', true)->selects;
$sql = "SELECT DISTINCT u.id{$namefields} FROM {user} u ...";
```

(Also: with `SELECT DISTINCT`, every `ORDER BY` column must be in the
select list — PostgreSQL enforces this in CI.)

### 3. Rewrite `@@PLUGINFILE@@` before formatting
Editor/textarea content saved via `file_postupdate_standard_editor()` is
stored with `@@PLUGINFILE@@` placeholders. Embedded images **404 on the
rendered page** (but work in the editor's draft area) unless you call
`file_rewrite_pluginfile_urls()` before `format_text()`:

```php
format_text(
    file_rewrite_pluginfile_urls($html, 'pluginfile.php',
        $context->id, 'local_imageblog', post::FILEAREA_BODY, $post->id),
    $format, ['context' => $context]
);
```

Every file area you serve **must also be whitelisted** in the plugin's
`local_imageblog_pluginfile()` callback (`$allowedareas`), or it 404s
even after rewriting.

### 4. AMD: edit src, rebuild build, bump version
`amd/src/*.js` is **not** what runs — Moodle loads `amd/build/*.min.js`.
After editing source you must regenerate the minified bundle (grunt). If
grunt isn't available, produce an equivalent hand-minified build matching
the existing wrapper style. Then **bump `version.php`** and tell the user
to *Purge all caches* — JS, Mustache templates and lang strings are
cached by plugin version.

### 5. Bump `version.php` for any cached asset change
Templates, AMD, lang strings, DB schema, capabilities — all require a
higher `$plugin->version` to take effect on an existing install. When
multiple PRs are in flight, give each a distinct version number to avoid
collisions on merge.

### 6. Optional file areas need an explicit clear-on-disable
`file_prepare_draft_area()` always populates the draft, so a hidden
filemanager still round-trips its existing files. If a toggle disables an
optional upload, **delete the area on save** when the toggle is off —
skipping the save call leaves the old file in place:

```php
if (!empty($data->haspanorama)) {
    file_save_draft_area_files(...);
} else {
    get_file_storage()->delete_area_files($context->id,
        'local_imageblog', self::FILEAREA_PANORAMA, $record->id);
}
```

Use `advcheckbox` + `$mform->hideIf('panorama_image', 'haspanorama',
'notchecked')` for the reveal.

### 7. Don't chase infrastructure failures as code bugs
If **every** CI matrix job fails identically within a few seconds
(before the install step), it is almost never the code — suspect a
retired runner image, an org Actions policy, or **billing/spending
limits**. Diagnose before "fixing": a runner-image bump wasted a cycle
here when the real cause was a GitHub Actions billing block. State the
diagnosis and ask rather than blind-pushing workflow edits.

## Security defaults

- JS: set user/content text with `textContent` /
  `document.createTextNode`, never `innerHTML`.
- Inline `<style>`/`<script>` injection from settings: strip the closing
  tag (`str_ireplace('</style', ...)`) to prevent breakout.
- Scope admin "custom CSS/JS" to the plugin's own pages unless the user
  explicitly wants site-wide — a bad rule shouldn't break all of Moodle.
- Validate at boundaries with the correct `PARAM_*`; trust internal code.
- Never commit secrets. If a user pastes a stack/dump containing live
  cookies, tokens or credentials, flag it and recommend rotation — do not
  echo the decoded values back.

## Plugin structure / boilerplate

- `README.md` follows the `moodle-tool_pluginskel` template: short
  description, "Installing via uploaded ZIP file", "Installing manually",
  Requirements, License (GPLv3 block matching the file headers).
- Bundled third-party libraries: declare in `thirdpartylibs.xml`, keep
  the upstream `LICENSE` in-tree, and attribute in the README (name,
  version, license, copyright holder, upstream URL, "unmodified").
- Standard headers on every PHP file (`// This file is part of
  Moodle...` + `@package/@copyright/@license` docblock).
- `lib.php` containing only function declarations should **not** have a
  `MOODLE_INTERNAL` guard (phpcs flags it as unnecessary).

## Pre-PR checklist

- [ ] `php -l` clean on every changed `.php` file.
- [ ] Lang file re-sorted; no interspersed comments.
- [ ] `@@PLUGINFILE@@` rewritten anywhere editor content is rendered.
- [ ] New file areas whitelisted in `*_pluginfile()`.
- [ ] AMD `build/` regenerated if `src/` changed.
- [ ] `version.php` bumped (distinct number if parallel PRs).
- [ ] User-facing strings exist in `lang/en/` (no hard-coded text).
- [ ] No `innerHTML` with untrusted/user data.

## Workflow

- One concern per PR; branch off the latest `main`. If a branch falls
  behind merged work, rebase onto `origin/main` and force-with-lease.
- Open PRs ready for review (not draft); keep PR bodies to a summary +
  test plan.
- CI is `moodle-plugin-ci` (phplint, phpcs `--max-warnings 0`, phpdoc,
  validate, savepoints, phpunit `--fail-on-warning`, behat) across a
  PHP × Moodle-branch matrix. Assume warnings = failure.
