<?php

/**
 * Unit-suite stand-in for core's WP_Block_Editor_Context.
 *
 * Loaded by tests/bootstrap.php only in the Unit process, where WordPress is
 * not loaded. The Integration suite uses core's own class.
 */

declare(strict_types=1);

final class WP_Block_Editor_Context
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(array $settings = [])
    {
    }
}
