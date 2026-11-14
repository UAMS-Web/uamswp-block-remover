<?php

/**
 * The settings page: hook routing, assets, menus, form handling, notices,
 * rendering and the sanitizers.
 *
 * wp_die() and wp_redirect() are stubbed to throw UamswpTestHalt, so a handler
 * that would stop the request stops the test at the same line, and the
 * `exit` after a redirect is never reached.
 *
 * @see admin/settings-page.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\stubEscapeFunctions;
use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\Functions\when;

/**
 * Stub WordPress' translation and escaping helpers as pass-throughs.
 */
function uamswp_test_stub_i18n(): void
{
    stubTranslationFunctions();
    stubEscapeFunctions();
    when('esc_url_raw')->returnArg();
    when('checked')->alias(static function ($checked, $current = true, bool $display = true): string {
        $result = (string) $checked === (string) $current ? " checked='checked'" : '';
        if ($display) {
            echo $result;
        }

        return $result;
    });
}

/**
 * Make wp_die() and wp_redirect() halt the test, reporting what they received.
 */
function uamswp_test_halting(): void
{
    when('wp_die')->alias(static function ($message = ''): void {
        throw new UamswpTestHalt('die:'.$message);
    });
    when('wp_redirect')->alias(static function ($location): void {
        throw new UamswpTestHalt('redirect:'.$location);
    });
    when('add_query_arg')->alias(static fn (string $key, string $value, string $url): string => $url.'&'.$key.'='.$value);
    when('network_admin_url')->alias(static fn (string $path): string => 'https://net.test/wp-admin/network/'.$path);
    when('admin_url')->alias(static fn (string $path): string => 'https://site.test/wp-admin/'.$path);
}

/**
 * The message a callback halts with.
 */
function uamswp_test_halt_message(callable $callback): string
{
    try {
        $callback();
    } catch (UamswpTestHalt $uamswpTestHalt) {
        return $uamswpTestHalt->getMessage();
    }

    return 'did not halt';
}

beforeEach(function (): void {
    $_GET = [];
    $_POST = [];
    uamswp_test_stub_i18n();
    uamswp_test_halting();
});

afterEach(function (): void {
    $_GET = [];
    $_POST = [];
});

// ---------------------------------------------------------------------------
// Hook routing
// ---------------------------------------------------------------------------

/**
 * Record what register_hooks() adds, as [hook, callback, priority-or-null].
 *
 * @return list<array{0: string, 1: string, 2: int|null}>
 */
function uamswp_test_routed(): array
{
    $added = [];
    when('add_action')->alias(static function (string $hook, string $callback, ?int $priority = null) use (&$added): void {
        $added[] = [$hook, $callback, $priority];
    });

    uamswp_block_remover_register_hooks();

    return $added;
}

test('a site-active plugin routes the site menu, settings, reset and assets', function (): void {
    uamswp_test_single_site();

    expect(uamswp_test_routed())->toBe([
        ['admin_menu', 'uamswp_block_remover_add_menu', null],
        ['admin_init', 'uamswp_block_remover_register_settings', null],
        ['admin_init', 'uamswp_block_remover_handle_reset', 5],
        ['admin_enqueue_scripts', 'uamswp_block_remover_admin_assets', null],
    ]);
});

test('a network-active plugin routes the network menu, notices and assets', function (): void {
    uamswp_test_network_active();

    expect(uamswp_test_routed())->toBe([
        ['network_admin_menu', 'uamswp_block_remover_add_network_menu', null],
        ['network_admin_notices', 'uamswp_block_remover_network_notices', null],
        ['admin_enqueue_scripts', 'uamswp_block_remover_admin_assets', null],
    ]);
});

// ---------------------------------------------------------------------------
// Assets
// ---------------------------------------------------------------------------

test('assets load on neither a foreign screen nor a prefix of ours', function (): void {
    Functions\expect('wp_enqueue_script')->never();
    Functions\expect('wp_add_inline_style')->never();

    uamswp_block_remover_admin_assets('plugins.php');
    uamswp_block_remover_admin_assets('settings_page_uamswp-block-remover-other');
});

