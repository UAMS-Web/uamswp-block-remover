<?php

/**
 * The block filter and the embed script enqueue.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

/**
 * Answer the settings read with the given saved value.
 */
function uamswp_test_saved(mixed $saved): void
{
    uamswp_test_single_site();
    when('get_option')->justReturn($saved);
}

beforeEach(function (): void {
    WP_Block_Type_Registry::$registered = [];
});

test('nothing blocked leaves a true allowlist alone', function (): void {
    uamswp_test_saved(['blocked' => []]);

    expect(uamswp_block_remover_filter_blocks(true, new WP_Block_Editor_Context))->toBeTrue();
});

test('nothing blocked leaves an array allowlist alone', function (): void {
    uamswp_test_saved(['blocked' => []]);

    expect(uamswp_block_remover_filter_blocks(['core/quote', 'core/list'], new WP_Block_Editor_Context))
        ->toBe(['core/quote', 'core/list']);
});

test('false stays false', function (): void {
    uamswp_test_saved(['blocked' => ['core/quote']]);

    expect(uamswp_block_remover_filter_blocks(false, new WP_Block_Editor_Context))->toBeFalse();
});

test('blocked blocks are subtracted from an array allowlist and the keys are reset', function (): void {
    uamswp_test_saved(['blocked' => ['core/quote', 'core/list']]);

    $result = uamswp_block_remover_filter_blocks(
        ['core/paragraph', 'core/quote', 'core/heading', 'core/list'],
        new WP_Block_Editor_Context
    );

    expect($result)->toBe(['core/paragraph', 'core/heading']);
});

test('true becomes every registered block minus the blocked ones', function (): void {
    uamswp_test_saved(['blocked' => ['core/quote']]);
    WP_Block_Type_Registry::$registered = ['core/paragraph', 'core/quote', 'core/heading'];

    $result = uamswp_block_remover_filter_blocks(true, new WP_Block_Editor_Context);

    expect($result)->toBe(['core/paragraph', 'core/heading']);
});

test('true with nothing registered is an empty list', function (): void {
    uamswp_test_saved(['blocked' => ['core/quote']]);

    expect(uamswp_block_remover_filter_blocks(true, new WP_Block_Editor_Context))->toBe([]);
});

test('a saved blocked value that is not an array falls back to the default list', function (): void {
    uamswp_test_saved(['blocked' => 'core/quote']);
    WP_Block_Type_Registry::$registered = ['core/paragraph', 'core/audio'];

    $default = uamswp_block_remover_default_blocks();
    expect($default)->toContain('core/audio');

    expect(uamswp_block_remover_filter_blocks(true, new WP_Block_Editor_Context))->toBe(['core/paragraph']);
});

test('no saved settings use the default blocked list', function (): void {
    uamswp_test_saved(false);

    $allowed = ['core/paragraph', 'core/audio'];

    expect(uamswp_block_remover_filter_blocks($allowed, new WP_Block_Editor_Context))->toBe(['core/paragraph']);
});

test('the embed script is not enqueued when no embed is blocked', function (): void {
    uamswp_test_saved(['blocked_embeds' => []]);
    Functions\expect('wp_enqueue_script')->never();
    Functions\expect('wp_localize_script')->never();

    uamswp_block_remover_enqueue_embed_filter();
});

test('the embed script is not enqueued when the saved embeds are not an array', function (): void {
    uamswp_test_saved(['blocked_embeds' => 'youtube']);
    Functions\expect('wp_enqueue_script')->never();

    uamswp_block_remover_enqueue_embed_filter();
});

test('the embed script is enqueued and given the blocked embeds', function (): void {
    uamswp_test_saved(['blocked_embeds' => [4 => 'youtube', 9 => 'vimeo']]);
    Functions\expect('wp_enqueue_script')->once()->with(
        'uamswp-block-remover-embeds',
        UAMSWP_BLOCK_REMOVER_URL.'assets/js/embed-filter.js',
        ['wp-hooks', 'wp-blocks'],
        UAMSWP_BLOCK_REMOVER_VERSION,
        true
    );
    Functions\expect('wp_localize_script')->once()->with(
        'uamswp-block-remover-embeds',
        'uamswpBREmbeds',
        ['blockedEmbeds' => ['youtube', 'vimeo']]
    );

    uamswp_block_remover_enqueue_embed_filter();
});
