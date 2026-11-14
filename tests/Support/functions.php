<?php

/**
 * Helpers the Unit tests share. Loaded by tests/bootstrap.php.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

const UAMSWP_TEST_OPTION = 'uamswp_block_remover_settings';

const UAMSWP_TEST_BASENAME = 'uamswp-block-remover/uamswp-block-remover.php';

/**
 * A single site: is_multisite() is false.
 */
function uamswp_test_single_site(): void
{
    when('is_multisite')->justReturn(false);
}

/**
 * A multisite where this plugin is network-active, and whose network option
 * holds the value given.
 *
 * One alias answers both network reads, the list of network-active plugins and
 * the plugin's own option, so a test also fails if the plugin asks for any
 * other network option.
 */
function uamswp_test_network_active(mixed $saved = false): void
{
    when('is_multisite')->justReturn(true);
    when('plugin_basename')->justReturn(UAMSWP_TEST_BASENAME);
    when('get_site_option')->alias(static function (string $name) use ($saved) {
        if ($name === 'active_sitewide_plugins') {
            return [UAMSWP_TEST_BASENAME => 1700000000];
        }

        if ($name === UAMSWP_TEST_OPTION) {
            return $saved;
        }

        throw new LogicException("Unexpected network option read: {$name}");
    });
}

/**
 * Everything echoed by $render, as a string.
 */
function uamswp_test_capture(callable $render): string
{
    ob_start();
    try {
        $render();
    } finally {
        $output = (string) ob_get_clean();
    }

    return $output;
}

/**
 * The hook registrations the plugin made while it loaded, as recorded by
 * tests/Support/load-plugin.php: each entry is [function name, arguments].
 *
 * @return list<array{0: string, 1: list<mixed>}>
 */
function uamswp_test_registrations(): array
{
    return $GLOBALS['uamswp_test_registrations'];
}