test('assets on the site settings screen use the site REST URL', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(['blocked' => ['core/quote'], 'blocked_embeds' => []]);
    when('wp_create_nonce')->alias(static fn (string $action): string => 'nonce-'.$action);

    Functions\expect('get_main_site_id')->never();
    Functions\expect('switch_to_blog')->never();
    Functions\expect('rest_url')->once()->with('wp/v2/block-types')->andReturn('https://site.test/wp-json/wp/v2/block-types');
    Functions\expect('wp_enqueue_script')->once()->with(
        'uamswp-block-remover-admin',
        UAMSWP_BLOCK_REMOVER_URL.'assets/js/admin-settings.js',
        [],
        UAMSWP_BLOCK_REMOVER_VERSION,
        true
    );
    $localized = null;
    when('wp_localize_script')->alias(static function (string $handle, string $name, array $data) use (&$localized): void {
        $localized = [$handle, $name, $data];
    });
    Functions\expect('wp_add_inline_style')->once()->with('wp-admin', Mockery::type('string'));

    uamswp_block_remover_admin_assets('settings_page_uamswp-block-remover');

    expect($localized[0])->toBe('uamswp-block-remover-admin')
        ->and($localized[1])->toBe('uamswpBRAdmin')
        ->and($localized[2]['restUrl'])->toBe('https://site.test/wp-json/wp/v2/block-types')
        ->and($localized[2]['restNonce'])->toBe('nonce-wp_rest')
        ->and($localized[2]['savedBlocked'])->toBe(['core/quote'])
        ->and($localized[2]['optionName'])->toBe(UAMSWP_TEST_OPTION)
        ->and($localized[2]['isNetwork'])->toBeFalse()
        ->and(array_keys($localized[2]['i18n']))->toBe([
            'loading', 'error', 'searchPlaceholder', 'selectAll', 'deselectAll', 'blockedCount', 'noResults',
        ]);
});

test('assets on the network screen read the REST URL from the main site', function (): void {
    uamswp_test_network_active(['blocked' => ['core/quote']]);
    when('wp_create_nonce')->justReturn('n');
    when('get_main_site_id')->justReturn(4);

    $log = [];
    when('switch_to_blog')->alias(static function (int $id) use (&$log): void {
        $log[] = "switch:{$id}";
    });
    when('rest_url')->alias(static function () use (&$log): string {
        $log[] = 'rest_url';

        return 'https://main.test/wp-json/wp/v2/block-types';
    });
    when('restore_current_blog')->alias(static function () use (&$log): void {
        $log[] = 'restore';
    });
    when('wp_enqueue_script')->justReturn(true);
    when('wp_add_inline_style')->justReturn(true);

    $localized = null;
    when('wp_localize_script')->alias(static function (string $handle, string $name, array $data) use (&$localized): void {
        $localized = $data;
    });

    uamswp_block_remover_admin_assets('settings_page_uamswp-block-remover-network');

    expect($log)->toBe(['rest_url', 'switch:4', 'rest_url', 'restore'])
        ->and($localized['isNetwork'])->toBeTrue()
        ->and($localized['restUrl'])->toBe('https://main.test/wp-json/wp/v2/block-types');
});

test('saved blocks are passed to the script as a re-indexed list', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(['blocked' => [5 => 'core/quote', 9 => 'core/list']]);
    when('wp_create_nonce')->justReturn('n');
    when('rest_url')->justReturn('u');
    when('wp_enqueue_script')->justReturn(true);
    when('wp_add_inline_style')->justReturn(true);

    $saved = null;
    when('wp_localize_script')->alias(static function (string $h, string $n, array $data) use (&$saved): void {
        $saved = $data['savedBlocked'];
    });

    uamswp_block_remover_admin_assets('settings_page_uamswp-block-remover');

    expect($saved)->toBe(['core/quote', 'core/list']);
});

// ---------------------------------------------------------------------------
// Menus and settings registration
// ---------------------------------------------------------------------------

test('the network menu is a Settings submenu for network options managers', function (): void {
    Functions\expect('add_submenu_page')->once()->with(
        'settings.php',
        'Block Remover',
        'Block Remover',
        'manage_network_options',
        'uamswp-block-remover',
        'uamswp_block_remover_render_network_page'
    );

    uamswp_block_remover_add_network_menu();
});

test('the site menu is an options page for options managers', function (): void {
    Functions\expect('add_options_page')->once()->with(
        'Block Remover',
        'Block Remover',
        'manage_options',
        'uamswp-block-remover',
        'uamswp_block_remover_render_page'
    );

    uamswp_block_remover_add_menu();
});

