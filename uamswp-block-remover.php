<?php

declare(strict_types=1);
/**
 * Plugin Name:       UAMSWP Block Remover
 * Plugin URI:        https://example.com
 * Description:       Remove specific Gutenberg blocks from the editor. Network-activates across all subsites; site-activates for a single site.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            UAMS Web Services
 * Author URI:        https://communications.uams.edu/web/
 * License:           GPL v2 or later
 * Text Domain:       uamswp-block-remover
 */
if (! defined('ABSPATH')) {
    exit;
}

define('UAMSWP_BLOCK_REMOVER_VERSION', '1.3.0');
define('UAMSWP_BLOCK_REMOVER_DIR', plugin_dir_path(__FILE__));
define('UAMSWP_BLOCK_REMOVER_URL', plugin_dir_url(__FILE__));
define('UAMSWP_BLOCK_REMOVER_OPTION', 'uamswp_block_remover_settings');

// ---------------------------------------------------------------------------
// Helpers: is the plugin network-active?
// ---------------------------------------------------------------------------

/**
 * Returns true when the plugin is activated at the network level.
 */
function uamswp_block_remover_is_network_active(): bool
{
    if (! is_multisite()) {
        return false;
    }

    $network_plugins = get_site_option('active_sitewide_plugins', []);
    $plugin_basename = plugin_basename(__FILE__);

    return isset($network_plugins[$plugin_basename]);
}

// ---------------------------------------------------------------------------
// Default blocks
// ---------------------------------------------------------------------------

function uamswp_block_remover_default_blocks(): array
{
    return [
        'core/archives',
        'core/audio',
        'core/avatar',
        'core/calendar',
        'core/classic',
        'core/categories',
        'core/column',
        'core/columns',
        'core/comment-author-name',
        'core/comment-content',
        'core/comment-date',
        'core/comment-edit-link',
        'core/comment-reply-link',
        'core/comment-template',
        'core/comments',
        'core/comments-pagination',
        'core/comments-pagination-next',
        'core/comments-pagination-previous',
        'core/comments-title',
        'core/cover',
        'core/gallery',
        'core/latest-comments',
        'core/latest-posts',
        'core/loginout',
        'core/missing',
        'core/more',
        'core/nextpage',
        'core/post-author',
        'core/post-author-biography',
        'core/post-author-name',
        'core/post-comments',
        'core/post-comments-form',
        'core/post-navigation-link',
        'core/core/post-template',
        'core/post-terms',
        'core/query',
        'core/query-no-results',
        'core/query-pagination',
        'core/query-pagination-next',
        'core/query-pagination-numbers',
        'core/query-pagination-previous',
        'core/query-title',
        'core/query-total',
        'core/rss',
        'core/search',
        'core/social-links',
        'core/spacer',
        'core/table-of-contents',
        'core/tag-cloud',
        'core/template-part',
        'core/term-description',
        'core/text-columns',
        'core/verse',
        'core/video',
    ];
}

/**
 * No embed variations are blocked by default — let the admin choose.
 */
function uamswp_block_remover_default_blocked_embeds(): array
{
    return [];
}

/**
 * Master list of all known core/embed variations shipped with WordPress.
 * Keyed by variation name (used in JS); value is the human label.
 * Update this list when WordPress adds new embed providers.
 *
 * Source: wp-includes/blocks/embed.php  (register_block_type variations)
 */
