<?php

/**
 * Where the settings live and how they are read, written, seeded and removed.
 *
 * A network-active plugin keeps one option for the whole network; otherwise each
 * site keeps its own. Every function here picks between the two, so each branch
 * is pinned with a call that would fail if the other one were taken.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

$defaults = static fn (): array => [
    'blocked' => uamswp_block_remover_default_blocks(),
    'blocked_embeds' => [],
];

test('a single site is never network active', function (): void {
    uamswp_test_single_site();
    Functions\expect('get_site_option')->never();

    expect(uamswp_block_remover_is_network_active())->toBeFalse();
});

test('a multisite is network active when the plugin is in the sitewide list', function (): void {
    uamswp_test_network_active();

    expect(uamswp_block_remover_is_network_active())->toBeTrue();
});

test('a multisite is not network active when the plugin is not in the sitewide list', function (): void {
    when('is_multisite')->justReturn(true);
    when('plugin_basename')->justReturn(UAMSWP_TEST_BASENAME);
    Functions\expect('get_site_option')->once()->with('active_sitewide_plugins', [])->andReturn(['other/other.php' => 1]);

    expect(uamswp_block_remover_is_network_active())->toBeFalse();
});

test('unsaved settings are the defaults', function () use ($defaults): void {
    uamswp_test_single_site();
    Functions\expect('get_option')->once()->with(UAMSWP_TEST_OPTION, false)->andReturn(false);

    expect(uamswp_block_remover_get_settings())->toBe($defaults());
});

test('settings that are not an array are the defaults', function () use ($defaults): void {
    uamswp_test_single_site();
    when('get_option')->justReturn('corrupt');

    expect(uamswp_block_remover_get_settings())->toBe($defaults());
});

test('saved settings override the defaults and keep keys the defaults lack', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(['blocked' => ['core/quote'], 'extra' => 1]);

    expect(uamswp_block_remover_get_settings())->toBe([
        'blocked' => ['core/quote'],
        'blocked_embeds' => [],
        'extra' => 1,
    ]);
});

test('saved settings missing a key fall back to the default for that key', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(['blocked_embeds' => ['youtube']]);

    expect(uamswp_block_remover_get_settings())->toBe([
        'blocked' => uamswp_block_remover_default_blocks(),
        'blocked_embeds' => ['youtube'],
    ]);
});

test('a network-active plugin reads the network option, not the site option', function (): void {
    uamswp_test_network_active(['blocked' => ['core/quote'], 'blocked_embeds' => []]);
    Functions\expect('get_option')->never();

    expect(uamswp_block_remover_get_settings()['blocked'])->toBe(['core/quote']);
});

test('a network-active plugin with nothing saved gets the defaults', function () use ($defaults): void {
    uamswp_test_network_active(false);

    expect(uamswp_block_remover_get_settings())->toBe($defaults());
});

test('updating on a single site writes the site option and returns its result', function (): void {
    uamswp_test_single_site();
    Functions\expect('update_site_option')->never();
    Functions\expect('update_option')->once()->with(UAMSWP_TEST_OPTION, ['blocked' => []])->andReturn(true);

    expect(uamswp_block_remover_update_settings(['blocked' => []]))->toBeTrue();
});

test('updating reports a failed write', function (): void {
    uamswp_test_single_site();
    when('update_option')->justReturn(false);

    expect(uamswp_block_remover_update_settings([]))->toBeFalse();
});

test('updating when network active writes the network option and returns its result', function (): void {
    uamswp_test_network_active();
    Functions\expect('update_option')->never();
    Functions\expect('update_site_option')->once()->with(UAMSWP_TEST_OPTION, ['blocked' => []])->andReturn(true);

    expect(uamswp_block_remover_update_settings(['blocked' => []]))->toBeTrue();
});

test('deleting on a single site deletes the site option only', function (): void {
    uamswp_test_single_site();
    Functions\expect('delete_site_option')->never();
    Functions\expect('delete_option')->once()->with(UAMSWP_TEST_OPTION);

    uamswp_block_remover_delete_settings();
});

test('deleting when network active deletes the network option only', function (): void {
    uamswp_test_network_active();
    Functions\expect('delete_option')->never();
    Functions\expect('delete_site_option')->once()->with(UAMSWP_TEST_OPTION);

    uamswp_block_remover_delete_settings();
});

test('activating a single site seeds the defaults when nothing is saved', function () use ($defaults): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(false);
    Functions\expect('update_site_option')->never();
    Functions\expect('update_option')->once()->with(UAMSWP_TEST_OPTION, $defaults());

    uamswp_block_remover_activate(false);
});

test('activating a single site keeps saved settings', function (): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(['blocked' => []]);
    Functions\expect('update_option')->never();

    uamswp_block_remover_activate(false);
});

test('activating network-wide on a multisite seeds the network option', function () use ($defaults): void {
    when('is_multisite')->justReturn(true);
    when('get_site_option')->justReturn(false);

    Functions\expect('update_option')->never();
    Functions\expect('update_site_option')->once()->with(UAMSWP_TEST_OPTION, $defaults());

    uamswp_block_remover_activate(true);
});

test('activating network-wide keeps a saved network option', function (): void {
    when('is_multisite')->justReturn(true);
    when('get_site_option')->justReturn(['blocked' => []]);

    Functions\expect('update_site_option')->never();

    uamswp_block_remover_activate(true);
});

test('activating one site of a multisite seeds the site option', function () use ($defaults): void {
    when('is_multisite')->justReturn(true);
    when('get_option')->justReturn(false);

    Functions\expect('update_site_option')->never();
    Functions\expect('update_option')->once()->with(UAMSWP_TEST_OPTION, $defaults());

    uamswp_block_remover_activate(false);
});

test('activating network-wide on a single site seeds the site option', function () use ($defaults): void {
    uamswp_test_single_site();
    when('get_option')->justReturn(false);
    Functions\expect('update_site_option')->never();
    Functions\expect('update_option')->once()->with(UAMSWP_TEST_OPTION, $defaults());

    uamswp_block_remover_activate(true);
});

test('uninstalling a single site deletes the site option only', function (): void {
    uamswp_test_single_site();
    Functions\expect('delete_site_option')->never();
    Functions\expect('get_sites')->never();
    Functions\expect('delete_option')->once()->with(UAMSWP_TEST_OPTION);

    uamswp_block_remover_uninstall();
});

test('uninstalling a multisite deletes the network option and every site option', function (): void {
    when('is_multisite')->justReturn(true);
    Functions\expect('delete_site_option')->once()->with(UAMSWP_TEST_OPTION);
    Functions\expect('get_sites')->once()->with(['number' => 0, 'fields' => 'ids'])->andReturn([3, 7]);

    $log = [];
    when('switch_to_blog')->alias(static function (int $id) use (&$log): void {
        $log[] = "switch:{$id}";
    });
    when('delete_option')->alias(static function (string $name) use (&$log): void {
        $log[] = "delete:{$name}";
    });
    when('restore_current_blog')->alias(static function () use (&$log): void {
        $log[] = 'restore';
    });

    uamswp_block_remover_uninstall();

    expect($log)->toBe([
        'switch:3', 'delete:'.UAMSWP_TEST_OPTION, 'restore',
        'switch:7', 'delete:'.UAMSWP_TEST_OPTION, 'restore',
    ]);
});

test('uninstalling a multisite with no sites still deletes the network option', function (): void {
    when('is_multisite')->justReturn(true);
    Functions\expect('delete_site_option')->once()->with(UAMSWP_TEST_OPTION);
    when('get_sites')->justReturn([]);
    Functions\expect('delete_option')->never();

    uamswp_block_remover_uninstall();
});

test('a network read asks for the option with a false default', function (): void {
    when('is_multisite')->justReturn(true);
    when('plugin_basename')->justReturn(UAMSWP_TEST_BASENAME);
    when('get_site_option')->alias(static function (string $name, mixed $default = null): array {
        if ($name === 'active_sitewide_plugins') {
            return [UAMSWP_TEST_BASENAME => 1];
        }

        return $default === false ? ['blocked' => ['core/quote']] : ['blocked' => ['wrong']];
    });

    expect(uamswp_block_remover_get_settings()['blocked'])->toBe(['core/quote']);
});
