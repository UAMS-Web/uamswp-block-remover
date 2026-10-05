<?php

/**
 * The plugin inside a real WordPress: what the Unit suite's mocks cannot see.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Integration;

use WP_Block_Editor_Context;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

final class PluginTest extends WP_UnitTestCase
{
    private const OPTION = 'uamswp_block_remover_settings';

    public function test_the_block_filter_is_hooked_late_enough_to_have_the_final_say(): void
    {
        self::assertSame(9999, has_filter('allowed_block_types_all', 'uamswp_block_remover_filter_blocks'));
    }

    public function test_it_removes_the_default_blocks_from_every_registered_block_when_nothing_is_saved(): void
    {
        delete_option(self::OPTION);

        $registered = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
        $allowed = apply_filters('allowed_block_types_all', true, new WP_Block_Editor_Context());

        self::assertIsArray($allowed);
        self::assertContains('core/paragraph', $allowed);
        self::assertNotContains('core/archives', $allowed);
        self::assertNotContains('core/video', $allowed);
        self::assertSame(
            array_values(array_diff($registered, uamswp_block_remover_default_blocks())),
            $allowed
        );
    }

    public function test_saved_settings_round_trip_through_the_site_option(): void
    {
        self::assertTrue(uamswp_block_remover_update_settings(['blocked' => ['core/paragraph']]));

        self::assertSame(['blocked' => ['core/paragraph']], get_option(self::OPTION));
        self::assertSame(
            ['blocked' => ['core/paragraph'], 'blocked_embeds' => []],
            uamswp_block_remover_get_settings()
        );

        $allowed = apply_filters('allowed_block_types_all', true, new WP_Block_Editor_Context());

        self::assertNotContains('core/paragraph', $allowed);
        self::assertContains('core/archives', $allowed);
    }

    public function test_activation_seeds_the_defaults_once_and_never_overwrites_saved_settings(): void
    {
        delete_option(self::OPTION);

        uamswp_block_remover_activate(false);

        self::assertSame(
            ['blocked' => uamswp_block_remover_default_blocks(), 'blocked_embeds' => []],
            get_option(self::OPTION)
        );

        update_option(self::OPTION, ['blocked' => ['core/rss']]);
        uamswp_block_remover_activate(false);

        self::assertSame(['blocked' => ['core/rss']], get_option(self::OPTION));
    }
}
