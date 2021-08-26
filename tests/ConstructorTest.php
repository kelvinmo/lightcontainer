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
interface ConstructorTestInterfaceNoImpl {}

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

class ConstructorTestNoImplementation {
    public function __construct(ConstructorTestInterfaceNoImpl $a) {
    }
}

class ConstructorTestNoImplementationOptional {
    public $a = 'something';

    public function __construct(?ConstructorTestInterfaceNoImpl $a) {
        $this->a = $a;
    }
}

class ConstructorTestNoImplementationDefault {
    public $a = 'something';

    public function __construct(ConstructorTestInterfaceNoImpl $a = null) {
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
    public $a = 'something';

    public function __construct(?string $a) {
        $this->a = $a;
    }
}

class ConstructorTestOptionalArg {
    public $a;

    public function __construct(string $a = 'something') {
        $this->a = $a;
    }
}

class ConstructorTestRef {
    public $inner;

    public function __construct($inner) {
        $this->inner = $inner;
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
        $this->assertInstanceOf(ConstructorTestClass::class, $obj->a);
    }

    public function testOptionalClassWithNull() {
        $container = new Container();
        $container->set(ConstructorTestOptionalClass::class)->alias(ConstructorTestClass::class, null);
        $obj = $container->get(ConstructorTestOptionalClass::class);
        $this->assertNull($obj->a);
    }

    public function testNullableClass() {
        $container = new Container();
        $container->set(ConstructorTestNullableClass::class)->alias(ConstructorTestClass::class, null);
        $obj = $container->get(ConstructorTestNullableClass::class);
        $this->assertNull($obj->a);
    }

    public function testOptionalInterface() {
        $container = new Container();
        $container->set(ConstructorTestInterfaceA::class, ConstructorTestImplA::class);
        $container->set(ConstructorTestOptionalInterface::class)
            ->alias(ConstructorTestInterfaceA::class, null);
        $obj = $container->get(ConstructorTestOptionalInterface::class);
        $this->assertNull($obj->a);
    }

    public function testNoImplementation() {
        $this->expectException('Psr\\Container\\NotFoundExceptionInterface');
        $container = new Container();
        $obj = $container->get(ConstructorTestNoImplementation::class);
    }

    public function testNoImplementationOptional() {
        $container = new Container();
        $obj = $container->get(ConstructorTestNoImplementationOptional::class);
        $this->assertNull($obj->a);
    }

    public function testNoImplementationDefault() {
        $container = new Container();
        $obj = $container->get(ConstructorTestNoImplementationDefault::class);
        $this->assertNull($obj->a);
    }

    // Constructor arguments
    public function testArgs() {
        $container = new Container();
        $container->set(ConstructorTestArgs::class)->args('example.com', 8080);

        $obj = $container->get(ConstructorTestArgs::class);
        $this->assertInstanceOf(ConstructorTestClass::class, $obj->a);
        $this->assertEquals('example.com', $obj->host);
        $this->assertEquals(8080, $obj->port);
    }

    public function testMultipleArgsCalls() {
        $container = new Container();
        $container->set(ConstructorTestArgs::class)->args('example.com')->args(8080);

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
        $obj = $container->get(ConstructorTestNullArg::class);
        $this->assertNull($obj->a);
    }

    public function testOptionalArg() {
        $container = new Container();
        $obj = $container->get(ConstructorTestOptionalArg::class);
        $this->assertEquals('something', $obj->a);
    }

    public function testRefClass() {
        $container = new Container();
        $container->set(ConstructorTestRef::class)->args(Container::ref(ConstructorTestOptionalArg::class));
        $obj = $container->get(ConstructorTestRef::class);
        $this->assertEquals('something', $obj->inner->a);
    }

    public function testRefNamedInstance() {
        $container = new Container();
        $container->set('@inner', ConstructorTestOptionalArg::class)->args('something else');
        $container->set(ConstructorTestRef::class)->args(Container::ref('@inner'));
        $obj = $container->get(ConstructorTestRef::class);
        $this->assertEquals('something else', $obj->inner->a);
    }
}
?>