<?php

/**
 * The block filter and the embed script, dispatched by real WordPress.
 *
 * The Unit suite calls the filter and the enqueue function directly, with a stub
 * block registry and mocked script functions. These tests go through
 * apply_filters() and do_action() with the real registry and script registry.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Integration;

use UamswpBlockRemover\Tests\Support\IntegrationTestCase;
use WP_Block_Editor_Context;
use WP_Block_Type_Registry;

final class BlockFilterTest extends IntegrationTestCase
{
    private const OPTION = 'uamswp_block_remover_settings';

    private const HANDLE = 'uamswp-block-remover-embeds';

    protected function tearDown(): void
    {
        wp_dequeue_script(self::HANDLE);
        wp_deregister_script(self::HANDLE);

        parent::tearDown();
    }

    /**
     * @return bool|list<string>
     */
    private static function allowed(mixed $input): bool|array
    {
        /** @var bool|list<string> */
        return apply_filters('allowed_block_types_all', $input, new WP_Block_Editor_Context);
    }

    public function test_true_becomes_every_registered_block_minus_the_blocked_ones(): void
    {
        update_option(self::OPTION, ['blocked' => ['core/quote', 'core/list']]);

        $registered = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
        $allowed = self::allowed(true);

        self::assertContains('core/quote', $registered);
        self::assertIsArray($allowed);
        self::assertNotContains('core/quote', $allowed);
        self::assertNotContains('core/list', $allowed);
        self::assertContains('core/paragraph', $allowed);
        self::assertSame(array_values(array_diff($registered, ['core/quote', 'core/list'])), $allowed);
    }

    public function test_an_explicit_allowlist_has_the_blocked_blocks_subtracted_and_keys_reset(): void
    {
        update_option(self::OPTION, ['blocked' => ['core/quote']]);

        self::assertSame(
            ['core/paragraph', 'core/heading'],
            self::allowed(['core/paragraph', 'core/quote', 'core/heading'])
        );
    }

    public function test_nothing_blocked_leaves_the_input_alone(): void
    {
        update_option(self::OPTION, ['blocked' => []]);

        self::assertTrue(self::allowed(true));
        self::assertSame(['core/quote'], self::allowed(['core/quote']));
    }

    public function test_false_stays_false(): void
    {
        update_option(self::OPTION, ['blocked' => ['core/quote']]);

        self::assertFalse(self::allowed(false));
    }

    public function test_a_later_filter_at_a_lower_priority_cannot_restore_a_blocked_block(): void
    {
        update_option(self::OPTION, ['blocked' => ['core/quote']]);
        add_filter('allowed_block_types_all', static fn (): array => ['core/paragraph', 'core/quote'], 10);

        self::assertSame(['core/paragraph'], self::allowed(true));
    }

    public function test_no_embed_script_is_enqueued_when_no_embed_is_blocked(): void
    {
        update_option(self::OPTION, ['blocked_embeds' => []]);

        do_action('enqueue_block_editor_assets');

        self::assertFalse(wp_script_is(self::HANDLE, 'enqueued'));
    }

    public function test_the_embed_script_is_enqueued_and_localized_with_the_blocked_embeds(): void
    {
        update_option(self::OPTION, ['blocked_embeds' => [4 => 'youtube', 9 => 'vimeo']]);

        do_action('enqueue_block_editor_assets');

        self::assertTrue(wp_script_is(self::HANDLE, 'enqueued'));

        $script = wp_scripts()->registered[self::HANDLE];

        self::assertSame(['wp-hooks', 'wp-blocks'], $script->deps);
        self::assertSame(UAMSWP_BLOCK_REMOVER_VERSION, $script->ver);
        self::assertSame(UAMSWP_BLOCK_REMOVER_URL.'assets/js/embed-filter.js', $script->src);
        self::assertStringContainsString(
            'var uamswpBREmbeds = {"blockedEmbeds":["youtube","vimeo"]};',
            (string) $script->extra['data']
        );
    }

    public function test_the_embed_script_file_exists_where_it_is_enqueued_from(): void
    {
        self::assertFileExists(UAMSWP_BLOCK_REMOVER_DIR.'assets/js/embed-filter.js');
    }
}
