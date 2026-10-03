<?php

/**
 * The option read/write helpers, which choose between the network option and
 * the site option by how the plugin is activated.
 *
 * Whether the values round-trip through the database is covered in the
 * Integration suite.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Unit;

use Brain\Monkey\Functions;
use UamswpBlockRemover\Tests\Support\UnitTestCase;

final class SettingsTest extends UnitTestCase
{
    private const OPTION = 'uamswp_block_remover_settings';

    private const BASENAME = 'uamswp-block-remover/uamswp-block-remover.php';

    private function givenSingleSite(): void
    {
        Functions\when('is_multisite')->justReturn(false);
    }

    /**
     * A multisite where this plugin is network-active, and whose network
     * option holds the value given.
     *
     * One alias answers both network reads, the list of network-active plugins
     * and the plugin's own option, so the test also fails if the plugin asks
     * for any other network option.
     *
     * @param  mixed  $saved
     */
    private function givenNetworkActivation($saved = false): void
    {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('plugin_basename')->justReturn(self::BASENAME);
        Functions\when('get_site_option')->alias(static function (string $name) use ($saved) {
            if ($name === 'active_sitewide_plugins') {
                return [self::BASENAME => 1700000000];
            }

            if ($name === self::OPTION) {
                return $saved;
            }

            throw new \LogicException("Unexpected network option read: {$name}");
        });
    }

    public function test_it_returns_the_defaults_when_nothing_has_been_saved(): void
    {
        $this->givenSingleSite();
        Functions\expect('get_option')->once()->with(self::OPTION, false)->andReturn(false);

        self::assertSame(
            [
                'blocked' => uamswp_block_remover_default_blocks(),
                'blocked_embeds' => [],
            ],
            uamswp_block_remover_get_settings()
        );
    }

    public function test_it_fills_keys_missing_from_the_saved_settings_with_their_defaults(): void
    {
        $this->givenSingleSite();
        Functions\expect('get_option')->once()->with(self::OPTION, false)->andReturn(['blocked' => ['core/rss']]);

        self::assertSame(
            ['blocked' => ['core/rss'], 'blocked_embeds' => []],
            uamswp_block_remover_get_settings()
        );
    }

    public function test_it_reads_the_network_option_when_network_active(): void
    {
        $this->givenNetworkActivation(['blocked_embeds' => ['youtube']]);
        Functions\expect('get_option')->never();

        self::assertSame(
            ['blocked' => uamswp_block_remover_default_blocks(), 'blocked_embeds' => ['youtube']],
            uamswp_block_remover_get_settings()
        );
    }

    public function test_it_reads_the_site_option_on_a_multisite_where_it_is_activated_per_site(): void
    {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('plugin_basename')->justReturn(self::BASENAME);
        Functions\expect('get_site_option')->once()->with('active_sitewide_plugins', [])->andReturn([]);
        Functions\expect('get_option')->once()->with(self::OPTION, false)->andReturn(['blocked' => []]);

        self::assertSame(['blocked' => [], 'blocked_embeds' => []], uamswp_block_remover_get_settings());
    }

    public function test_it_saves_to_the_site_option_on_a_single_site(): void
    {
        $this->givenSingleSite();
        Functions\expect('update_option')->once()->with(self::OPTION, ['blocked' => ['core/rss']])->andReturn(true);
        Functions\expect('update_site_option')->never();

        self::assertTrue(uamswp_block_remover_update_settings(['blocked' => ['core/rss']]));
    }

    public function test_it_saves_to_the_network_option_when_network_active(): void
    {
        $this->givenNetworkActivation();
        Functions\expect('update_site_option')->once()->with(self::OPTION, ['blocked' => ['core/rss']])->andReturn(true);
        Functions\expect('update_option')->never();

        self::assertTrue(uamswp_block_remover_update_settings(['blocked' => ['core/rss']]));
    }
}
