<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock classes
 * ------------------------------------------------------------------------- */
class CallTestClass {
    public $called = false;

    public function call() {
        $this->called = true;
    }
}

interface CallTestInterfaceA {}

class CallTestImplA implements CallTestInterfaceA {}
class CallTestImplA2 implements CallTestInterfaceA {}

class CallTestAlias {
    public $a = null;

    public function setA(CallTestInterfaceA $a) {
        $this->a = $a;
    }
}

class CallTestArgs {
    public $a;
    public $b;

    public function setAB($a, $b) {
        $this->a = $a;
        $this->b = $b;
    }
}

/* -------------------------------------------------------------------------
 * Tests
 * ------------------------------------------------------------------------- */

class CallTest extends TestCase {
    public function testCall() {
        $container = new Container();
        $container->set(CallTestClass::class)->call('call');
        $obj = $container->get(CallTestClass::class);

        $this->assertEquals(true, $obj->called);
    }

    public function testCallAlias() {
        $container = new Container();
        $container->set(CallTestInterfaceA::class, CallTestImplA::class);
        $container->set(CallTestAlias::class)
            ->alias(CallTestInterfaceA::class, CallTestImplA2::class)
            ->call('setA');
        
        $a = $container->get(CallTestAlias::class);
        $this->assertInstanceOf(CallTestImplA2::class, $a->a);

        $b = $container->get(CallTestInterfaceA::class);
        $this->assertInstanceOf(CallTestImplA::class, $b);
    }

    public function testCallArgs() {
        $container = new Container();
        $container->set(CallTestArgs::class)->call('setAB', 'foo', 'bar');
        $obj = $container->get(CallTestArgs::class);

        $this->assertEquals('foo', $obj->a);
        $this->assertEquals('bar', $obj->b);
    }
}
?>