<?php


use Codewiser\UAC\Model\ResourceOwner;

class ResourceOwnerTest extends \PHPUnit\Framework\TestCase
{
    public function testPayload()
    {
        $payload = [
            'id' => rand(1, 10),
            'name' => 'zxczxczx',
            'cars' => [
                [
                    'number' => 'asfdsd',
                    'main' => true
                ]
            ]
        ];

        $rules = [
            'id' => 'required|readonly',
            'name' => 'required',
            'cars' => 'min:1',
            'cars.*.number' => 'required'
        ];

        $user = new ResourceOwner($payload, $rules);

        $this->assertTrue(is_array($user->cars));
        $this->assertEquals(1, count($user->cars));
        $this->assertEquals('asfdsd', $user->cars[0]->number);

        $this->assertEquals(1, $user->rules()->min('cars'));
        $this->assertTrue($user->rules()->isRequired('cars.*.number'));

        $this->assertTrue($user->rules()->isRequired('id'));
        $this->assertTrue($user->rules()->isReadonly('id'));

        $this->assertTrue($user->rules()->isRequired('name'));
        $this->assertNotTrue($user->rules()->isReadonly('name'));
    }
}