function uamswp_block_remover_known_embed_variations(): array
{
    return [
        'amazon-kindle' => 'Amazon Kindle',
        'animoto' => 'Animoto',
        'cloudup' => 'Cloudup',
        'crowdsignal' => 'Crowdsignal',
        'dailymotion' => 'Dailymotion',
        'flickr' => 'Flickr',
        'imdb' => 'IMDb',
        'imgur' => 'Imgur',
        'instagram' => 'Instagram',
        'issuu' => 'Issuu',
        'kickstarter' => 'Kickstarter',
        'mixcloud' => 'Mixcloud',
        'pinterest' => 'Pinterest',
        'pocket-casts' => 'Pocket Casts',
        'reddit' => 'Reddit',
        'reverbnation' => 'ReverbNation',
        'screencast' => 'Screencast',
        'scribd' => 'Scribd',
        'slideshare' => 'SlideShare',
        'smugmug' => 'SmugMug',
        'soundcloud' => 'SoundCloud',
        'speaker-deck' => 'Speaker Deck',
        'spotify' => 'Spotify',
        'ted' => 'TED',
        'tiktok' => 'TikTok',
        'tumblr' => 'Tumblr',
        'twitter' => 'X (Twitter)',
        'videopress' => 'VideoPress',
        'vimeo' => 'Vimeo',
        'wolfram-cloud' => 'Wolfram Cloud',
        'wordpress' => 'WordPress',
        'wordpress-tv' => 'WordPress.tv',
        'youtube' => 'YouTube',
    ];
}

// ---------------------------------------------------------------------------
// Option read/write abstraction
// Switches between get_site_option (network) and get_option (site) so the
// rest of the plugin doesn't need to care which context it's running in.
// ---------------------------------------------------------------------------

function uamswp_block_remover_get_settings(): array
{
    $default = [
        'blocked' => uamswp_block_remover_default_blocks(),
        'blocked_embeds' => uamswp_block_remover_default_blocked_embeds(),
    ];

    if (uamswp_block_remover_is_network_active()) {
        $settings = get_site_option(UAMSWP_BLOCK_REMOVER_OPTION, false);
    } else {
        $settings = get_option(UAMSWP_BLOCK_REMOVER_OPTION, false);
    }

    if ($settings === false || ! is_array($settings)) {
        return $default;
    }

    // Merge so any keys added in future versions are always present.
    return array_merge($default, $settings);
}

function uamswp_block_remover_update_settings(array $settings): bool
{
    if (uamswp_block_remover_is_network_active()) {
        return update_site_option(UAMSWP_BLOCK_REMOVER_OPTION, $settings);
    }

    return update_option(UAMSWP_BLOCK_REMOVER_OPTION, $settings);
}

function uamswp_block_remover_delete_settings(): void
{
    if (uamswp_block_remover_is_network_active()) {
        delete_site_option(UAMSWP_BLOCK_REMOVER_OPTION);
    } else {
        delete_option(UAMSWP_BLOCK_REMOVER_OPTION);
    }
}

// ---------------------------------------------------------------------------
// Activation / deactivation
// ---------------------------------------------------------------------------

/**
 * Seeds network-level option on network activation.
 * The $network_wide flag is true when activated network-wide.
 */
function uamswp_block_remover_activate(bool $network_wide): void
{
    $seed = [
        'blocked' => uamswp_block_remover_default_blocks(),
        'blocked_embeds' => uamswp_block_remover_default_blocked_embeds(),
    ];

    if ($network_wide && is_multisite()) {
        if (get_site_option(UAMSWP_BLOCK_REMOVER_OPTION) === false) {
            update_site_option(UAMSWP_BLOCK_REMOVER_OPTION, $seed);
        }
    } else {
        if (get_option(UAMSWP_BLOCK_REMOVER_OPTION) === false) {
            update_option(UAMSWP_BLOCK_REMOVER_OPTION, $seed);
        }
    }
}

register_activation_hook(__FILE__, 'uamswp_block_remover_activate');

function uamswp_block_remover_uninstall(): void
{
    if (is_multisite()) {
        delete_site_option(UAMSWP_BLOCK_REMOVER_OPTION);

        // Also clean up any per-site options left behind.
        $sites = get_sites(['number' => 0, 'fields' => 'ids']);
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            delete_option(UAMSWP_BLOCK_REMOVER_OPTION);
            restore_current_blog();
        }
    } else {
        delete_option(UAMSWP_BLOCK_REMOVER_OPTION);
    }
}

register_uninstall_hook(__FILE__, 'uamswp_block_remover_uninstall');

// ---------------------------------------------------------------------------
// Load admin UI
// ---------------------------------------------------------------------------

