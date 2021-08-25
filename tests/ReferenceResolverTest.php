<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock
 * ------------------------------------------------------------------------- */
interface AliasTestInterface {}

class AliasTestInterfaceImpl implements AliasTestInterface {}

class AliasTestInterfaceSubclass extends AliasTestInterfaceImpl {}

class NamedInstanceTestClass {}

/* -------------------------------------------------------------------------
 * Tests
 * ------------------------------------------------------------------------- */

class ReferenceResolverTest extends TestCase {
    // global aliases
    public function testInterface() {
        $container = new Container();
        $container->set(AliasTestInterface::class, AliasTestInterfaceImpl::class);
        $obj = $container->get(AliasTestInterface::class);
        $this->assertInstanceOf(AliasTestInterfaceImpl::class, $obj);
    }

    public function testNestedReferences() {
        $container = new Container();
        $container->set(AliasTestInterface::class, AliasTestInterfaceImpl::class);
        $container->set(AliasTestInterfaceImpl::class, AliasTestInterfaceSubclass::class);
        $obj = $container->get(AliasTestInterface::class);
        $this->assertInstanceOf(AliasTestInterfaceSubclass::class, $obj);
    }

    public function testAliasOptions() {
        $container = new Container();
        $container->set(AliasTestInterfaceImpl::class, AliasTestInterfaceSubclass::class)->shared();

        // If we instantiate AliasTestInterfaceSubclass directly,
        // it should not be shared
        $s1 = $container->get(AliasTestInterfaceSubclass::class);
        $s2 = $container->get(AliasTestInterfaceSubclass::class);
        $this->assertNotSame($s1, $s2);

        // However, if we instantiate AliasTestInterfaceImpl,
        // it should be shared
        $i1 = $container->get(AliasTestInterfaceImpl::class);
        $i2 = $container->get(AliasTestInterfaceImpl::class);
        $this->assertInstanceOf(AliasTestInterfaceSubclass::class, $i1);
        $this->assertSame($i1, $i2);
    }

    public function testNestedReferenceOptions() {
        $container = new Container();
        $container->set(AliasTestInterface::class, AliasTestInterfaceImpl::class);
        $container->set(AliasTestInterfaceImpl::class, AliasTestInterfaceSubclass::class)->shared();

        $a1 = $container->get(AliasTestInterface::class);
        $a2 = $container->get(AliasTestInterface::class);
        $this->assertSame($a1, $a2);
    }

    // named instances
    public function testNamedInstance() {
        $container = new Container();
        $container->set('@instance', NamedInstanceTestClass::class);

        $a = $container->get('@instance');
        $b = $container->get('@instance');
        $this->assertSame($a, $b);
    }

    public function testNamedInstanceSetShared() {
        $this->expectException('InvalidArgumentException');

        $container = new Container();
        $container->set('@instance', NamedInstanceTestClass::class)->shared(false);
    }
}
?>