<?php

/**
 * Loads the plugin for the Unit suite, from inside a test run.
 *
 * The plugin file registers hooks, defines constants and checks ABSPATH at file
 * scope. Loaded from tests/bootstrap.php those lines run before the coverage
 * driver starts, so a coverage or mutation run reports every one of them
 * uncovered. Pest calls this from a beforeAll hook instead, where the driver is
 * already running, and the load-time lines are covered like any other.
 *
 * The first call loads the file inside one Brain Monkey session and records every
 * hook registration it makes; later calls do nothing.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

function uamswp_test_load_plugin(): void
{
    if (isset($GLOBALS['uamswp_test_load_state'])) {
        return;
    }

    $GLOBALS['uamswp_test_load_state'] = 'loading';

    /*
     * A plugin file that exits while it loads ends the PHP process, and a process
     * that ends with no test run reads as a pass to anything that only checks the
     * exit status: an inverted ABSPATH guard would do exactly that. Fail the
     * process instead, so the test run notices.
     */
    register_shutdown_function(static function (): void {
        if ($GLOBALS['uamswp_test_load_state'] === 'loading') {
            fwrite(STDERR, PHP_EOL.'The plugin file stopped the process while it loaded.'.PHP_EOL);

            exit(1);
        }
    });

    $GLOBALS['uamswp_test_registrations'] = [];

    /*
     * Load definitions and registrations inside one Brain Monkey session. Do not
     * define permanent WordPress function stubs outside it: Patchwork treats them
     * as defined too early and Brain Monkey cannot redefine them in tests
     * (DefinedTooEarly).
     */
    setUp();

    foreach (['add_action', 'add_filter', 'register_activation_hook', 'register_deactivation_hook', 'register_uninstall_hook'] as $registrar) {
        when($registrar)->alias(static function (...$arguments) use ($registrar): void {
            $GLOBALS['uamswp_test_registrations'][] = [$registrar, $arguments];
        });
    }

    when('plugin_dir_path')->justReturn(dirname(__DIR__, 2).'/');
    when('plugin_dir_url')->justReturn('https://example.test/wp-content/plugins/'.basename(dirname(__DIR__, 2)).'/');
    when('plugin_basename')->alias(static fn ($file): string => basename(dirname($file)).'/'.basename($file));
    when('is_multisite')->justReturn(false);

    require_once __DIR__.'/../../uamswp-block-remover.php';

    tearDown();

    $GLOBALS['uamswp_test_load_state'] = 'loaded';
}