test('the setting is registered with a named sanitize callback and the defaults', function (): void {
    Functions\expect('register_setting')->once()->with(
        'uamswp_block_remover_group',
        UAMSWP_TEST_OPTION,
        [
            'type' => 'array',
            'sanitize_callback' => 'uamswp_block_remover_sanitize_settings_input',
            'default' => [
                'blocked' => uamswp_block_remover_default_blocks(),
                'blocked_embeds' => [],
            ],
        ]
    );

    uamswp_block_remover_register_settings();
});

// ---------------------------------------------------------------------------
// Network save
// ---------------------------------------------------------------------------

test('a network save without the nonce field does nothing', function (): void {
    Functions\expect('check_admin_referer')->never();
    Functions\expect('update_site_option')->never();

    uamswp_block_remover_handle_network_save();
});

test('a network save checks the nonce before anything else', function (): void {
    $_POST['uamswp_block_remover_nonce'] = 'x';
    Functions\expect('check_admin_referer')->once()->with('uamswp_block_remover_network_save', 'uamswp_block_remover_nonce');
    when('current_user_can')->justReturn(false);
    Functions\expect('update_site_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_network_save'))
        ->toBe('die:You do not have permission to do this.');
});

test('a network save sanitizes both lists, stores them and redirects', function (): void {
    $_POST['uamswp_block_remover_nonce'] = 'x';
    $_POST[UAMSWP_TEST_OPTION] = [
        'blocked' => ['core/quote', 'not a block', 'core/quote'],
        'blocked_embeds' => ['youtube', 'unknown'],
    ];
    when('check_admin_referer')->justReturn(1);
    when('current_user_can')->alias(static fn (string $capability): bool => $capability === 'manage_network_options');
    when('sanitize_text_field')->returnArg();
    Functions\expect('update_site_option')->once()->with(UAMSWP_TEST_OPTION, [
        'blocked' => ['core/quote'],
        'blocked_embeds' => ['youtube'],
    ]);

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_network_save'))
        ->toBe('redirect:https://net.test/wp-admin/network/settings.php?page=uamswp-block-remover&updated=1');
});

test('a network save with no lists stores empty lists', function (): void {
    $_POST['uamswp_block_remover_nonce'] = 'x';
    when('check_admin_referer')->justReturn(1);
    when('current_user_can')->justReturn(true);
    Functions\expect('update_site_option')->once()->with(UAMSWP_TEST_OPTION, ['blocked' => [], 'blocked_embeds' => []]);

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_network_save'))->toStartWith('redirect:');
});

test('a network save casts a scalar list to an array', function (): void {
    $_POST['uamswp_block_remover_nonce'] = 'x';
    $_POST[UAMSWP_TEST_OPTION] = ['blocked' => 'core/quote', 'blocked_embeds' => 'vimeo'];
    when('check_admin_referer')->justReturn(1);
    when('current_user_can')->justReturn(true);
    when('sanitize_text_field')->returnArg();
    Functions\expect('update_site_option')->once()->with(UAMSWP_TEST_OPTION, [
        'blocked' => ['core/quote'],
        'blocked_embeds' => ['vimeo'],
    ]);

    uamswp_test_halt_message('uamswp_block_remover_handle_network_save');
});

// ---------------------------------------------------------------------------
// Network reset
// ---------------------------------------------------------------------------

test('a network reset needs the reset action', function (): void {
    Functions\expect('wp_verify_nonce')->never();
    Functions\expect('update_site_option')->never();

    uamswp_block_remover_handle_network_reset();
    $_GET['action'] = 'something-else';
    uamswp_block_remover_handle_network_reset();
});

test('a network reset without a nonce dies', function (): void {
    $_GET['action'] = 'br_reset';
    Functions\expect('update_site_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_network_reset'))->toBe('die:Nonce check failed.');
});

test('a network reset with a bad nonce dies', function (): void {
    $_GET = ['action' => 'br_reset', '_wpnonce' => 'bad'];
    when('wp_unslash')->returnArg();
    when('sanitize_text_field')->returnArg();
    Functions\expect('wp_verify_nonce')->once()->with('bad', 'br_reset_network')->andReturn(false);
    Functions\expect('update_site_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_network_reset'))->toBe('die:Nonce check failed.');
});

test('a network reset without permission dies', function (): void {
    $_GET = ['action' => 'br_reset', '_wpnonce' => 'good'];
    when('wp_unslash')->returnArg();
    when('sanitize_text_field')->returnArg();
    when('wp_verify_nonce')->justReturn(1);
    when('current_user_can')->justReturn(false);

    Functions\expect('update_site_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_network_reset'))->toBe('die:Permission denied.');
});

