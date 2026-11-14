<?php

/**
 * Unit bootstrap.
 *
 * The Unit suite runs with no WordPress: WordPress functions are mocked per
 * test with Brain Monkey. The Integration suite does not come through here; it
 * has its own Composer setup and bootstrap under tests/Integration.
 */

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

/*
 * Patchwork first: it can only redefine functions declared in files loaded
 * after it, so loading it before the plugin is what lets a test mock one of the
 * plugin's own functions, not only WordPress'.
 */
require_once dirname(__DIR__).'/vendor/antecedent/patchwork/Patchwork.php';

/*
 * Plugin/theme files open with an ABSPATH guard. Define it so they load,
 * pointing at a directory that is not a WordPress install.
 */
if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir().'/uamswp_block_remover-tests-no-wordpress/');
}

/*
 * Load definitions+registrations inside one Brain Monkey session. Do not
 * define permanent WordPress function stubs in this file: Patchwork treats
 * this bootstrap as too early and Brain Monkey cannot redefine them in tests
 * (DefinedTooEarly). See the tests skill and the wordpress-theme template.
 */
Brain\Monkey\setUp();

Brain\Monkey\Functions\when('plugin_dir_path')->justReturn(dirname(__DIR__).'/');
Brain\Monkey\Functions\when('plugin_dir_url')->justReturn('https://example.test/wp-content/plugins/'.basename(dirname(__DIR__)).'/');
Brain\Monkey\Functions\when('plugin_basename')->alias(static function ($file) {
    return basename(dirname($file)).'/'.basename($file);
});
Brain\Monkey\Functions\when('is_multisite')->justReturn(false);
Brain\Monkey\Functions\when('get_site_option')->alias(static function ($key, $default = false) {
    return $default;
});
Brain\Monkey\Functions\when('get_option')->alias(static function ($key, $default = false) {
    return $default;
});
Brain\Monkey\Functions\when('update_option')->justReturn(null);
Brain\Monkey\Functions\when('register_activation_hook')->justReturn(null);
Brain\Monkey\Functions\when('register_deactivation_hook')->justReturn(null);
Brain\Monkey\Functions\when('register_uninstall_hook')->justReturn(null);
Brain\Monkey\Functions\when('load_plugin_textdomain')->justReturn(null);
Brain\Monkey\Functions\when('is_admin')->justReturn(false);
Brain\Monkey\Functions\when('wp_enqueue_script')->justReturn(null);
Brain\Monkey\Functions\when('wp_enqueue_style')->justReturn(null);
Brain\Monkey\Functions\when('wp_register_script')->justReturn(null);
Brain\Monkey\Functions\when('wp_register_style')->justReturn(null);
Brain\Monkey\Functions\when('wp_localize_script')->justReturn(null);
Brain\Monkey\Functions\when('esc_html')->returnArg();
Brain\Monkey\Functions\when('esc_attr')->returnArg();
Brain\Monkey\Functions\when('esc_url')->returnArg();
Brain\Monkey\Functions\when('__')->returnArg();
Brain\Monkey\Functions\when('_e')->justReturn(null);
Brain\Monkey\Functions\when('esc_html__')->returnArg();
Brain\Monkey\Functions\when('esc_attr__')->returnArg();

if (is_readable(dirname(__DIR__).'/uamswp-block-remover.php')) {
    require_once dirname(__DIR__).'/uamswp-block-remover.php';
}

Brain\Monkey\tearDown();

// cspell:ignore ABSPATH
