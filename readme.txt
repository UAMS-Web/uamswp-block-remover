=== Block Remover ===
Tags:              gutenberg, blocks, editor, restrict
Requires at least: 6.8
Tested up to:      6.8
Requires PHP:      8.2
Stable tag:        1.3.1
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Remove specific Gutenberg blocks from the editor, with sensible hardcoded defaults and a UI to override them.

== Description ==

Block Remover controls which blocks the editor offers:

1. **Default list:** the first time the plugin is activated, a curated list of core blocks (Archives, Audio, Calendar, Cover, Gallery and others) is stored as the removed set.
2. **Settings page:** *Settings, Block Remover* (on a network-activated install, *Network Admin, Settings, Block Remover*) lists every registered block, core and third-party, as a checkbox grid grouped by namespace. A checked block is removed. A second section, *Embed Variations*, hides individual embed providers (YouTube, Vimeo and others) while keeping the Embed block.

Blocks are removed on the server: the plugin filters `allowed_block_types_all` and returns the allowed blocks minus the removed ones. Nothing is unregistered, so a removed block is no longer offered in the inserter while content that already uses it is kept.

Embed providers are variations defined in JavaScript, so they are filtered in the browser: when at least one provider is hidden, a small editor script drops those variations from `core/embed` as it registers.

== Installation ==

1. Upload the `uamswp-block-remover` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings, Block Remover** to change the list.

== Frequently Asked Questions ==

= Will this break existing content that uses a removed block? =
No. The block type stays registered, so a post that already contains the block keeps it, the editor still recognizes it, and the front end renders it as before. Removing a block only stops new copies from being inserted.

= Can I remove third-party blocks (ACF, Gravity Forms, etc.)? =
Yes. The settings page reads every registered block from the REST API (`/wp/v2/block-types`), so third-party blocks appear in the checkbox grid under their own namespace. Check the block and save.

= How do I reset to the original defaults? =
Click the **Reset to Defaults** button on the settings page. It restores the default block list and clears every hidden embed provider.

== Changelog ==

= 1.3.1 =
* The `core/post-template` block is now blocked by default; the default list named it `core/core/post-template`, so it was not.

= 1.3.0 =
* First tagged release. The plugin header, the version constant and the stable tag now agree.

= 1.0.0 =
* Initial release.