test('a network reset restores the default blocks and redirects', function (): void {
    $_GET = ['action' => 'br_reset', '_wpnonce' => 'good'];
    when('wp_unslash')->returnArg();
    when('sanitize_text_field')->returnArg();
    when('wp_verify_nonce')->justReturn(1);
    Functions\expect('current_user_can')->once()->with('manage_network_options')->andReturn(true);
    Functions\expect('update_site_option')->once()->with(UAMSWP_TEST_OPTION, ['blocked' => uamswp_block_remover_default_blocks()]);

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_network_reset'))
        ->toBe('redirect:https://net.test/wp-admin/network/settings.php?page=uamswp-block-remover&reset=1');
});

// ---------------------------------------------------------------------------
// Network notices
// ---------------------------------------------------------------------------

test('network notices print nothing without a screen', function (): void {
    when('get_current_screen')->justReturn();
    $_GET['updated'] = '1';

    expect(uamswp_test_capture('uamswp_block_remover_network_notices'))->toBe('');
});

test('network notices print nothing on another screen', function (): void {
    when('get_current_screen')->justReturn((object) ['id' => 'dashboard']);
    $_GET['updated'] = '1';

    expect(uamswp_test_capture('uamswp_block_remover_network_notices'))->toBe('');
});

test('network notices print nothing when neither flag is set', function (): void {
    when('get_current_screen')->justReturn((object) ['id' => 'settings_page_uamswp-block-remover-network']);

    expect(uamswp_test_capture('uamswp_block_remover_network_notices'))->toBe('');
});

test('network notices announce a save', function (): void {
    when('get_current_screen')->justReturn((object) ['id' => 'settings_page_uamswp-block-remover-network']);
    $_GET['updated'] = '1';

    expect(uamswp_test_capture('uamswp_block_remover_network_notices'))
        ->toBe('<div class="notice notice-success is-dismissible"><p>Network block list saved.</p></div>');
});

test('network notices announce a reset', function (): void {
    when('get_current_screen')->justReturn((object) ['id' => 'settings_page_uamswp-block-remover-network']);
    $_GET['reset'] = '1';

    expect(uamswp_test_capture('uamswp_block_remover_network_notices'))
        ->toBe('<div class="notice notice-success is-dismissible"><p>Network block list reset to defaults.</p></div>');
});

test('network notices announce both when both flags are set', function (): void {
    when('get_current_screen')->justReturn((object) ['id' => 'settings_page_uamswp-block-remover-network']);
    $_GET = ['updated' => '1', 'reset' => '1'];

    $output = uamswp_test_capture('uamswp_block_remover_network_notices');

    expect($output)->toContain('Network block list saved.')->toContain('Network block list reset to defaults.');
});

// ---------------------------------------------------------------------------
// Site reset
// ---------------------------------------------------------------------------

/**
 * Arrange a site reset request that passes every check.
 */
function uamswp_test_site_reset_request(bool $stubNonce = true): void
{
    $_GET = ['action' => 'br_reset', '_wpnonce' => 'good'];
    when('wp_unslash')->returnArg();
    when('sanitize_text_field')->returnArg();
    when('current_user_can')->justReturn(true);
    if ($stubNonce) {
        when('wp_verify_nonce')->justReturn(1);
    }
}

test('a site reset restores the default blocks and redirects', function (): void {
    uamswp_test_site_reset_request();
    Functions\expect('update_option')->once()->with(UAMSWP_TEST_OPTION, ['blocked' => uamswp_block_remover_default_blocks()]);

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_reset'))
        ->toBe('redirect:https://site.test/wp-admin/options-general.php?page=uamswp-block-remover&reset=1');
});

test('a site reset checks the nonce against the site action', function (): void {
    uamswp_test_site_reset_request(false);
    Functions\expect('wp_verify_nonce')->once()->with('good', 'br_reset_site')->andReturn(1);
    when('update_option')->justReturn(true);

    uamswp_test_halt_message('uamswp_block_remover_handle_reset');
});

test('a site reset without the action does nothing', function (): void {
    uamswp_test_site_reset_request();
    unset($_GET['action']);
    Functions\expect('update_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_reset'))->toBe('did not halt');
});

test('a site reset with another action does nothing', function (): void {
    uamswp_test_site_reset_request();
    $_GET['action'] = 'other';
    Functions\expect('update_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_reset'))->toBe('did not halt');
});

