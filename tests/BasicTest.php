<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock classes
 * ------------------------------------------------------------------------- */
class BasicTestClass {}

interface BasicTestInterface {}

class BasicTestInterfaceImpl implements BasicTestInterface {}

class BasicTestA {
    public $b;

    public function __construct(BasicTestB $b) {
        $this->b = $b;
    }
}

class BasicTestB {
    public $c;

    public function __construct(BasicTestClass $c) {
        $this->c = $c;
    }
}

class BasicTestCustom {
    public $inner;
}

class BasicTestCycleA {
    public $b;

    public function __construct(BasicTestCycleB $b) {
        $this->b = $b;
    }
}

class BasicTestCycleB {
    public $a;

    public function __construct(BasicTestCycleA $a) {
        $this->a = $a;
    }
}

/* -------------------------------------------------------------------------
 * Tests
 * ------------------------------------------------------------------------- */
class BasicTest extends TestCase {
    public function testNonExistentClass() {
        $this->expectException('Psr\\Container\\NotFoundExceptionInterface');

        $container = new Container();
        $container->get('NonExistentClass');
    }

    public function testUnregisteredClass() {
        $container = new Container();
        $obj = $container->get(BasicTestClass::class);
        $this->assertInstanceOf(BasicTestClass::class, $obj);
    }

    public function testInterface() {
        $container = new Container();
        $container->set(BasicTestInterface::class, BasicTestInterfaceImpl::class);
        $obj = $container->get(BasicTestInterface::class);
        $this->assertInstanceOf(BasicTestInterfaceImpl::class, $obj);
    }

    public function testDependencyGraph() {
        $container = new Container();
        $a = $container->get(BasicTestA::class);

        $this->assertInstanceOf(BasicTestB::class, $a->b);
        $this->assertInstanceOf(BasicTestClass::class, $a->b->c);
    }

    public function testShared() {
        $container = new Container();
        $container->set(BasicTestClass::class)->shared();
        
        $a = $container->get(BasicTestClass::class);
        $b = $container->get(BasicTestClass::class);
        $this->assertSame($a, $b);
    }

    public function testSharedDependency() {
        $container = new Container();
        $container->set(BasicTestB::class)->shared();
        
        $a1 = $container->get(BasicTestA::class);
        $a2 = $container->get(BasicTestA::class);
        $this->assertNotSame($a1, $a2);
        $this->assertSame($a1->b, $a2->b);
    }

    public function testCycles() {
        $container = new Container();
        $container->set(BasicTestCycleB::class)->shared();

        $a = $container->get(BasicTestCycleA::class);

        $this->assertInstanceOf(BasicTestCycleB::class, $a->b);
        $this->assertInstanceOf(BasicTestCycleA::class, $a->b->a);

        $this->assertSame($a->b, $a->b->a->b);
    }

    // inheritance and propagation
    public function testInheritance() {
        $container = new Container();
        $container->set('*')->shared();
        $container->set(BasicTestClass::class)->shared(false);

        $a = $container->get(BasicTestClass::class);
        $b = $container->get(BasicTestClass::class);
        $this->assertNotSame($a, $b);
    }

    public function testPropagate() {
        $container = new Container();
        $container->set('*')->shared()->propagate(false);

        $a = $container->get(BasicTestClass::class);
        $b = $container->get(BasicTestClass::class);
        $this->assertNotSame($a, $b);
    }

    // named instances
    public function testNamedInstance() {
        $container = new Container();
        $container->set('@instance', BasicTestClass::class);

        $a = $container->get('@instance');
        $b = $container->get('@instance');
        $this->assertSame($a, $b);
    }

    // custom instantiation
    public function testCustomInstantiation() {
        $container = new Container();
        $container->set(BasicTestCustom::class, function($container) {
            $custom = new BasicTestCustom();
            $custom->inner = $container->get(BasicTestClass::class);
            return $custom;
        });

        $a = $container->get(BasicTestCustom::class);
        $this->assertInstanceOf(BasicTestCustom::class, $a);
        $this->assertInstanceOf(BasicTestClass::class, $a->inner);
    }

    // values
    public function testValues() {
        $obj = new BasicTestClass();

        $container = new Container();
        $container->set('@port', 8080);
        $container->set('@db', Container::value('example.com'));
        $container->set('@paramArray', [1, 2, 3]);
        $container->set('@paramHash', ['one' => 1, 'two' => 2, 'three' => 3]);
        $container->set('@obj', $obj);

        $paramHash = $container->get('@paramHash');

        $this->assertEquals(8080, $container->get('@port'));
        $this->assertEquals('example.com', $container->get('@db'));
        $this->assertEquals('123', implode('', $container->get('@paramArray')));
        $this->assertEquals(1, $paramHash['one']);
        $this->assertEquals(2, $paramHash['two']);
        $this->assertEquals(3, $paramHash['three']);
        $this->assertSame($obj, $container->get('@obj'));
    }

    // wildcard rules
    public function testWildcardResolverInstantiation() {
        $this->expectException('Psr\\Container\\NotFoundExceptionInterface');
        $container = new Container();
        $container->get('*');
    }

    public function testWildcardOptions() {
        $container = new Container();
        $container->set('*')->shared();
        
        $a = $container->get(BasicTestClass::class);
        $b = $container->get(BasicTestClass::class);
        $this->assertSame($a, $b);
    }
}

?>