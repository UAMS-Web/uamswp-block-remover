<?php

/**
 * Test bootstrap.
 *
 * The Unit suite runs with no WordPress: WordPress functions are mocked per
 * test with Brain Monkey. The Integration suite boots a real WordPress through
 * wp-phpunit (tests/Integration/bootstrap.php). Each suite runs in its own
 * process, and this file decides which one it is in.
 *
 * Written for PHP 7.4, the plugin's declared floor: no str_contains(), no
 * mixed type, no named arguments.
 */

declare(strict_types=1);

if (defined('UAMSWP_BLOCK_REMOVER_TESTS_BOOTSTRAPPED')) {
    return;
}

define('UAMSWP_BLOCK_REMOVER_TESTS_BOOTSTRAPPED', true);

require_once dirname(__DIR__).'/vendor/autoload.php';

/**
 * Whether this process runs the Integration suite.
 *
 * True for `--testsuite=Integration`, `--testsuite Integration`, and for a run
 * naming a path under tests/Integration. Anything else is the Unit suite,
 * which is also phpunit.xml's defaultTestSuite.
 *
 * @param  array<mixed>  $argv
 */
function uamswp_block_remover_tests_want_wordpress(array $argv): bool
{
    foreach ($argv as $index => $argument) {
        if (! is_string($argument)) {
            continue;
        }

        if ($argument === '--testsuite=Integration') {
            return true;
        }

        if ($argument === '--testsuite' && ($argv[$index + 1] ?? null) === 'Integration') {
            return true;
        }

        if (strpos(str_replace('\\', '/', $argument), 'tests/Integration') !== false) {
            return true;
        }
    }

    return false;
}

$argv = $_SERVER['argv'] ?? [];

if (uamswp_block_remover_tests_want_wordpress(is_array($argv) ? $argv : [])) {
    require __DIR__.'/Integration/bootstrap.php';

    return;
}

/*
 * The Unit suite.
 *
 * Patchwork first: it can only redefine functions declared in files loaded
 * after it, so loading it before the plugin is what lets a test mock one of the
 * plugin's own functions, not only WordPress'.
 */
require_once dirname(__DIR__).'/vendor/antecedent/patchwork/Patchwork.php';

/*
 * Plugin files open with an ABSPATH guard. Define it so they load, pointing at
 * a directory that is not a WordPress install: code that requires a core file
 * through ABSPATH fails loudly here, which is the sign it belongs in the
 * Integration suite.
 */
if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir().'/uamswp-block-remover-tests-no-wordpress/');
}

/*
 * The editor context class the block filter type-hints. Core declares it final
 * with a constructor taking the editor settings; the filter never reads it, so
 * an empty stand-in is enough to call the function.
 */
if (! class_exists('WP_Block_Editor_Context', false)) {
    require __DIR__.'/Support/WP_Block_Editor_Context.php';
}

require_once __DIR__.'/Support/UnitTestCase.php';

/*
 * The plugin defines its functions and registers its hooks in the same files
 * (uamswp-block-remover.php and admin/settings-page.php, which the main file
 * requires), and defines its path constants from plugin_dir_path() and
 * plugin_dir_url() at file scope. So the main file is loaded the way the theme
 * template loads functions.php: inside one Brain Monkey session, with what it
 * calls at load time stubbed. The functions it defines outlive the session;
 * the hooks it registered are discarded by tearDown(), so every test starts
 * clean, and hook registration is asserted in the Integration suite.
 */
Brain\Monkey\setUp();

Brain\Monkey\Functions\when('plugin_dir_path')->justReturn(dirname(__DIR__).'/');
Brain\Monkey\Functions\when('plugin_dir_url')->justReturn('https://example.org/wp-content/plugins/uamswp-block-remover/');
Brain\Monkey\Functions\when('register_activation_hook')->justReturn(null);
Brain\Monkey\Functions\when('register_uninstall_hook')->justReturn(null);

require_once dirname(__DIR__).'/uamswp-block-remover.php';

Brain\Monkey\tearDown();

// cspell:ignore ABSPATH
