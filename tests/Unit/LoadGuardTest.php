<?php

/**
 * What the plugin file does when it is requested outside WordPress.
 *
 * The guard runs when the file loads, before any test can observe it in this
 * process, so each case includes the file in a fresh PHP process.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

/**
 * Include the plugin file in a new PHP process and return what it printed.
 */
function uamswp_test_load_in_new_process(bool $define_abspath): string
{
    $script = ($define_abspath ? "define('ABSPATH', '/tmp/');" : '')
        .'require '.var_export(dirname(__DIR__, 2).'/uamswp-block-remover.php', true).';';

    $output = [];
    exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>&1', $output);

    return implode("\n", $output);
}

test('the file exits silently when it is requested outside WordPress', function (): void {
    $output = uamswp_test_load_in_new_process(false);

    expect($output)->toBe('');
});

test('the file runs past the guard when ABSPATH is defined, and stops at the first WordPress function it needs', function (): void {
    $output = uamswp_test_load_in_new_process(true);

    expect($output)->toContain('Call to undefined function plugin_dir_path()');
});
