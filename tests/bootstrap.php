<?php

/**
 * Unit bootstrap.
 *
 * The Unit suite runs with no WordPress: WordPress functions are mocked per
 * test with Brain Monkey. The Integration suite does not come through here; it
 * has its own Composer setup and bootstrap under tests/Integration.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

require_once __DIR__.'/../vendor/autoload.php';

/*
 * Patchwork first: it can only redefine functions declared in files loaded
 * after it, so loading it before the plugin is what lets a test mock one of the
 * plugin's own functions, not only WordPress'.
 */
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

/*
 * Plugin/theme files open with an ABSPATH guard. Define it so they load,
 * pointing at a directory that is not a WordPress install.
 */
if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir().'/uamswp_block_remover-tests-no-wordpress/');
}

/*
 * Stand-ins for the core classes the plugin names, and the helpers the tests
 * share. The plugin type-hints WP_Block_Editor_Context and reads
 * WP_Block_Type_Registry, neither of which exists without WordPress.
 */
require_once __DIR__.'/Support/WP_Block_Editor_Context.php';
require_once __DIR__.'/Support/WP_Block_Type_Registry.php';
require_once __DIR__.'/Support/UamswpTestHalt.php';
require_once __DIR__.'/Support/functions.php';

/*
 * What the plugin registers at file scope, recorded while it loads. The main
 * plugin file and the admin page call add_action(), add_filter() and the
 * register_*_hook() functions when WordPress includes them, before any test
 * runs, so the calls are captured here and the tests read them back through
 * uamswp_test_registrations().
 */
$GLOBALS['uamswp_test_registrations'] = [];

/*
 * Load definitions+registrations inside one Brain Monkey session. Do not
 * define permanent WordPress function stubs in this file: Patchwork treats
 * this bootstrap as too early and Brain Monkey cannot redefine them in tests
 * (DefinedTooEarly). See the tests skill and the wordpress-theme template.
 */
setUp();

foreach (['add_action', 'add_filter', 'register_activation_hook', 'register_deactivation_hook', 'register_uninstall_hook'] as $registrar) {
    when($registrar)->alias(static function (...$arguments) use ($registrar): void {
        $GLOBALS['uamswp_test_registrations'][] = [$registrar, $arguments];
    });
}

when('plugin_dir_path')->justReturn(__DIR__.'/../');
when('plugin_dir_url')->justReturn('https://example.test/wp-content/plugins/'.basename(dirname(__DIR__)).'/');
when('plugin_basename')->alias(static fn ($file): string => basename(dirname($file)).'/'.basename($file));
when('is_multisite')->justReturn(false);

if (is_readable(__DIR__.'/../uamswp-block-remover.php')) {
    require_once __DIR__.'/../uamswp-block-remover.php';
}

tearDown();

// cspell:ignore ABSPATH
