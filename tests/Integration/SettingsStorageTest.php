<?php

/**
 * Where the settings live, against the real options table.
 *
 * The Unit suite pins each branch against mocked option functions. These tests
 * let WordPress store, serialize and delete the value, so a difference between
 * the mocks and the table shows up here.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Integration;

use UamswpBlockRemover\Tests\Support\IntegrationTestCase;

final class SettingsStorageTest extends IntegrationTestCase
{
    private const OPTION = 'uamswp_block_remover_settings';

    /**
     * @return array{blocked: list<string>, blocked_embeds: list<string>}
     */
    private static function defaults(): array
    {
        return [
            'blocked' => uamswp_block_remover_default_blocks(),
            'blocked_embeds' => uamswp_block_remover_default_blocked_embeds(),
        ];
    }

    public function test_a_single_site_is_never_network_active(): void
    {
        self::assertFalse(is_multisite());
        self::assertFalse(uamswp_block_remover_is_network_active());
    }

    public function test_unsaved_settings_are_the_defaults(): void
    {
        delete_option(self::OPTION);

        self::assertSame(self::defaults(), uamswp_block_remover_get_settings());
    }

    public function test_a_stored_value_that_is_not_an_array_is_the_defaults(): void
    {
        update_option(self::OPTION, 'corrupt');

        self::assertSame('corrupt', get_option(self::OPTION));
        self::assertSame(self::defaults(), uamswp_block_remover_get_settings());
    }

    public function test_a_partial_saved_value_keeps_the_default_for_the_missing_key(): void
    {
        update_option(self::OPTION, ['blocked_embeds' => ['youtube']]);

        self::assertSame(
            ['blocked' => uamswp_block_remover_default_blocks(), 'blocked_embeds' => ['youtube']],
            uamswp_block_remover_get_settings()
        );
    }

    public function test_a_saved_value_overrides_the_defaults_and_keeps_extra_keys(): void
    {
        update_option(self::OPTION, ['blocked' => ['core/quote'], 'extra' => 1]);

        self::assertSame(
            ['blocked' => ['core/quote'], 'blocked_embeds' => uamswp_block_remover_default_blocked_embeds(), 'extra' => 1],
            uamswp_block_remover_get_settings()
        );
    }

    public function test_updating_an_unchanged_value_reports_false_because_nothing_was_written(): void
    {
        self::assertTrue(uamswp_block_remover_update_settings(['blocked' => ['core/quote']]));
        self::assertFalse(uamswp_block_remover_update_settings(['blocked' => ['core/quote']]));
    }

    public function test_updating_on_a_single_site_writes_the_site_option_that_the_network_read_also_sees(): void
    {
        delete_option(self::OPTION);

        uamswp_block_remover_update_settings(['blocked' => ['core/list']]);

        self::assertSame(['blocked' => ['core/list']], get_option(self::OPTION));
        // On a single site WordPress maps the network option onto the site option.
        self::assertSame(['blocked' => ['core/list']], get_site_option(self::OPTION));
    }

    public function test_deleting_removes_the_stored_option(): void
    {
        update_option(self::OPTION, ['blocked' => ['core/list']]);

        uamswp_block_remover_delete_settings();

        self::assertFalse(get_option(self::OPTION));
        self::assertSame(self::defaults(), uamswp_block_remover_get_settings());
    }

    public function test_activating_a_single_site_keeps_saved_settings(): void
    {
        update_option(self::OPTION, ['blocked' => []]);

        uamswp_block_remover_activate(false);

        self::assertSame(['blocked' => []], get_option(self::OPTION));
    }

    public function test_activating_network_wide_on_a_single_site_seeds_the_site_option(): void
    {
        delete_option(self::OPTION);

        uamswp_block_remover_activate(true);

        self::assertSame(self::defaults(), get_option(self::OPTION));
    }

    public function test_uninstalling_a_single_site_deletes_the_site_option(): void
    {
        update_option(self::OPTION, ['blocked' => ['core/list']]);

        uamswp_block_remover_uninstall();

        self::assertFalse(get_option(self::OPTION));
    }
}
