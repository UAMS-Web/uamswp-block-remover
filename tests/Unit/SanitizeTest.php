<?php

/**
 * The settings sanitizers in admin/settings-page.php.
 *
 * sanitize_text_field() is stubbed to return its argument, so these tests
 * cover the plugin's own filtering (the block-name pattern, the known embed
 * list, de-duplication), not WordPress' text cleaning.
 *
 * @see admin/settings-page.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Unit;

use Brain\Monkey\Functions;
use UamswpBlockRemover\Tests\Support\UnitTestCase;

final class SanitizeTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('sanitize_text_field')->returnArg();
    }

    public function test_it_keeps_namespaced_block_names_and_drops_everything_else(): void
    {
        self::assertSame(
            ['core/paragraph', 'acf/my-block', 'gravityforms/form'],
            uamswp_block_remover_sanitize_block_list([
                'core/paragraph',
                'acf/my-block',
                'Core/heading',
                'no-namespace',
                'core/paragraph',
                'a/b/c',
                '-leading/hyphen',
                'core/',
                'gravityforms/form',
            ])
        );
    }

    public function test_it_keeps_only_embed_variations_wordpress_ships(): void
    {
        self::assertSame(
            ['youtube', 'vimeo'],
            uamswp_block_remover_sanitize_embed_list(['youtube', 'not-a-provider', 'vimeo', 'youtube', 'YouTube'])
        );
    }

    public function test_it_turns_a_submission_missing_either_list_into_two_empty_lists(): void
    {
        self::assertSame(
            ['blocked' => [], 'blocked_embeds' => []],
            uamswp_block_remover_sanitize_settings_input(['blocked' => 'core/paragraph'])
        );
    }
}
