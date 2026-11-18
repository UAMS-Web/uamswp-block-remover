# Hiding embed providers

Written against v1.3.1.

The Embed block (`core/embed`) offers one variation per provider: YouTube, Vimeo, TikTok and so on. Removing `core/embed` removes all of them. To keep the block and hide only some providers, use the *Embed Variations* section of the settings page.

1. Open *Settings*, *Block Remover* (on a network: *Network Admin*, *Settings*, *Block Remover*).
2. Scroll to **Embed Variations**. Each provider has a checkbox, its label and its variation name (for example *TikTok*, `tiktok`). The badge beside the heading counts how many are checked.
3. Check each provider to hide, then save.
4. In the post editor, the hidden providers no longer appear in the inserter or in the Embed block's variation list. The other providers, and the generic Embed block, stay available.

**Note:** no provider is hidden by default, and *Reset to Defaults* clears every provider you checked.

**Note:** the provider list is fixed in the plugin (see [Embed providers](Embed-providers.md)). A provider WordPress adds later does not appear on the page until the plugin's list is updated.

## Reference

- [Embed providers](Embed-providers.md): every provider the page lists.
- [Settings](Settings.md): where the choices are stored.
- [How blocks are removed](How-blocks-are-removed.md): why providers are filtered in the browser rather than on the server.
