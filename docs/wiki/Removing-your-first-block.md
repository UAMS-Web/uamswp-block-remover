# Removing your first block

<!-- cspell:ignore multisite -->

Written against v1.3.1.

At the end of this page, one block you choose is gone from the block inserter on your site, and you know where to put it back.

This walkthrough uses a single site (the plugin activated on one site, not network-activated). On a network, the same steps run from the network admin; see [Running on a multisite network](Running-on-a-multisite-network.md).

1. **Activate the plugin** under *Plugins*. Activation stores the [default blocks](Default-blocks.md) as the site's list, so 54 core blocks (Archives, Audio, Cover, Gallery and others) are already gone from the inserter.
2. **Open *Settings*, *Block Remover*.** The page shows *Loading block list…* while it reads every registered block from the REST API, then a checkbox grid grouped by namespace (`core`, then one group per plugin that registers blocks). A checked box is a block that is removed.
3. **Type the block's name in *Filter blocks…*,** for example `quote`. Only matching blocks stay visible.
4. **Check the block** (`core/quote` in this example) and select **Save Changes**.
5. **Open the post editor and the block inserter.** The block is no longer offered.
6. **To put it back,** uncheck it on the same page and save again.

**Note:** removing a block hides it from the inserter only. A post that already contains it keeps the content; see [How blocks are removed](How-blocks-are-removed.md).

**Note:** *Reset to Defaults* replaces the whole list with the [default blocks](Default-blocks.md) and clears every hidden embed provider. It asks for confirmation first.

## Where to go next

- [Hiding embed providers](Hiding-embed-providers.md), to keep the Embed block but hide some providers.
- [Settings](Settings.md), for every control on the page and where the list is stored.
