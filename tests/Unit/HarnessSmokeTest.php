<?php

/**
 * First unit test pinning harness load for UAMSWP Block Remover.
 */

declare(strict_types=1);

test('the harness loads the plugin surface', function (): void {
    expect(function_exists('uamswp_block_remover_default_blocks'))->toBeTrue();

    $result = uamswp_block_remover_default_blocks();

    expect($result)->toBeArray()
        ->and($result)->toContain('core/archives');
});
