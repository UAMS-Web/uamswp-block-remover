<?php

/**
 * What the plugin registers with WordPress when it loads.
 *
 * WordPress includes the main file, which includes the admin page, and both call
 * add_action(), add_filter() and the register_*_hook() functions at file scope.
 * tests/bootstrap.php records those calls while it loads the plugin, so these
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
