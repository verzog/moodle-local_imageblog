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

* Moodle 5.0 (2025041100) or later.

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

2026 Vernon Spain

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