test('a site reset without permission does nothing', function (): void {
    uamswp_test_site_reset_request();
    when('current_user_can')->justReturn(false);
    Functions\expect('update_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_reset'))->toBe('did not halt');
});

test('a site reset without a nonce does nothing', function (): void {
    uamswp_test_site_reset_request();
    unset($_GET['_wpnonce']);
    Functions\expect('update_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_reset'))->toBe('did not halt');
});

test('a site reset with a failed nonce does nothing', function (): void {
    uamswp_test_site_reset_request();
    when('wp_verify_nonce')->justReturn(false);
    Functions\expect('update_option')->never();

    expect(uamswp_test_halt_message('uamswp_block_remover_handle_reset'))->toBe('did not halt');
});

// ---------------------------------------------------------------------------
// Sanitizers
// ---------------------------------------------------------------------------

test('block names are kept only when they are namespace/name slugs', function (): void {
    when('sanitize_text_field')->alias(static fn (string $value): string => trim($value));

    $result = uamswp_block_remover_sanitize_block_list([
        'core/quote',
        ' my-plugin/block-2 ',
        'core/quote',
        'Core/Quote',
        'core',
        'core/',
        '/quote',
        '-core/quote',
        'core/-quote',
        'core/quote/extra',
        'core/qu_ote',
        "core/quote\n",
    ]);

    expect($result)->toBe(['core/quote', 'my-plugin/block-2']);
});

test('an empty block list sanitizes to an empty list', function (): void {
    expect(uamswp_block_remover_sanitize_block_list([]))->toBe([]);
});

test('embed names are kept only when they are known variations', function (): void {
    when('sanitize_text_field')->returnArg();

    $result = uamswp_block_remover_sanitize_embed_list(['youtube', 'nope', 'vimeo', 'youtube', 'core/quote']);

    expect($result)->toBe(['youtube', 'vimeo']);
});

test('embed names match exactly, not loosely', function (): void {
    when('sanitize_text_field')->returnArg();

    expect(uamswp_block_remover_sanitize_embed_list(['0', 'YouTube', 'youtube ']))->toBe([]);
});

test('the settings input is sanitized key by key', function (): void {
    when('sanitize_text_field')->returnArg();

    $result = uamswp_block_remover_sanitize_settings_input([
        'blocked' => ['core/quote', 'bad'],
        'blocked_embeds' => ['youtube', 'bad'],
    ]);

    expect($result)->toBe(['blocked' => ['core/quote'], 'blocked_embeds' => ['youtube']]);
});

test('settings input that is not an array of lists sanitizes to empty lists', function (): void {
    expect(uamswp_block_remover_sanitize_settings_input(null))->toBe(['blocked' => [], 'blocked_embeds' => []])
        ->and(uamswp_block_remover_sanitize_settings_input(['blocked' => 'core/quote', 'blocked_embeds' => 'youtube']))
        ->toBe(['blocked' => [], 'blocked_embeds' => []]);
});

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

test('the dynamic UI lists every known embed, checking the blocked ones', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(['blocked_embeds' => ['youtube']]);

    $output = uamswp_test_capture(static fn () => uamswp_block_remover_render_dynamic_ui('https://reset.test/', 'Save'));

    expect(substr_count($output, 'class="br-embed-cb"'))->toBe(count(uamswp_block_remover_known_embed_variations()))
        ->and($output)->toMatch('/value="youtube"\s+checked=\'checked\'\s+class="br-embed-cb"/')
        ->and(substr_count($output, "checked='checked'"))->toBe(1)
        ->and($output)->toContain('1 blocked')
        ->and($output)->toContain('name="'.UAMSWP_TEST_OPTION.'[blocked_embeds][]"')
        ->and($output)->toContain('value="Save"')
        ->and($output)->toContain('href="https://reset.test/"')
        ->and($output)->toContain('Reset to Defaults');
});

test('the dynamic UI with no blocked embeds checks none and counts zero', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(false);

    $output = uamswp_test_capture(static fn () => uamswp_block_remover_render_dynamic_ui('r', 's'));

    expect($output)->not->toContain("checked='checked'")->toContain('0 blocked');
});

test('the dynamic UI treats a missing blocked_embeds key as empty', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(['blocked' => []]);

    $output = uamswp_test_capture(static fn () => uamswp_block_remover_render_dynamic_ui('r', 's'));

    expect($output)->toContain('0 blocked');
});

