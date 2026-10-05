<?php

/**
 * uamswp_block_remover_filter_blocks(), with WordPress mocked.
 *
 * The branch where WordPress allows every block (`$allowed === true`) reads the
 * real block registry, so it is covered in the Integration suite.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Unit;

use Brain\Monkey\Functions;
use UamswpBlockRemover\Tests\Support\UnitTestCase;
use WP_Block_Editor_Context;

final class FilterBlocksTest extends UnitTestCase
{
    /**
     * A single site whose saved option is the value given.
     *
     * @param  mixed  $saved
     */
    private function givenSiteSettings($saved): void
    {
        Functions\when('is_multisite')->justReturn(false);
        Functions\expect('get_option')
            ->once()
            ->with('uamswp_block_remover_settings', false)
            ->andReturn($saved);
    }

    public function test_it_removes_blocked_blocks_from_an_explicit_allowlist(): void
    {
        $this->givenSiteSettings(['blocked' => ['core/audio', 'core/video']]);

        $allowed = ['core/paragraph', 'core/audio', 'core/heading', 'core/video'];

        self::assertSame(
            ['core/paragraph', 'core/heading'],
            uamswp_block_remover_filter_blocks($allowed, new WP_Block_Editor_Context())
        );
    }

    public function test_it_leaves_an_editor_that_allows_nothing_alone(): void
    {
        $this->givenSiteSettings(['blocked' => ['core/audio']]);

        self::assertFalse(uamswp_block_remover_filter_blocks(false, new WP_Block_Editor_Context()));
    }

    /**
     * @dataProvider allowedValues
     *
     * @param  bool|string[]  $allowed
     */
    public function test_it_returns_what_it_was_given_when_nothing_is_blocked($allowed): void
    {
        $this->givenSiteSettings(['blocked' => []]);

        self::assertSame($allowed, uamswp_block_remover_filter_blocks($allowed, new WP_Block_Editor_Context()));
    }

    /**
     * @return array<string, array{0: bool|string[]}>
     */
    public function allowedValues(): array
    {
        return [
            'every block allowed' => [true],
            'an explicit allowlist' => [['core/paragraph', 'core/audio']],
        ];
    }

    public function test_it_falls_back_to_the_default_list_when_the_saved_list_is_not_an_array(): void
    {
        $this->givenSiteSettings(['blocked' => 'core/paragraph']);

        self::assertSame(
            ['core/paragraph', 'core/image'],
            uamswp_block_remover_filter_blocks(
                ['core/paragraph', 'core/audio', 'core/image', 'core/video'],
                new WP_Block_Editor_Context()
            )
        );
    }
}
