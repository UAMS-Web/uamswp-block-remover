<?php

/**
 * What the plugin registers and defines when it loads.
 *
 * This file sorts first on purpose (see tests/Pest.php): the plugin file loads in
 * the first test that runs, and a mutation run credits the file's load-time lines
 * to that test alone. Every statement the plugin runs at file scope is asserted here.
 *
 * WordPress includes the main file, which includes the admin page, and both call
 * add_action(), add_filter() and the register_*_hook() functions at file scope.
 * tests/Support/load-plugin.php records those calls while it loads the plugin, so these
 * tests read the arguments back rather than needing WordPress to dispatch them.
 *
 * @see uamswp-block-remover.php
 * @see admin/settings-page.php
 */

declare(strict_types=1);

/**
 * The recorded calls to one registrar, as lists of arguments.
 *
 * @return list<list<mixed>>
 */
function uamswp_test_calls_to(string $registrar): array
{
    $calls = [];
    foreach (uamswp_test_registrations() as [$name, $arguments]) {
        if ($name === $registrar) {
            $calls[] = $arguments;
        }
    }

    return $calls;
}

test('loading the plugin defines its constants and registers every hook', function (): void {
    $registered = array_map(
        static fn (array $call): array => $call[0] === 'register_activation_hook' || $call[0] === 'register_uninstall_hook'
            ? [$call[0], basename((string) $call[1][0]), $call[1][1]]
            : [$call[0], ...$call[1]],
        uamswp_test_registrations()
    );

    expect(UAMSWP_BLOCK_REMOVER_VERSION)->toBe('1.3.1')
        ->and(UAMSWP_BLOCK_REMOVER_OPTION)->toBe('uamswp_block_remover_settings')
        ->and(UAMSWP_BLOCK_REMOVER_DIR)->toBe(dirname(__DIR__, 2).'/')
        ->and(UAMSWP_BLOCK_REMOVER_URL)->toEndWith('/'.basename(dirname(__DIR__, 2)).'/')
        ->and($registered)->toEqualCanonicalizing([
            ['register_activation_hook', 'uamswp-block-remover.php', 'uamswp_block_remover_activate'],
            ['register_uninstall_hook', 'uamswp-block-remover.php', 'uamswp_block_remover_uninstall'],
            ['add_action', 'init', 'uamswp_block_remover_register_hooks'],
            ['add_filter', 'allowed_block_types_all', 'uamswp_block_remover_filter_blocks', 9999, 2],
            ['add_action', 'enqueue_block_editor_assets', 'uamswp_block_remover_enqueue_embed_filter'],
        ]);
});

test('the block filter is hooked at the last priority, with both arguments', function (): void {
    expect(uamswp_test_calls_to('add_filter'))->toBe([
        ['allowed_block_types_all', 'uamswp_block_remover_filter_blocks', 9999, 2],
    ]);
});

test('the embed filter script is enqueued with the block editor assets', function (): void {
    expect(uamswp_test_calls_to('add_action'))->toContain(
        ['enqueue_block_editor_assets', 'uamswp_block_remover_enqueue_embed_filter']
    );
});

test('the admin page routes its hooks on init', function (): void {
    expect(uamswp_test_calls_to('add_action'))->toContain(
        ['init', 'uamswp_block_remover_register_hooks']
    );
});

test('the plugin registers exactly the two actions it adds at load', function (): void {
    expect(uamswp_test_calls_to('add_action'))->toHaveCount(2);
});

test('activation is registered for the main plugin file', function (): void {
    $calls = uamswp_test_calls_to('register_activation_hook');

    expect($calls)->toHaveCount(1)
        ->and(basename((string) $calls[0][0]))->toBe('uamswp-block-remover.php')
        ->and($calls[0][1])->toBe('uamswp_block_remover_activate');
});

test('uninstall is registered for the main plugin file', function (): void {
    $calls = uamswp_test_calls_to('register_uninstall_hook');

    expect($calls)->toHaveCount(1)
        ->and(basename((string) $calls[0][0]))->toBe('uamswp-block-remover.php')
        ->and($calls[0][1])->toBe('uamswp_block_remover_uninstall');
});

test('no deactivation hook is registered', function (): void {
    expect(uamswp_test_calls_to('register_deactivation_hook'))->toBe([]);
});

test('the admin page file is loaded with the plugin', function (): void {
    expect(function_exists('uamswp_block_remover_register_hooks'))->toBeTrue()
        ->and(uamswp_test_calls_to('add_action'))->toContain(['init', 'uamswp_block_remover_register_hooks']);
});
