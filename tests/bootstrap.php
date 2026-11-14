<?php

/**
 * Unit bootstrap.
 *
 * The Unit suite runs with no WordPress: WordPress functions are mocked per
 * test with Brain Monkey. The Integration suite does not come through here; it
 * has its own Composer setup and bootstrap under tests/Integration.
 */

declare(strict_types=1);

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
require_once __DIR__.'/Support/load-plugin.php';

/*
 * The plugin file is not loaded here. It registers hooks and defines constants at
 * file scope, and anything loaded from a bootstrap runs before the coverage
 * driver starts, so those lines would count as uncovered. tests/Pest.php loads it
 * from a beforeAll hook through uamswp_test_load_plugin(), inside the test run.
 */

// cspell:ignore ABSPATH
