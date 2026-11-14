<?php

/**
 * Unit-suite stand-in for core's WP_Block_Type_Registry.
 *
 * Only what the plugin reads: the singleton and the list of registered block
 * types, keyed by name. A test sets `$registered` to the names it wants the
 * registry to hold. The Integration suite uses core's own registry.
 */

declare(strict_types=1);

final class WP_Block_Type_Registry
{
    /**
     * @var list<string>
     */
    public static array $registered = [];

    private static ?self $instance = null;

    public static function get_instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * @return array<string, object>
     */
    public function get_all_registered(): array
    {
        $all = [];
        foreach (self::$registered as $name) {
            $all[$name] = new stdClass;
        }

        return $all;
    }
}
