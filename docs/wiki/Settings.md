# Settings

<!-- cspell:ignore multisite uamswp -->

Written against v1.3.1.

The plugin has one settings page and stores everything in one option. This page lists where the page is, what each control does and what is stored.

## Where the page is

| Activation | Menu | Capability to view and save |
| --- | --- | --- |
| Single site, or one site of a network | *Settings*, *Block Remover* | `manage_options` |
| Network-activated | *Network Admin*, *Settings*, *Block Remover* | `manage_network_options` |

When the plugin is network-activated, no site has the page; see [Running on a multisite network](Running-on-a-multisite-network.md).

## Controls

| Control | Effect |
| --- | --- |
| *Filter blocks…* | Shows only blocks whose name or title matches the text. It changes nothing stored. |
| *Select all*, *Deselect all* | Checks or unchecks every block the filter currently shows. |
| Block checkboxes | One per registered block type, grouped by namespace. Checked means removed from the editor. |
| *Embed Variations* checkboxes | One per [embed provider](Embed-providers.md). Checked means the provider is hidden from the Embed block. |
| *Save Changes* (site) or *Save Network Settings* (network) | Stores the checked blocks and providers, replacing the stored list. |
| *Reset to Defaults* | After a confirmation, stores the [default blocks](Default-blocks.md) and no hidden providers. |

The block grid is read from the REST API (`/wp/v2/block-types`) when the page loads, so it lists every block registered at that moment, core and third-party alike. A third-party block is removed the same way as a core one: find it in its namespace group and check it.

## The stored option

| | |
| --- | --- |
| Option name | `uamswp_block_remover_settings` |
| Stored with | `get_option()` / `update_option()` on a site; `get_site_option()` / `update_site_option()` when network-activated |
| Created | on activation, with the defaults, when no option exists yet |
| Removed | when the plugin is deleted (on a network: the network option and every site's option) |

The option is an array with two keys:

| Key | Type | Default | Effect |
| --- | --- | --- | --- |
| `blocked` | list of block names | the [default blocks](Default-blocks.md) | Each block is removed from the editor. |
| `blocked_embeds` | list of embed variation names | empty | Each provider is hidden from `core/embed`. |

Example of a stored value:

```php
[
    'blocked'        => ['core/archives', 'core/audio', 'example-plugin/example-block'],
    'blocked_embeds' => ['tiktok', 'spotify'],
]
```

**Note:** when no option is stored, or the stored value is not an array, the defaults apply. A missing key is filled from the defaults, so an option saved without `blocked_embeds` hides no provider.

## What a save accepts

- **Block names** must look like `namespace/block-name`: lowercase letters, digits and hyphens on each side of one slash. Anything else is dropped, and duplicates are removed.
- **Embed variation names** must be one of the names on [Embed providers](Embed-providers.md). Anything else is dropped.
