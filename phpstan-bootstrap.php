<?php

/**
 * PHPStan bootstrap: plain stand-ins for the constants the plugin defines from
 * function calls (`plugin_dir_path()`, `plugin_dir_url()`), which PHPStan does
 * not evaluate when discovering constants. Never loaded at runtime.
 */

declare(strict_types=1);

define('UAMSWP_BLOCK_REMOVER_DIR', '/var/www/wp-content/plugins/uamswp-block-remover/');
define('UAMSWP_BLOCK_REMOVER_URL', 'https://example.test/wp-content/plugins/uamswp-block-remover/');
