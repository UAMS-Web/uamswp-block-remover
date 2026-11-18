# Troubleshooting

<!-- cspell:ignore multisite -->

Written against v1.3.1.

- [The settings page says it could not load blocks from the REST API](#the-settings-page-says-it-could-not-load-blocks-from-the-rest-api)
- [There is no Block Remover page under Settings](#there-is-no-block-remover-page-under-settings)
- [A removed block still appears in the inserter](#a-removed-block-still-appears-in-the-inserter)
- [A block that is not on the list is missing from the inserter](#a-block-that-is-not-on-the-list-is-missing-from-the-inserter)
- [A new embed provider is not on the settings page](#a-new-embed-provider-is-not-on-the-settings-page)

## The settings page says it could not load blocks from the REST API

`Could not load blocks from the REST API. Check that the REST API is accessible.`, followed by the error in parentheses.

**Cause:** the page builds its block grid by reading `/wp/v2/block-types` (on a network, from the main site). A security plugin or server rule that blocks the REST API, or a main site that cannot be reached from the network admin, stops the read.

**Fix:** confirm that a logged-in administrator can open `https://example.org/wp-json/wp/v2/block-types` (with your own site's address). Allow the REST API for logged-in users, then reload the settings page. The embed providers section does not need the REST API and still works.

## There is no Block Remover page under Settings

**Cause:** the plugin is network-activated, so its page is in the network admin only, or your account lacks the capability (`manage_options` on a site, `manage_network_options` on a network).

**Fix:** open *Network Admin*, *Settings*, *Block Remover* as a super admin. See [Running on a multisite network](Running-on-a-multisite-network.md).

## A removed block still appears in the inserter

**Cause:** the block is not on the saved list (a reset or another administrator's save replaced it), or the editor page was loaded before the save.

**Fix:** open the settings page, filter for the block, confirm it is checked, save, then reload the editor. A block that already sits in a post stays there; removal only stops new copies being inserted ([How blocks are removed](How-blocks-are-removed.md)).

## A block that is not on the list is missing from the inserter

A block that the settings page does not show as checked (or does not show at all) cannot be inserted.

**Cause:** when another plugin or the theme does not narrow the allowed blocks, the plugin builds the allow-list from blocks registered on the server. A block registered only by JavaScript is not on that list, so as soon as any block is removed it is not offered either.

**Fix:** register the block on the server as well (for example with `register_block_type()` and its `block.json`), which most block plugins do. When nothing at all is removed, the plugin leaves the allowed blocks unchanged.

## A new embed provider is not on the settings page

**Cause:** the provider list on the settings page is maintained by hand ([Embed providers](Embed-providers.md)), so a provider added by a newer WordPress is not listed and cannot be hidden.

**Fix:** report it on the [issue tracker](https://github.com/UAMS-Web/uamswp-block-remover/issues) so the list can be updated. Until then, the provider stays available in the Embed block.
