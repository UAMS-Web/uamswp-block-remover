=== Block Remover ===
Contributors:      yourname
Tags:              gutenberg, blocks, editor, restrict
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.2
Stable tag:        1.3.1
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Remove specific Gutenberg blocks from the editor, with sensible hardcoded defaults and a UI to override them.

== Description ==

Block Remover gives you two layers of control over which Gutenberg blocks appear in the editor:

1. **Hardcoded defaults** — a curated list of blocks that are removed out-of-the-box (Archives, Audio, Calendar, Classic, Cover, etc.). These are the starting point if you've never saved settings.
2. **Admin settings page** — a full UI under *Settings → Block Remover* with checkboxes for every core block plus a textarea for custom/third-party block names.

Blocks are removed client-side via `wp.blocks.unregisterBlockType()` on `domReady`, so they disappear from the inserter, toolbar, and block transforms — without touching any content already using those blocks.

== Installation ==

1. Upload the `block-remover` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings → Block Remover** to customise the list.

== Frequently Asked Questions ==

= Will this break existing content that uses a removed block? =
No. Removing a block from the registry only hides it from the inserter. Posts that already contain the block will show a "block is not registered" notice, but the content is safe and unaffected.

= Can I remove third-party blocks (ACF, Gravity Forms, etc.)? =
Yes — add their block names (e.g. `acf/my-block`) in the **Custom / Third-Party Blocks** textarea on the settings page.

= How do I reset to the original defaults? =
Click the **Reset to Defaults** button on the settings page.

== Changelog ==

= 1.3.1 =
* The `core/post-template` block is now blocked by default; the default list named it `core/core/post-template`, so it was not.

= 1.3.0 =
* First tagged release. The plugin header, the version constant and the stable tag now agree.

= 1.0.0 =
* Initial release.