require_once UAMSWP_BLOCK_REMOVER_DIR.'admin/settings-page.php';

// ---------------------------------------------------------------------------
// Block filtering via allowed_block_types_all (PHP, server-side).
//
// This is the correct approach — it returns an allowlist to the editor before
// the block registry is built, so we never mutate registered block types and
// avoid the recursive dependency-resolver stack overflow that
// wp.blocks.unregisterBlockType() can trigger on blocks with parent/ancestor
// relationships (e.g. core/column inside core/columns).
// ---------------------------------------------------------------------------

/**
 * Filter the allowed block types by removing any blocks on the blocked list.
 *
 * Runs at priority 9999 so we always have the final say, regardless of what
 * other plugins set. Handles all three states $allowed can arrive in:
 *   - true  (all blocks allowed — the WP default)
 *   - false (no blocks allowed — unlikely but valid)
 *   - array (explicit allowlist set by another plugin/theme)
 *
 * @param  bool|string[]  $allowed  True (all allowed), false (none), or an array of block names.
 * @param  WP_Block_Editor_Context  $context  Editor context.
 * @return bool|string[]
 */
function uamswp_block_remover_filter_blocks($allowed, WP_Block_Editor_Context $context)
{
    $settings = uamswp_block_remover_get_settings();

    $blocked = isset($settings['blocked']) && is_array($settings['blocked'])
        ? $settings['blocked']
        : uamswp_block_remover_default_blocks();

    // Nothing blocked — preserve whatever $allowed already is.
    if ($blocked === []) {
        return $allowed;
    }

    // false = editor already allows nothing, nothing for us to do.
    if ($allowed === false) {
        return $allowed;
    }

    $blocked_set = array_flip($blocked);

    // Another plugin already produced an explicit allowlist — subtract from it.
    if (is_array($allowed)) {
        return array_values(
            array_filter($allowed, fn (string $name): bool => ! isset($blocked_set[$name]))
        );
    }

    // $allowed === true: build the full list from the registry and subtract.
    // Using get_all_registered() here is safe — this filter fires during
    // block_editor_settings_all on admin_init, by which point all register_block_type()
    // and register_block_type_from_metadata() calls have completed.
    $all_names = array_keys(
        WP_Block_Type_Registry::get_instance()->get_all_registered()
    );

    return array_values(
        array_filter($all_names, fn (int|string $name): bool => ! isset($blocked_set[$name]))
    );
}

add_filter('allowed_block_types_all', 'uamswp_block_remover_filter_blocks', 9999, 2);

// ---------------------------------------------------------------------------
// Embed variation filtering — client-side only.
//
// core/embed variations (YouTube, Vimeo, etc.) are pure JS constructs;
// they have no PHP block registration and can't be controlled via
// allowed_block_types_all. We use the blocks.registerBlockType JS filter
// (wp.hooks) to strip unwanted variations from core/embed as it registers,
// which is safe and doesn't touch already-registered types.
// ---------------------------------------------------------------------------

function uamswp_block_remover_enqueue_embed_filter(): void
{
    $settings = uamswp_block_remover_get_settings();
    $blocked_embeds = isset($settings['blocked_embeds']) && is_array($settings['blocked_embeds'])
        ? array_values($settings['blocked_embeds'])
        : [];

    // Nothing to do if no embed variations are blocked.
    if ($blocked_embeds === []) {
        return;
    }

    wp_enqueue_script(
        'uamswp-block-remover-embeds',
        UAMSWP_BLOCK_REMOVER_URL.'assets/js/embed-filter.js',
        ['wp-hooks', 'wp-blocks'],
        UAMSWP_BLOCK_REMOVER_VERSION,
        true
    );

    wp_localize_script(
        'uamswp-block-remover-embeds',
        'uamswpBREmbeds',
        ['blockedEmbeds' => $blocked_embeds]
    );
}

add_action('enqueue_block_editor_assets', 'uamswp_block_remover_enqueue_embed_filter');
