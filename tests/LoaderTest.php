<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use LightContainer\Loader\LoaderException;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock classes
 * ------------------------------------------------------------------------- */
$loader_test_global = 'This is a global variable';

const LOADER_TEST_CONST = 'This is a constant';

class LoaderTestBasicClass {}

class LoaderTestClassWithOptions {}

interface LoaderTestInterface {}

interface LoaderTestInterfaceWithOptions {}

class LoaderTestConstructorArgs {
    public $inner;

    public function __construct($inner) {
        $this->inner = $inner;
    }
}

/* -------------------------------------------------------------------------
 * Tests
 * ------------------------------------------------------------------------- */

class LoaderTest extends TestCase {


    public function testLoad() {
        global $loader_test_global;

        $config = [
            // Classes
            'LightContainer\\Tests\\LoaderTestBasicClass' => [],
            'LightContainer\\Tests\\LoaderTestClassWithOptions' => [
                'shared' => true
            ],

            // Global aliases
            'LightContainer\\Tests\\LoaderTestInterface' => 'LightContainer\\Tests\\LoaderTestBasicClass',
            'LightContainer\\Tests\\LoaderTestInterfaceWithOptions' => [
                '_ref' => 'LightContainer\\Tests\\LoaderTestBasicClass',
                'shared' => true
            ],

            // Named instances
            '@named_instance_1' => [
                '_ref' => 'LightContainer\\Tests\\LoaderTestConstructorArgs',
                'args' => [ 1 ]
            ],

            '@named_instance_2' => [
                '_ref' => 'LightContainer\\Tests\\LoaderTestConstructorArgs',
                'args' => [ 2 ]
            ],

            // Values
            '@null_value' => null,
            '@bool_value' => false,

            // Values - integer
            '@int_value' => 1,

            // Values - string
            '@string_value' => ['_value' => 'hello'],

            // Values - arrays
            '@array_value' => [1, 2, 3, 4, 'five'],
            //'@assoc_value' => ['_value' => ['one' => 1, 'two' => 2]],
            '@assoc_value' => ['_type' => 'ValueResolver', 'one' => 1, 'two' => 2],

            // Constants and globals
            '@const_value' => ['_const' => 'LightContainer\Tests\LOADER_TEST_CONST'],
            '@global_value' => ['_global' => 'loader_test_global'],
        ];

        $container = new Container();
        $container->load($config);

        $this->assertInstanceOf('LightContainer\\Resolvers\\ClassResolver', $container->getResolver('LightContainer\\Tests\\LoaderTestBasicClass'));

        $o1 = $container->get('LightContainer\\Tests\\LoaderTestClassWithOptions');
        $o2 = $container->get('LightContainer\\Tests\\LoaderTestClassWithOptions');
        $this->assertSame($o1, $o2);

        $this->assertInstanceOf('LightContainer\\Tests\\LoaderTestBasicClass', $container->get('LightContainer\\Tests\\LoaderTestInterface'));

        $i1 = $container->get('LightContainer\\Tests\\LoaderTestInterfaceWithOptions');
        $i2 = $container->get('LightContainer\\Tests\\LoaderTestInterfaceWithOptions');
        $this->assertSame($i1, $i2);

        $n1 = $container->get('@named_instance_1');
        $this->assertEquals(1, $n1->inner);

        $n2 = $container->get('@named_instance_2');
        $this->assertEquals(2, $n2->inner);

        $this->assertNull($container->get('@null_value'));
        $this->assertEquals(false, $container->get('@bool_value'));
        $this->assertEquals(1, $container->get('@int_value'));
        $this->assertEquals('hello', $container->get('@string_value'));

        $array_value = $container->get('@array_value');
        $this->assertEquals('five', $array_value[4]);

        $assoc_value = $container->get('@assoc_value');
        $this->assertEquals(2, $assoc_value['two']);

        $this->assertEquals(LOADER_TEST_CONST, $container->get('@const_value'));
        $this->assertEquals($loader_test_global, $container->get('@global_value'));
    }
}
?>