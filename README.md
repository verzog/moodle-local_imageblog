# Image blog #

A Moodle local plugin that provides a site-wide image-led blog. Posts support a
featured image, rich body content, optional 360° equirectangular panoramas, a
taxonomy of categories, subcategories, tags and difficulty levels, and an
optional "clinical case" mode in which readers submit a diagnosis, ask the
author questions, and earn CPD hours once the outcome is revealed. Readers can
also opt in to periodic email digests of newly published posts.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/imageblog

Afterwards, log in to your Moodle site as an admin and go to _Site
administration > Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Requirements ##

* Moodle 5.0 (2025041100) or later (tested up to Moodle 5.2).
* PHP 8.2, 8.3 or 8.4 (per the requirements of the Moodle version in use).

## Bulk import and export ##

Two CLI tools share a common CSV format (columns: `title`, `summary`, `body`,
`status`, `timepublished`, `author_email`, `category`, `subcategory`, `tags`,
`levels`, `featured_image`, `panorama_image`), so an export from one site can
be re-imported into another.

Import legacy posts from a CSV plus an optional directory of images:

    $ php local/imageblog/cli/import.php --csv=/path/posts.csv \
          --imagedir=/path/images --fallback-author=admin [--dry-run]

Export posts (and their featured/panorama images) to the same format:

    $ php local/imageblog/cli/export.php --csv=/path/posts.csv \
          --imagedir=/path/images [--status=published] [--overwrite]

Run either script with `--help` for the full option list.

## Uninstalling ##

Uninstalling the plugin via _Site administration > Plugins > Plugins
overview_ removes all plugin database tables, settings, capabilities,
scheduled tasks and stored files, and also deletes the custom "Blog author"
role the plugin creates (including any assignments of that role).

## Third-party libraries ##

This plugin bundles the [Pannellum](https://github.com/mpetroff/pannellum)
panorama viewer to render the optional 360° equirectangular images. Pannellum
version 2.5.6 is shipped under `thirdparty/pannellum/` and is also declared in
`thirdpartylibs.xml`.

Pannellum is Copyright (c) 2011-2019 Matthew Petroff and is distributed under
the MIT License. The full license text is included alongside the library at
`thirdparty/pannellum/LICENSE`. The bundled files are used unmodified; no
changes have been made to the upstream source.

> Petroff, Matthew A. "Pannellum: a lightweight web-based panorama viewer."
> _Journal of Open Source Software_ 4, no. 40 (2019): 1628.
> [doi:10.21105/joss.01628](https://doi.org/10.21105/joss.01628)

## License ##

Copyright (c) Vernon Apain / Educheckout. All rights reserved.

This is a proprietary plugin. It is **not** free software and is **not**
released under the GNU General Public License. It is intended for in-house use
only and is **not** for publication to the Moodle Plugins directory.

Unauthorised copying, distribution, modification, or use of this software, in
whole or in part, via any medium, is strictly prohibited without the prior
written permission of Educheckout. The software is provided "as is", without
warranty of any kind, express or implied.

Note: the bundled Pannellum library (see _Third-party libraries_ above) remains
under its own MIT license.
