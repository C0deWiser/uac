<?php

namespace Codewiser\UAC\Tests;

use Codewiser\UAC\Contracts\CacheContract;
use Codewiser\UAC\Mock\MockCache;
use Faker\Factory;

class MockContextTest extends \PHPUnit\Framework\TestCase
{
    protected CacheContract $context;
    protected \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = new MockCache();
        $this->faker = Factory::create();
    }

    public function testInvalidArgumentException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->context->get(true);
    }

    public function testHas()
    {
        $key = $this->faker->slug;
        $value = [$this->faker->slug, $this->faker->slug];

        $this->assertFalse($this->context->has($key));
        $this->assertTrue($this->context->set($key, $value));
        $this->assertTrue($this->context->has($key));
    }

    public function testGet()
    {
        $key = $this->faker->slug;
        $value = [$this->faker->slug, $this->faker->slug];

        $this->assertNull($this->context->get($key));
        $this->assertTrue($this->context->set($key, $value));
        $this->assertEquals($value, $this->context->get($key));
    }

    public function testTtl()
    {
        $key = $this->faker->slug;
        $value = [$this->faker->slug, $this->faker->slug];

        $this->assertNull($this->context->get($key));
        $this->assertTrue($this->context->set($key, $value, 1));
        sleep(2);
        $this->assertFalse($this->context->has($key));
    }

    public function testDelete()
    {
        $key = $this->faker->slug;
        $value = [$this->faker->slug, $this->faker->slug];

        $this->assertNull($this->context->get($key));
        $this->assertTrue($this->context->set($key, $value));
        $this->assertTrue($this->context->has($key));
        $this->assertTrue($this->context->delete($key));
        $this->assertFalse($this->context->has($key));
    }
}
