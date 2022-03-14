<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock
 * ------------------------------------------------------------------------- */
interface PopulateTestInterfaceA {}

interface PopulateTestInterfaceB {}

interface PopulateTestInterfaceC extends PopulateTestInterfaceA {}

class PopulateTestAB implements PopulateTestInterfaceA, PopulateTestInterfaceB {}

class PopulateTestC implements PopulateTestInterfaceC {}

/* -------------------------------------------------------------------------
 * Tests
 * ------------------------------------------------------------------------- */

class PopulateTest extends TestCase {
    public function testPopulate() {
        $container = new Container();
        $container->populate(PopulateTestAB::class);
        
        $a = $container->get(PopulateTestInterfaceA::class);
        $this->assertInstanceOf(PopulateTestAB::class, $a);

        $b = $container->get(PopulateTestInterfaceB::class);
        $this->assertInstanceOf(PopulateTestAB::class, $b);
    }

    public function testPopulateExclude() {
        $this->expectException('LightContainer\\NotFoundException');

        $container = new Container();
        $container->populate(PopulateTestAB::class, [ PopulateTestInterfaceB::class ]);
        
        $b = $container->get(PopulateTestInterfaceB::class);
    }

    public function testSubInterfaces() {
        $container = new Container();
        $container->populate(PopulateTestC::class);
        
        $a = $container->get(PopulateTestInterfaceA::class);
        $this->assertInstanceOf(PopulateTestC::class, $a);
    }
}
?>