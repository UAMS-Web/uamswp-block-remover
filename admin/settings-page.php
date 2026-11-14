<?php
/**
 * Admin settings page for UAMSWP Block Remover.
 *
 * Block list is fetched live from the REST API (/wp/v2/block-types) so every
 * registered block — core, plugin, or theme — appears automatically.
 *
 * Registers either:
 *   - A network admin page (Settings → Block Remover) when network-active, or
 *   - A standard subsite options page (Settings → Block Remover) when site-active.
 *
 * Because network admin pages can't use the WP Settings API (options.php is
 * not available there), the network page handles its own form submission.
 */
if (! defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------
// Route hooks based on activation mode.
// ---------------------------------------------------------------------------

add_action('init', 'uamswp_block_remover_register_hooks');

function uamswp_block_remover_register_hooks(): void
{
    if (uamswp_block_remover_is_network_active()) {
        add_action('network_admin_menu', 'uamswp_block_remover_add_network_menu');
        add_action('network_admin_notices', 'uamswp_block_remover_network_notices');
        add_action('admin_enqueue_scripts', 'uamswp_block_remover_admin_assets');
    } else {
        add_action('admin_menu', 'uamswp_block_remover_add_menu');
        add_action('admin_init', 'uamswp_block_remover_register_settings');
        add_action('admin_init', 'uamswp_block_remover_handle_reset', 5);
        add_action('admin_enqueue_scripts', 'uamswp_block_remover_admin_assets');
    }
}

// ---------------------------------------------------------------------------
// Shared: styles + JS
// ---------------------------------------------------------------------------

function uamswp_block_remover_admin_assets(string $hook): void
{
    $allowed_hooks = [
        'settings_page_uamswp-block-remover',
        'settings_page_uamswp-block-remover-network',
    ];

    if (! in_array($hook, $allowed_hooks, true)) {
        return;
    }

    // Pass saved state and REST config to the JS module.
    $settings = uamswp_block_remover_get_settings();
    $blocked = $settings['blocked'] ?? [];
    $is_network = uamswp_block_remover_is_network_active();

    // For network admin we need a REST URL from a real subsite, not the
    // network admin URL (which has no block-types endpoint).
    $rest_url = rest_url('wp/v2/block-types');
    if ($is_network) {
        // Use the main site's REST URL — block types are the same network-wide.
        $main_site_id = get_main_site_id();
        switch_to_blog($main_site_id);
        $rest_url = rest_url('wp/v2/block-types');
        restore_current_blog();
    }

    wp_enqueue_script(
        'uamswp-block-remover-admin',
        UAMSWP_BLOCK_REMOVER_URL.'assets/js/admin-settings.js',
        [],
        UAMSWP_BLOCK_REMOVER_VERSION,
        true
    );

    wp_localize_script(
        'uamswp-block-remover-admin',
        'uamswpBRAdmin',
        [
            'restUrl' => esc_url_raw($rest_url),
            'restNonce' => wp_create_nonce('wp_rest'),
            'savedBlocked' => array_values($blocked),
            'optionName' => UAMSWP_BLOCK_REMOVER_OPTION,
            'isNetwork' => $is_network,
            'i18n' => [
                'loading' => __('Loading block list…', 'uamswp-block-remover'),
                'error' => __('Could not load blocks from the REST API. Check that the REST API is accessible.', 'uamswp-block-remover'),
                'searchPlaceholder' => __('Filter blocks…', 'uamswp-block-remover'),
                'selectAll' => __('Select all', 'uamswp-block-remover'),
                'deselectAll' => __('Deselect all', 'uamswp-block-remover'),
                'blockedCount' => __('blocked', 'uamswp-block-remover'),
                'noResults' => __('No blocks match your filter.', 'uamswp-block-remover'),
            ],
        ]
    );

    wp_add_inline_style('wp-admin', '
        #uamswp-br-wrap { max-width: 860px; }
        .br-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .br-header h1 { margin: 0; }
        .br-badge { background: #0073aa; color: #fff; border-radius: 10px; padding: 2px 9px; font-size: 12px; font-weight: 600; }
        .br-network-notice { background: #f0f6fc; border-left: 4px solid #0073aa; padding: 10px 14px; margin-bottom: 20px; font-size: 13px; }
        .br-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .br-toolbar input[type="search"] { width: 260px; }
        .br-toolbar .br-bulk { display: flex; gap: 6px; margin-left: auto; }
        #br-block-list { border: 1px solid #ddd; border-radius: 4px; background: #fff; }
        .br-namespace { border-bottom: 1px solid #eee; }
        .br-namespace:last-child { border-bottom: none; }
        .br-namespace-header {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 14px; background: #f6f7f7;
            cursor: pointer; font-weight: 600; font-size: 13px;
            border-bottom: 1px solid #eee; user-select: none;
        }
        .br-namespace-header:hover { background: #eef0f1; }
        .br-namespace-header .br-ns-badge { font-weight: normal; font-size: 11px; color: #666; margin-left: auto; }
        .br-namespace-header .br-chevron { transition: transform .15s; display: inline-block; }
        .br-namespace.is-collapsed .br-chevron { transform: rotate(-90deg); }
        .br-namespace.is-collapsed .br-blocks { display: none; }
        .br-blocks { display: grid; grid-template-columns: 1fr 1fr; padding: 6px 0; }
        .br-block-label {
            display: flex; align-items: flex-start; gap: 8px;
            padding: 7px 14px; font-size: 13px; cursor: pointer;
        }
        .br-block-label:hover { background: #f0f6fc; }
        .br-block-label input { margin-top: 2px; flex-shrink: 0; }
        .br-block-info { display: flex; flex-direction: column; gap: 2px; }
        .br-block-title { font-weight: 500; }
        .br-block-slug { font-family: monospace; font-size: 11px; color: #999; }
        .br-loading { padding: 40px; text-align: center; color: #666; font-size: 14px; }
        .br-error { padding: 20px; color: #d63638; font-size: 13px; }
        .br-actions { display: flex; gap: 8px; align-items: center; margin-top: 20px; }
        .br-hidden { display: none !important; }
        #br-embeds-section { margin-top: 32px; }
        .br-section-heading { display: flex; align-items: center; gap: 10px; font-size: 1.3em; }
        .br-embed-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px 24px; margin-top: 12px; border: 1px solid #ddd; border-radius: 4px; background: #fff; padding: 12px 16px; }
        .br-embed-label { display: flex; align-items: flex-start; gap: 8px; padding: 5px 6px; font-size: 13px; cursor: pointer; border-radius: 3px; }
        .br-embed-label:hover { background: #f0f6fc; }
        .br-embed-label input { margin-top: 2px; flex-shrink: 0; }
    ');
}

// ===========================================================================
// NETWORK ADMIN PATH
// ===========================================================================

function uamswp_block_remover_add_network_menu(): void
{
    add_submenu_page(
        'settings.php',
        __('Block Remover', 'uamswp-block-remover'),
        __('Block Remover', 'uamswp-block-remover'),
        'manage_network_options',
        'uamswp-block-remover',
        'uamswp_block_remover_render_network_page'
    );
}

function uamswp_block_remover_handle_network_save(): void
{
    if (! isset($_POST['uamswp_block_remover_nonce'])) {
        return;
    }

    check_admin_referer('uamswp_block_remover_network_save', 'uamswp_block_remover_nonce');

    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('You do not have permission to do this.', 'uamswp-block-remover'));
    }

    $blocked = isset($_POST[UAMSWP_BLOCK_REMOVER_OPTION]['blocked'])
        ? (array) $_POST[UAMSWP_BLOCK_REMOVER_OPTION]['blocked']
        : [];
    $blocked_embeds = isset($_POST[UAMSWP_BLOCK_REMOVER_OPTION]['blocked_embeds'])
        ? (array) $_POST[UAMSWP_BLOCK_REMOVER_OPTION]['blocked_embeds']
        : [];

    $sanitized = uamswp_block_remover_sanitize_block_list($blocked);
    $sanitized_embeds = uamswp_block_remover_sanitize_embed_list($blocked_embeds);

    update_site_option(UAMSWP_BLOCK_REMOVER_OPTION, [
        'blocked' => $sanitized,
        'blocked_embeds' => $sanitized_embeds,
    ]);

    wp_redirect(add_query_arg(
        'updated',
        '1',
        network_admin_url('settings.php?page=uamswp-block-remover')
    ));
    exit;
}

function uamswp_block_remover_handle_network_reset(): void
{
    if (! isset($_GET['action']) || $_GET['action'] !== 'br_reset') {
        return;
    }
    if (
        ! isset($_GET['_wpnonce']) ||
        ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'br_reset_network')
    ) {
        wp_die(esc_html__('Nonce check failed.', 'uamswp-block-remover'));
    }
    if (! current_user_can('manage_network_options')) {
        wp_die(esc_html__('Permission denied.', 'uamswp-block-remover'));
    }

    update_site_option(UAMSWP_BLOCK_REMOVER_OPTION, ['blocked' => uamswp_block_remover_default_blocks()]);

    wp_redirect(add_query_arg(
        'reset',
        '1',
        network_admin_url('settings.php?page=uamswp-block-remover')
    ));
    exit;
}

function uamswp_block_remover_network_notices(): void
{
    $screen = get_current_screen();
    if (! $screen || $screen->id !== 'settings_page_uamswp-block-remover-network') {
        return;
    }
    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>'
            .esc_html__('Network block list saved.', 'uamswp-block-remover')
            .'</p></div>';
    }
    if (isset($_GET['reset'])) {
        echo '<div class="notice notice-success is-dismissible"><p>'
            .esc_html__('Network block list reset to defaults.', 'uamswp-block-remover')
            .'</p></div>';
    }
}

function uamswp_block_remover_render_network_page(): void
{
    uamswp_block_remover_handle_network_save();
    uamswp_block_remover_handle_network_reset();

    if (! current_user_can('manage_network_options')) {
        return;
    }

    $reset_url = wp_nonce_url(
        add_query_arg('action', 'br_reset', network_admin_url('settings.php?page=uamswp-block-remover')),
        'br_reset_network'
    );
    ?>
    <div class="wrap" id="uamswp-br-wrap">
        <div class="br-header">
            <h1><?php esc_html_e('Block Remover', 'uamswp-block-remover'); ?></h1>
            <span class="br-badge" id="br-badge">—</span>
        </div>
        <div class="br-network-notice">
            <?php esc_html_e('🌐 Network managed — these settings apply to every subsite on this network.', 'uamswp-block-remover'); ?>
        </div>
        <p><?php esc_html_e('Check any block to remove it from the Gutenberg editor across all subsites. The list is pulled live from the block registry.', 'uamswp-block-remover'); ?></p>

        <form method="post" action="<?php echo esc_url(network_admin_url('settings.php?page=uamswp-block-remover')); ?>" id="br-form">
            <?php wp_nonce_field('uamswp_block_remover_network_save', 'uamswp_block_remover_nonce'); ?>
            <?php uamswp_block_remover_render_dynamic_ui($reset_url, __('Save Network Settings', 'uamswp-block-remover')); ?>
        </form>
    </div>
    <?php
}

// ===========================================================================
// SINGLE-SITE PATH
// ===========================================================================

function uamswp_block_remover_add_menu(): void
{
    add_options_page(
        __('Block Remover', 'uamswp-block-remover'),
        __('Block Remover', 'uamswp-block-remover'),
        'manage_options',
        'uamswp-block-remover',
        'uamswp_block_remover_render_page'
    );
}

function uamswp_block_remover_register_settings(): void
{
    register_setting(
        'uamswp_block_remover_group',
        UAMSWP_BLOCK_REMOVER_OPTION,
        [
            'type' => 'array',
            'sanitize_callback' => 'uamswp_block_remover_sanitize_settings_input',
            'default' => [
                'blocked' => uamswp_block_remover_default_blocks(),
                'blocked_embeds' => uamswp_block_remover_default_blocked_embeds(),
            ],
        ]
    );
}

/**
 * Named sanitize callback (required for register_setting to reliably fire).
 * Anonymous closures passed as sanitize_callback can silently fail in some
 * WP versions when the option type is complex.
 */
function uamswp_block_remover_sanitize_settings_input($input): array
{
    $blocked = isset($input['blocked']) && is_array($input['blocked'])
        ? $input['blocked'] : [];
    $blocked_embeds = isset($input['blocked_embeds']) && is_array($input['blocked_embeds'])
        ? $input['blocked_embeds'] : [];

    return [
        'blocked' => uamswp_block_remover_sanitize_block_list($blocked),
        'blocked_embeds' => uamswp_block_remover_sanitize_embed_list($blocked_embeds),
    ];
}

function uamswp_block_remover_handle_reset(): void
{
    if (
        isset($_GET['action']) && $_GET['action'] === 'br_reset' &&
        current_user_can('manage_options') &&
        isset($_GET['_wpnonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'br_reset_site')
    ) {
        update_option(UAMSWP_BLOCK_REMOVER_OPTION, ['blocked' => uamswp_block_remover_default_blocks()]);
        wp_redirect(add_query_arg('reset', '1', admin_url('options-general.php?page=uamswp-block-remover')));
        exit;
    }
}

function uamswp_block_remover_render_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $reset_url = wp_nonce_url(
        add_query_arg('action', 'br_reset', admin_url('options-general.php?page=uamswp-block-remover')),
        'br_reset_site'
    );
    ?>
    <div class="wrap" id="uamswp-br-wrap">
        <div class="br-header">
            <h1><?php esc_html_e('Block Remover', 'uamswp-block-remover'); ?></h1>
            <span class="br-badge" id="br-badge">—</span>
        </div>
        <p><?php esc_html_e('Check any block to remove it from the Gutenberg editor on this site. The list is pulled live from the block registry.', 'uamswp-block-remover'); ?></p>

        <form method="post" action="options.php" id="br-form">
            <?php settings_fields('uamswp_block_remover_group'); ?>
            <?php uamswp_block_remover_render_dynamic_ui($reset_url, __('Save Changes', 'uamswp-block-remover')); ?>
        </form>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Shared dynamic UI shell — JS populates #br-block-list after REST fetch.
// Embeds section is rendered directly from the known PHP list.
// ---------------------------------------------------------------------------

function uamswp_block_remover_render_dynamic_ui(string $reset_url, string $save_label): void
{
    $settings = uamswp_block_remover_get_settings();
    $blocked_embeds = $settings['blocked_embeds'] ?? [];
    $known_embeds = uamswp_block_remover_known_embed_variations();
    ?>
    <div class="br-toolbar">
        <input type="search" id="br-search" placeholder="<?php esc_attr_e('Filter blocks…', 'uamswp-block-remover'); ?>">
        <div class="br-bulk">
            <button type="button" class="button" id="br-select-all"><?php esc_html_e('Select all', 'uamswp-block-remover'); ?></button>
            <button type="button" class="button" id="br-deselect-all"><?php esc_html_e('Deselect all', 'uamswp-block-remover'); ?></button>
        </div>
    </div>

    <div id="br-block-list">
        <div class="br-loading"><?php esc_html_e('Loading block list…', 'uamswp-block-remover'); ?></div>
    </div>

    <?php /* ---- Embed Variations ---- */ ?>
    <div id="br-embeds-section">
        <h2 class="br-section-heading">
            <?php esc_html_e('Embed Variations', 'uamswp-block-remover'); ?>
            <span class="br-badge" id="br-embed-badge">
                <?php echo esc_html(count($blocked_embeds)); ?> blocked
            </span>
        </h2>
        <p class="description">
            <?php esc_html_e('These are the individual embed providers available inside the Embed block. Checked items will be hidden from editors.', 'uamswp-block-remover'); ?>
        </p>
        <div class="br-embed-grid">
            <?php foreach ($known_embeds as $variation_name => $label) {
                $checked = in_array($variation_name, $blocked_embeds, true);
                ?>
                <label class="br-embed-label" data-embed="<?php echo esc_attr($variation_name); ?>">
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr(UAMSWP_BLOCK_REMOVER_OPTION); ?>[blocked_embeds][]"
                        value="<?php echo esc_attr($variation_name); ?>"
                        <?php checked($checked); ?>
                        class="br-embed-cb"
                    >
                    <span class="br-block-info">
                        <span class="br-block-title"><?php echo esc_html($label); ?></span>
                        <span class="br-block-slug"><?php echo esc_html($variation_name); ?></span>
                    </span>
                </label>
            <?php } ?>
        </div>
    </div>

    <div class="br-actions">
        <input type="submit" class="button button-primary" value="<?php echo esc_attr($save_label); ?>">
        <a href="<?php echo esc_url($reset_url); ?>"
           class="button button-secondary"
           onclick="return confirm('<?php esc_attr_e('Reset to defaults?', 'uamswp-block-remover'); ?>')">
            <?php esc_html_e('Reset to Defaults', 'uamswp-block-remover'); ?>
        </a>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Shared sanitizers
// ---------------------------------------------------------------------------

function uamswp_block_remover_sanitize_block_list(array $blocks): array
{
    $out = [];
    foreach ($blocks as $block) {
        $block = sanitize_text_field($block);
        if (preg_match('/^[a-z0-9][a-z0-9\-]*\/[a-z0-9][a-z0-9\-]*$/', $block)) {
            $out[] = $block;
        }
    }

    return array_values(array_unique($out));
}

/**
 * Sanitize embed variation names — alphanumeric + hyphens only, no slash.
 */
function uamswp_block_remover_sanitize_embed_list(array $embeds): array
{
    $known = array_keys(uamswp_block_remover_known_embed_variations());
    $out = [];
    foreach ($embeds as $embed) {
        $embed = sanitize_text_field($embed);
        if (in_array($embed, $known, true)) {
            $out[] = $embed;
        }
    }

    return array_values(array_unique($out));
}
