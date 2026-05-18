# Capturing the WordPress.org listing screenshots

These three PNGs are referenced in `readme.txt` (`== Screenshots ==` section). Save them in this directory using the exact filenames listed below. They go into the WordPress.org SVN repository's `assets/` directory at release time — they do **not** ship inside the plugin ZIP.

## Recommended capture settings

- **Browser**: Chrome or Firefox at 100% zoom.
- **Window width**: 1280 px (a roomy laptop width).
- **Format**: PNG, no compression artifacts.
- **DPI**: native (capture at 1x, not 2x). WordPress.org displays screenshots at ~770 px wide, so anything larger than ~1600 px wide is wasted bytes.
- **Crop**: trim to just the relevant editor / admin region. Drop the WP admin toolbar unless it adds useful context.

## Setup before capturing

1. Activate **RelateWP** in `wp-content/plugins/relatewp/`.
2. In the active theme's `functions.php` (or a small mu-plugin) register a few demo relationships on `relatewp_init` so the screens look populated, e.g.:
   - `Schema::belongsToMany( 'post', 'post', 'related_posts', [ 'symmetric' => true, 'labels' => [ 'from' => 'Related Posts', 'to' => 'Related Posts' ] ] )`
   - A second relationship between Posts and Pages so the sidebar shows multiple panels.
3. Create or pick a Post that has two or three related items connected, so list/grid layouts have real content.

## Required screenshots

Filenames must match exactly — `readme.txt` references them by number.

### `screenshot-1.png` — Gutenberg sidebar panel
- **Caption (already in `readme.txt`):** Gutenberg sidebar panel for managing related content.
- **State:** Edit a post that has the RelateWP sidebar open. Expand the panel so the search input is visible and 2-3 connected items are listed beneath it (with their drag handles and remove buttons). Crop tight around the sidebar.

### `screenshot-2.png` — Related Content block, grid layout
- **Caption:** Related Content block with grid layout.
- **State:** Inside the editor, insert the **Related Content** block, pick a relationship, switch its layout to **Grid** with thumbnails enabled. Show the block selected with its toolbar visible so reviewers can see the layout switcher. Real post thumbnails should be loaded.

### `screenshot-3.png` — Admin column with related items
- **Caption:** Admin column showing connected items.
- **State:** Go to **Posts → All Posts** (or whichever post type has the relationship) and capture the list table with the auto-added "Related …" column populated for at least three rows.

## After capture

Drop the three PNGs into this folder. They'll be copied to the WordPress.org SVN `assets/` directory alongside the rendered icon and banner during the SVN push.