test('the site page renders nothing without permission', function (): void {
    when('current_user_can')->justReturn(false);

    expect(uamswp_test_capture('uamswp_block_remover_render_page'))->toBe('');
});

test('the site page renders the form posting to options.php with the reset link', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(false);
    Functions\expect('current_user_can')->once()->with('manage_options')->andReturn(true);
    when('wp_nonce_url')->alias(static fn (string $url, string $action): string => $url.'|'.$action);
    Functions\expect('settings_fields')->once()->with('uamswp_block_remover_group');

    $output = uamswp_test_capture('uamswp_block_remover_render_page');

    expect($output)->toContain('action="options.php"')
        ->and($output)->toContain('on this site')
        ->and($output)->toContain('value="Save Changes"')
        ->and($output)->toContain('https://site.test/wp-admin/options-general.php?page=uamswp-block-remover&action=br_reset|br_reset_site');
});

test('the network page runs both handlers, then renders nothing without permission', function (): void {
    when('current_user_can')->justReturn(false);

    expect(uamswp_test_capture('uamswp_block_remover_render_network_page'))->toBe('');
});

test('the network page runs the save handler first', function (): void {
    $_POST['uamswp_block_remover_nonce'] = 'x';
    when('check_admin_referer')->justReturn(1);
    when('current_user_can')->justReturn(false);

    expect(uamswp_test_halt_message('uamswp_block_remover_render_network_page'))
        ->toBe('die:You do not have permission to do this.');
});

test('the network page runs the reset handler', function (): void {
    $_GET['action'] = 'br_reset';

    expect(uamswp_test_halt_message('uamswp_block_remover_render_network_page'))->toBe('die:Nonce check failed.');
});

test('the network page renders the form posting to the network settings URL', function (): void {
    uamswp_test_network_active(false);
    when('current_user_can')->justReturn(true);
    when('wp_nonce_url')->alias(static fn (string $url, string $action): string => $url.'|'.$action);
    Functions\expect('wp_nonce_field')->once()->with('uamswp_block_remover_network_save', 'uamswp_block_remover_nonce');

    $output = uamswp_test_capture('uamswp_block_remover_render_network_page');

    expect($output)->toContain('action="https://net.test/wp-admin/network/settings.php?page=uamswp-block-remover"')
        ->and($output)->toContain('every subsite on this network')
        ->and($output)->toContain('value="Save Network Settings"')
        ->and($output)->toContain('br_reset_network');
});

// ---------------------------------------------------------------------------
// Rendered copy and re-indexed sanitizer output
// ---------------------------------------------------------------------------

test('block and embed lists are re-indexed after duplicates are dropped', function (): void {
    when('sanitize_text_field')->returnArg();

    expect(uamswp_block_remover_sanitize_block_list(['core/quote', 'core/quote', 'core/list']))
        ->toBe(['core/quote', 'core/list'])
        ->and(uamswp_block_remover_sanitize_embed_list(['youtube', 'youtube', 'vimeo']))
        ->toBe(['youtube', 'vimeo']);
});

test('the dynamic UI renders its own copy', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(false);

    $output = uamswp_test_capture(static fn () => uamswp_block_remover_render_dynamic_ui('r', 's'));

    expect($output)->toContain('placeholder="Filter blocks…"')
        ->toContain('>Select all</button>')
        ->toContain('>Deselect all</button>')
        ->toContain('Loading block list…')
        ->toContain('Embed Variations')
        ->toContain('Checked items will be hidden from editors.')
        ->toContain("confirm('Reset to defaults?')");
});

test('the site page renders its heading and description', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(false);
    when('current_user_can')->justReturn(true);
    when('wp_nonce_url')->returnArg();
    when('settings_fields')->justReturn();

    $output = uamswp_test_capture('uamswp_block_remover_render_page');

    expect($output)->toContain('<h1>Block Remover</h1>')
        ->toContain('The list is pulled live from the block registry.');
});

test('the network page renders its heading and description', function (): void {
    uamswp_test_network_active(false);
    when('current_user_can')->justReturn(true);
    when('wp_nonce_url')->returnArg();
    when('wp_nonce_field')->justReturn('');

    $output = uamswp_test_capture('uamswp_block_remover_render_network_page');

    expect($output)->toContain('<h1>Block Remover</h1>')
        ->toContain('The list is pulled live from the block registry.');
});
