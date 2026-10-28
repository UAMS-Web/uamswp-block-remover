<?php

/**
 * First unit test pinning harness load for UAMSWP Block Remover.
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Unit;

use UamswpBlockRemover\Tests\Support\UnitTestCase;

final class HarnessSmokeTest extends UnitTestCase
{
    /**
     * @return void
     */
    public function test_harness_loads_plugin_surface(): void
    {
        $this->assertTrue(\function_exists('uamswp_block_remover_default_blocks'));
        $result = \uamswp_block_remover_default_blocks();
        $this->assertIsArray($result);
        $this->assertContains('core/archives', $result);
    }
}
