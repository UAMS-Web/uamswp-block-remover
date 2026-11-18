# How blocks are removed

Written against v1.3.1.

This page says what the plugin changes in the editor and what it leaves alone, which is what decides most of the behavior described in [Troubleshooting](Troubleshooting.md).

## Blocks: an allow-list built on the server

The plugin filters `allowed_block_types_all` at priority 9999, so it runs after almost every other plugin and theme that uses the same filter. The filter receives one of three values, and the plugin answers each:

| The editor would allow | The plugin returns |
| --- | --- |
| every block (`true`, the WordPress default) | every block in the server's block registry, minus the removed ones |
| an explicit list (another plugin or the theme narrowed it) | that list, minus the removed ones |
| nothing (`false`) | `false`, unchanged |

When nothing is removed, the value passes through unchanged.

Because the result is an allow-list, a removed block is not offered in the inserter. Nothing is unregistered: the block type stays registered, so content that uses it is kept.

**Note:** in the first case the list is built from blocks registered on the server. A block registered only in the browser (by JavaScript, with no matching server registration) is not in that list, so once any block is removed it is not offered either. See [Troubleshooting](Troubleshooting.md#a-block-that-is-not-on-the-list-is-missing-from-the-inserter).

## Content that already uses a removed block

The post keeps the block's markup, and the front end renders it as before. The block type is still registered, so the editor does not treat the block as unknown; removing it only stops new copies from being inserted.

## Embed providers: filtered in the browser

Embed providers are variations of `core/embed` defined in JavaScript, so the server's allow-list cannot reach them. When at least one provider is hidden, the plugin loads a small editor script that uses the `blocks.registerBlockType` filter to drop those variations from `core/embed` as it registers. When no provider is hidden, the script is not loaded at all.

## Reference

- [Settings](Settings.md): the stored lists these steps read.
