<?php

namespace Codewiser\UAC\Tests;

use Codewiser\UAC\ContextManager;
use Codewiser\UAC\Mock\MockCache;
use Faker\Factory;

class ContextManagerTest extends \PHPUnit\Framework\TestCase
{
    protected ContextManager $contextManager;
    protected \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextManager = new ContextManager(new MockCache());
        $this->faker = Factory::create();
    }

    public function testEmptyContext()
    {
        $state = $this->faker->slug;

        $this->assertFalse($this->contextManager->restoreContext($state));
        $this->assertNull($this->contextManager->state);
    }

    public function testRestoreContext()
    {
        $state = $this->faker->slug;
        $response_type = $this->faker->slug;

        $this->contextManager->state = $state;
        $this->contextManager->response_type = $response_type;

        // Context doesn't exist
        $this->assertFalse($this->contextManager->restoreContext($this->faker->slug));
        $this->assertNull($this->contextManager->state);
        $this->assertNull($this->contextManager->response_type);

        // Context restored
        $this->assertTrue($this->contextManager->restoreContext($state));
        $this->assertEquals($state, $this->contextManager->state);
        $this->assertEquals($response_type, $this->contextManager->response_type);

        // Context can be restored just once
        $this->assertFalse($this->contextManager->restoreContext($state));
    }
}
