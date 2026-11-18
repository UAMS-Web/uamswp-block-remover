# Running on a multisite network

<!-- cspell:ignore multisite subsite -->

Written against v1.3.1.

On a multisite network the plugin works in one of two modes, decided by how it is activated. Network activation gives one list for every site; activating it on individual sites gives each site its own list.

## Network activation: one list for the whole network

1. In *Network Admin*, *Plugins*, select **Network Activate**. Activation stores the [default blocks](Default-blocks.md) as the network's list, unless a network list already exists.
2. Open *Network Admin*, *Settings*, *Block Remover*. A notice at the top says the settings apply to every subsite.
3. Check blocks and embed providers as on a single site, then select **Save Network Settings**. A *Network block list saved.* notice confirms it.

What changes in this mode:

- **One list applies to every site.** It is stored as a network option, and no per-site list is read.
- **Site administrators see no settings page.** The page exists only in the network admin, and saving it needs the `manage_network_options` capability (a super admin).
- **The block grid is read from the main site.** The network admin has no block-types REST endpoint, so the page asks the main site's REST API. Block types are registered network-wide, so the list is the same; a block registered only on one subsite (by a plugin activated only there) does not appear.

## Activation on individual sites

Activating the plugin on a site (not network-wide) works exactly as on a single install: the site gets *Settings*, *Block Remover*, its own list, and the `manage_options` capability is enough to change it. Each site that activates it starts from the [default blocks](Default-blocks.md).

## Uninstalling

Deleting the plugin removes the network list and the list of every site on the network, whichever mode stored them.

## Reference

- [Settings](Settings.md): option name, stored keys and capabilities.
