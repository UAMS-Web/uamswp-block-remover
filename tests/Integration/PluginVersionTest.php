<?php

/**
 * The version constant, as WordPress loads the plugin.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Integration;

use UamswpBlockRemover\Tests\Support\IntegrationTestCase;

final class PluginVersionTest extends IntegrationTestCase
{
    public function test_the_version_constant_is_the_header_version(): void
    {
        $header = get_file_data(UAMSWP_BLOCK_REMOVER_DIR.'uamswp-block-remover.php', ['Version' => 'Version']);

        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $header['Version']);
        self::assertSame($header['Version'], UAMSWP_BLOCK_REMOVER_VERSION);
    }
}
