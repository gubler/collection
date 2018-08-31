<?php

namespace Gubler\Tests\Collection;

use Gubler\Collection\Helper;
use stdClass;
use ArrayAccess;
use Mockery as m;
use RuntimeException;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testClassBasename()
    {
        $this->assertEquals('Baz', Helper::class_basename('Foo\Bar\Baz'));
        $this->assertEquals('Baz', Helper::class_basename('Baz'));
    }

    public function testValue()
    {
        $this->assertEquals('foo', Helper::value('foo'));
        $this->assertEquals('foo', Helper::value(function () {
            return 'foo';
        }));
    }

    public function testObjectGet()
    {
        $class = new stdClass;
        $class->name = new stdClass;
        $class->name->first = 'Taylor';

        $this->assertEquals('Taylor', Helper::object_get($class, 'name.first'));
    }

    public function testDataGet()
    {
        $object = (object) ['users' => ['name' => ['Taylor', 'Otwell']]];
        $array = [(object) ['users' => [(object) ['name' => 'Taylor']]]];
        $dottedArray = ['users' => ['first.name' => 'Taylor', 'middle.name' => null]];
        $arrayAccess = new SupportTestArrayAccess(['price' => 56, 'user' => new SupportTestArrayAccess(['name' => 'John']), 'email' => null]);

        $this->assertEquals('Taylor', Helper::data_get($object, 'users.name.0'));
        $this->assertEquals('Taylor', Helper::data_get($array, '0.users.0.name'));
        $this->assertNull(Helper::data_get($array, '0.users.3'));
        $this->assertEquals('Not found', Helper::data_get($array, '0.users.3', 'Not found'));
        $this->assertEquals('Not found', Helper::data_get($array, '0.users.3', function () {
            return 'Not found';
        }));
        $this->assertEquals('Taylor', Helper::data_get($dottedArray, ['users', 'first.name']));
        $this->assertNull(Helper::data_get($dottedArray, ['users', 'middle.name']));
        $this->assertEquals('Not found', Helper::data_get($dottedArray, ['users', 'last.name'], 'Not found'));
        $this->assertEquals(56, Helper::data_get($arrayAccess, 'price'));
        $this->assertEquals('John', Helper::data_get($arrayAccess, 'user.name'));
        $this->assertEquals('void', Helper::data_get($arrayAccess, 'foo', 'void'));
        $this->assertEquals('void', Helper::data_get($arrayAccess, 'user.foo', 'void'));
        $this->assertNull(Helper::data_get($arrayAccess, 'foo'));
        $this->assertNull(Helper::data_get($arrayAccess, 'user.foo'));
        $this->assertNull(Helper::data_get($arrayAccess, 'email', 'Not found'));
    }

    public function testDataGetWithNestedArrays()
    {
        $array = [
            ['name' => 'taylor', 'email' => 'taylorotwell@gmail.com'],
            ['name' => 'abigail'],
            ['name' => 'dayle'],
        ];

        $this->assertEquals(['taylor', 'abigail', 'dayle'], Helper::data_get($array, '*.name'));
        $this->assertEquals(['taylorotwell@gmail.com', null, null], Helper::data_get($array, '*.email', 'irrelevant'));

        $array = [
            'users' => [
                ['first' => 'taylor', 'last' => 'otwell', 'email' => 'taylorotwell@gmail.com'],
                ['first' => 'abigail', 'last' => 'otwell'],
                ['first' => 'dayle', 'last' => 'rees'],
            ],
            'posts' => null,
        ];

        $this->assertEquals(['taylor', 'abigail', 'dayle'], Helper::data_get($array, 'users.*.first'));
        $this->assertEquals(['taylorotwell@gmail.com', null, null], Helper::data_get($array, 'users.*.email', 'irrelevant'));
        $this->assertEquals('not found', Helper::data_get($array, 'posts.*.date', 'not found'));
        $this->assertNull(Helper::data_get($array, 'posts.*.date'));
    }

    public function testDataGetWithDoubleNestedArraysCollapsesResult()
    {
        $array = [
            'posts' => [
                [
                    'comments' => [
                        ['author' => 'taylor', 'likes' => 4],
                        ['author' => 'abigail', 'likes' => 3],
                    ],
                ],
                [
                    'comments' => [
                        ['author' => 'abigail', 'likes' => 2],
                        ['author' => 'dayle'],
                    ],
                ],
                [
                    'comments' => [
                        ['author' => 'dayle'],
                        ['author' => 'taylor', 'likes' => 1],
                    ],
                ],
            ],
        ];

        $this->assertEquals(['taylor', 'abigail', 'abigail', 'dayle', 'dayle', 'taylor'], Helper::data_get($array, 'posts.*.comments.*.author'));
        $this->assertEquals([4, 3, 2, null, null, 1], Helper::data_get($array, 'posts.*.comments.*.likes'));
        $this->assertEquals([], Helper::data_get($array, 'posts.*.users.*.name', 'irrelevant'));
        $this->assertEquals([], Helper::data_get($array, 'posts.*.users.*.name'));
    }

    public function testDataFill()
    {
        $data = ['foo' => 'bar'];

        $this->assertEquals(['foo' => 'bar', 'baz' => 'boom'], Helper::data_fill($data, 'baz', 'boom'));
        $this->assertEquals(['foo' => 'bar', 'baz' => 'boom'], Helper::data_fill($data, 'baz', 'noop'));
        $this->assertEquals(['foo' => [], 'baz' => 'boom'], Helper::data_fill($data, 'foo.*', 'noop'));
        $this->assertEquals(
            ['foo' => ['bar' => 'kaboom'], 'baz' => 'boom'],
            Helper::data_fill($data, 'foo.bar', 'kaboom')
        );
    }

    public function testDataFillWithStar()
    {
        $data = ['foo' => 'bar'];

        $this->assertEquals(
            ['foo' => []],
            Helper::data_fill($data, 'foo.*.bar', 'noop')
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'original'], []]],
            Helper::data_fill($data, 'bar', [['baz' => 'original'], []])
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'original'], ['baz' => 'boom']]],
            Helper::data_fill($data, 'bar.*.baz', 'boom')
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'original'], ['baz' => 'boom']]],
            Helper::data_fill($data, 'bar.*', 'noop')
        );
    }

    public function testDataFillWithDoubleStar()
    {
        $data = [
            'posts' => [
                (object) [
                    'comments' => [
                        (object) ['name' => 'First'],
                        (object) [],
                    ],
                ],
                (object) [
                    'comments' => [
                        (object) [],
                        (object) ['name' => 'Second'],
                    ],
                ],
            ],
        ];

        Helper::data_fill($data, 'posts.*.comments.*.name', 'Filled');

        $this->assertEquals([
            'posts' => [
                (object) [
                    'comments' => [
                        (object) ['name' => 'First'],
                        (object) ['name' => 'Filled'],
                    ],
                ],
                (object) [
                    'comments' => [
                        (object) ['name' => 'Filled'],
                        (object) ['name' => 'Second'],
                    ],
                ],
            ],
        ], $data);
    }

    public function testDataSet()
    {
        $data = ['foo' => 'bar'];

        $this->assertEquals(
            ['foo' => 'bar', 'baz' => 'boom'],
            Helper::data_set($data, 'baz', 'boom')
        );

        $this->assertEquals(
            ['foo' => 'bar', 'baz' => 'kaboom'],
            Helper::data_set($data, 'baz', 'kaboom')
        );

        $this->assertEquals(
            ['foo' => [], 'baz' => 'kaboom'],
            Helper::data_set($data, 'foo.*', 'noop')
        );

        $this->assertEquals(
            ['foo' => ['bar' => 'boom'], 'baz' => 'kaboom'],
            Helper::data_set($data, 'foo.bar', 'boom')
        );

        $this->assertEquals(
            ['foo' => ['bar' => 'boom'], 'baz' => ['bar' => 'boom']],
            Helper::data_set($data, 'baz.bar', 'boom')
        );

        $this->assertEquals(
            ['foo' => ['bar' => 'boom'], 'baz' => ['bar' => ['boom' => ['kaboom' => 'boom']]]],
            Helper::data_set($data, 'baz.bar.boom.kaboom', 'boom')
        );
    }

    public function testDataSetWithStar()
    {
        $data = ['foo' => 'bar'];

        $this->assertEquals(
            ['foo' => []],
            Helper::data_set($data, 'foo.*.bar', 'noop')
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'original'], []]],
            Helper::data_set($data, 'bar', [['baz' => 'original'], []])
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'boom'], ['baz' => 'boom']]],
            Helper::data_set($data, 'bar.*.baz', 'boom')
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => ['overwritten', 'overwritten']],
            Helper::data_set($data, 'bar.*', 'overwritten')
        );
    }

    public function testDataSetWithDoubleStar()
    {
        $data = [
            'posts' => [
                (object) [
                    'comments' => [
                        (object) ['name' => 'First'],
                        (object) [],
                    ],
                ],
                (object) [
                    'comments' => [
                        (object) [],
                        (object) ['name' => 'Second'],
                    ],
                ],
            ],
        ];

        Helper::data_set($data, 'posts.*.comments.*.name', 'Filled');

        $this->assertEquals([
            'posts' => [
                (object) [
                    'comments' => [
                        (object) ['name' => 'Filled'],
                        (object) ['name' => 'Filled'],
                    ],
                ],
                (object) [
                    'comments' => [
                        (object) ['name' => 'Filled'],
                        (object) ['name' => 'Filled'],
                    ],
                ],
            ],
        ], $data);
    }

    public function testHead()
    {
        $array = ['a', 'b', 'c'];
        $this->assertEquals('a', Helper::head($array));
    }

    public function testLast()
    {
        $array = ['a', 'b', 'c'];
        $this->assertEquals('c', Helper::last($array));
    }

    public function testClassUsesRecursiveShouldReturnTraitsOnParentClasses()
    {
        $this->assertSame([
            'Gubler\Tests\Collection\SupportTestTraitTwo' => 'Gubler\Tests\Collection\SupportTestTraitTwo',
            'Gubler\Tests\Collection\SupportTestTraitOne' => 'Gubler\Tests\Collection\SupportTestTraitOne',
        ],
            Helper::class_uses_recursive('Gubler\Tests\Collection\SupportTestClassTwo'));
    }

    public function testClassUsesRecursiveAcceptsObject()
    {
        $this->assertSame([
            'Gubler\Tests\Collection\SupportTestTraitTwo' => 'Gubler\Tests\Collection\SupportTestTraitTwo',
            'Gubler\Tests\Collection\SupportTestTraitOne' => 'Gubler\Tests\Collection\SupportTestTraitOne',
        ],
            Helper::class_uses_recursive(new SupportTestClassTwo));
    }

    public function testClassUsesRecursiveReturnParentTraitsFirst()
    {
        $this->assertSame([
            'Gubler\Tests\Collection\SupportTestTraitTwo' => 'Gubler\Tests\Collection\SupportTestTraitTwo',
            'Gubler\Tests\Collection\SupportTestTraitOne' => 'Gubler\Tests\Collection\SupportTestTraitOne',
            'Gubler\Tests\Collection\SupportTestTraitThree' => 'Gubler\Tests\Collection\SupportTestTraitThree',
        ],
            Helper::class_uses_recursive(SupportTestClassThree::class));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testThrow()
    {
        Helper::throw_if(true, new RuntimeException);
    }

    public function testThrowReturnIfNotThrown()
    {
        $this->assertSame('foo', Helper::throw_unless('foo', new RuntimeException));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Test Message
     */
    public function testThrowWithString()
    {
        Helper::throw_if(true, RuntimeException::class, 'Test Message');
    }

    public function testTransform()
    {
        $this->assertEquals(10, Helper::transform(5, function ($value) {
            return $value * 2;
        }));

        $this->assertNull(Helper::transform(null, function () {
            return 10;
        }));
    }

    public function testTransformDefaultWhenBlank()
    {
        $this->assertEquals('baz', Helper::transform(null, function () {
            return 'bar';
        }, 'baz'));

        $this->assertEquals('baz', Helper::transform('', function () {
            return 'bar';
        }, function () {
            return 'baz';
        }));
    }

    public function testWith()
    {
        $this->assertEquals(10, Helper::with(10));

        $this->assertEquals(10, Helper::with(5, function ($five) {
            return $five + 5;
        }));
    }
}

trait SupportTestTraitOne
{
}

trait SupportTestTraitTwo
{
    use SupportTestTraitOne;
}

class SupportTestClassOne
{
    use SupportTestTraitTwo;
}

class SupportTestClassTwo extends SupportTestClassOne
{
}

trait SupportTestTraitThree
{
}

class SupportTestClassThree extends SupportTestClassTwo
{
    use SupportTestTraitThree;
}

class SupportTestArrayAccess implements ArrayAccess
{
    protected $attributes = [];

    public function __construct($attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->attributes);
    }

    public function offsetGet($offset)
    {
        return $this->attributes[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }
}
