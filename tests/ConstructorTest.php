<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock classes
 * ------------------------------------------------------------------------- */
class ConstructorTestClass {}

interface ConstructorTestInterfaceA {}
interface ConstructorTestInterfaceB {}

class ConstructorTestImplA implements ConstructorTestInterfaceA {}
class ConstructorTestImplA2 implements ConstructorTestInterfaceA {}
class ConstructorTestImplB implements ConstructorTestInterfaceB {}

class ConstructorTestAlias {
    public $a = null;

    public function __construct(ConstructorTestInterfaceA $a) {
        $this->a = $a;
    }
}

class ConstructorTestMultipleAliases {
    public $a = null;
    public $b = null;

    public function __construct(ConstructorTestInterfaceA $a, ConstructorTestInterfaceB $b) {
        $this->a = $a;
        $this->b = $b;
    }
}

class ConstructorTestOptionalClass {
    public $a;

    public function __construct(ConstructorTestClass $a = null) {
        $this->a = $a;
    }
}

class ConstructorTestNullableClass {
    public $a;

    public function __construct(?ConstructorTestClass $a) {
        $this->a = $a;
    }
}

class ConstructorTestOptionalInterface {
    public $a = null;

    public function __construct(ConstructorTestInterfaceA $a = null) {
        $this->a = $a;
    }
}

class ConstructorTestArgs {
    public $a = null;
    public $host = null;
    public $port = null;

    public function __construct(ConstructorTestClass $a, string $host, int $port = 80) {
        $this->a = $a;
        $this->host = $host;
        $this->port = $port;
    }
}

class ConstructorTestArgOrder {
    public $a = null;
    public $host = null;
    public $b = null;
    public $port = null;

    public function __construct(ConstructorTestClass $a, string $host, ConstructorTestImplB $b, int $port) {
        $this->a = $a;
        $this->host = $host;
        $this->b = $b;
        $this->port = $port;
    }
}

class ConstructorTestNested {
    public $inner;

    public function __construct(ConstructorTestArgs $inner) {
        $this->inner = $inner;
    }
}

class ConstructorTestNullArg {
    public $a;

    public function __construct(string $a = null) {
        $this->a = $a;
    }
}

/* -------------------------------------------------------------------------
 * Tests
 * ------------------------------------------------------------------------- */

class ConstructorTest extends TestCase {
    public function testAlias() {
        $container = new Container();
        $container->set(ConstructorTestInterfaceA::class, ConstructorTestImplA::class);
        $container->set(ConstructorTestAlias::class)->alias(ConstructorTestInterfaceA::class, ConstructorTestImplA2::class);
        
        $a = $container->get(ConstructorTestAlias::class);
        $this->assertInstanceOf(ConstructorTestImplA2::class, $a->a);

        $b = $container->get(ConstructorTestInterfaceA::class);
        $this->assertInstanceOf(ConstructorTestImplA::class, $b);
    }

    public function testMultipleAliases() {
        $container = new Container();
        $container->set(ConstructorTestMultipleAliases::class)->alias([
            ConstructorTestInterfaceA::class => ConstructorTestImplA::class,
            ConstructorTestInterfaceB::class => ConstructorTestImplB::class
        ]);
        
        $obj = $container->get(ConstructorTestMultipleAliases::class);
        $this->assertInstanceOf(ConstructorTestImplA::class, $obj->a);
        $this->assertInstanceOf(ConstructorTestImplB::class, $obj->b);
    }

    public function testOptionalClass() {
        $container = new Container();
        $obj = $container->get(ConstructorTestOptionalClass::class);
        $this->assertNull($obj->a);
    }

    public function testNullableClass() {
        $container = new Container();
        $obj = $container->get(ConstructorTestNullableClass::class);
        $this->assertNull($obj->a);
    }

    public function testOptionalInterface() {
        $container = new Container();
        $container->set(ConstructorTestInterfaceA::class, ConstructorTestImplA::class);
        $obj = $container->get(ConstructorTestOptionalInterface::class);
        $this->assertNull($obj->a);
    }

    public function testArgs() {
        $container = new Container();
        $container->set(ConstructorTestArgs::class)->args('example.com', 8080);

        $obj = $container->get(ConstructorTestArgs::class);
        $this->assertInstanceOf(ConstructorTestClass::class, $obj->a);
        $this->assertEquals('example.com', $obj->host);
        $this->assertEquals(8080, $obj->port);
    }

    public function testInternalClasses() {
        $container = new Container();
        $container->set(\ReflectionClass::class)->args(ConstructorTestClass::class);
        $obj = $container->get(\ReflectionClass::class);
        $this->assertInstanceOf(\ReflectionClass::class, $obj);
    }

    public function testOptionalArgs() {
        $container = new Container();
        $container->set(ConstructorTestArgs::class)->args('example.com');

        $obj = $container->get(ConstructorTestArgs::class);
        $this->assertInstanceOf(ConstructorTestClass::class, $obj->a);
        $this->assertEquals('example.com', $obj->host);
        $this->assertEquals(80, $obj->port);
    }

    public function testMissingArgs() {
        $this->expectException('Psr\\Container\\ContainerExceptionInterface');

        $container = new Container();
        $obj = $container->get(ConstructorTestArgs::class);
    }

    public function testIncorrectArgType() {
        $this->expectException('Psr\\Container\\ContainerExceptionInterface');

        $container = new Container();
        $container->set(ConstructorTestArgs::class)->args(8080);
        $obj = $container->get(ConstructorTestArgs::class);
    }

    public function testArgOrder() {
        $container = new Container();
        $container->set(ConstructorTestArgOrder::class)->args('example.com', 8080);

        $obj = $container->get(ConstructorTestArgOrder::class);
        $this->assertInstanceOf(ConstructorTestClass::class, $obj->a);
        $this->assertInstanceOf(ConstructorTestImplB::class, $obj->b);
        $this->assertEquals('example.com', $obj->host);
        $this->assertEquals(8080, $obj->port);
    }

    public function testNestedArg() {
        $container = new Container();
        $container->set(ConstructorTestArgs::class)->args('example.com', 8080);

        $obj = $container->get(ConstructorTestNested::class);
        $this->assertEquals('example.com', $obj->inner->host);
    }

    public function testNullArg() {
        $container = new Container();
        $container->set(ConstructorTestNullArg::class)->args(null);

        $obj = $container->get(ConstructorTestNullArg::class);
        $this->assertNull($obj->a);
    }
}
?>