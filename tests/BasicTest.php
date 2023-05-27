<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use LightContainer\LightContainerInterface;
use LightContainer\Attributes\Shared;
use LightContainer\Attributes\Propagate;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock classes
 * ------------------------------------------------------------------------- */
class BasicTestClass {}

#[Shared]
class BasicTestSharedClass {}

#[Shared(false)]
class BasicTestNotSharedClass {}

#[Propagate(false)]
class BasicTestPropagateClass {}

class BasicTestPropagateSubclass extends BasicTestPropagateClass {}

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

    public function testSharedAttribute() {
        if (!method_exists(\ReflectionClass::class, 'getAttributes')) {
            $this->markTestSkipped('Attributes not supported in this version of PHP');
            return;
        }

        $container = new Container();
        $container->set(BasicTestSharedClass::class);
        $container->set(BasicTestNotSharedClass::class);
        
        $a = $container->get(BasicTestSharedClass::class);
        $b = $container->get(BasicTestSharedClass::class);
        $this->assertSame($a, $b);

        $c = $container->get(BasicTestNotSharedClass::class);
        $d = $container->get(BasicTestNotSharedClass::class);
        $this->assertNotSame($c, $d);
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

    public function testPropagateAttribute() {
        if (!method_exists(\ReflectionClass::class, 'getAttributes')) {
            $this->markTestSkipped('Attributes not supported in this version of PHP');
            return;
        }

        $container = new Container();
        $container->set(BasicTestPropagateClass::class)->shared();

        // 'shared' option should not be propagated to subclasses
        $a = $container->get(BasicTestPropagateSubclass::class);
        $b = $container->get(BasicTestPropagateSubclass::class);
        $this->assertNotSame($a, $b);
    }

    // wildcard resolver
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
        $container->set('@null', Container::value(null));
        $container->set('@bool', false);
        $container->set('@port', 8080);
        $container->set('@db', Container::value('example.com'));
        $container->set('@paramArray', [1, 2, 3]);
        $container->set('@paramHash', ['one' => 1, 'two' => 2, 'three' => 3]);
        $container->set('@obj', $obj);

        $paramHash = $container->get('@paramHash');

        $this->assertNull($container->get('@null'));
        $this->assertEquals(false, $container->get('@bool'));
        $this->assertEquals(8080, $container->get('@port'));
        $this->assertEquals('example.com', $container->get('@db'));
        $this->assertEquals('123', implode('', $container->get('@paramArray')));
        $this->assertEquals(1, $paramHash['one']);
        $this->assertEquals(2, $paramHash['two']);
        $this->assertEquals(3, $paramHash['three']);
        $this->assertSame($obj, $container->get('@obj'));
    }

    // self
    public function testSelfResolver() {
        $container = new Container();
        $this->assertSame($container, $container->get(LightContainerInterface::class));
    }
}

?>