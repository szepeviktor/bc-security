<?php
/**
 * @package BC Security
 */

namespace BlueChip\Security\Tests\Unit;

use Brain\Monkey;

class TestCase extends \PHPUnit\Framework\TestCase
{
    // See: https://github.com/Brain-WP/BrainMonkey/issues/39
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp()
    {
        parent::setUp();
        Monkey\setUp();

        // Mock some more WordPress functions
        Monkey\Functions\stubs(
            [
                '__', // https://github.com/Brain-WP/BrainMonkey/issues/25
            ]
        );
    }

    protected function tearDown()
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
