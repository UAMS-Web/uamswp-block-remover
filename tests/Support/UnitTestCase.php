<?php

/**
 * Base case for every Unit test.
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Support;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Brain Monkey around every test.
 *
 * setUp() defines WordPress' hook functions and arms Patchwork; tearDown()
 * verifies every expectation the test set (a `->once()` that never happened
 * fails here) and removes every mock, so nothing leaks into the next test.
 * MockeryPHPUnitIntegration adds Mockery's expectations to PHPUnit's assertion
 * count, so a test whose only check is `Functions\expect(...)->once()` is not
 * reported as risky.
 */
abstract class UnitTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
