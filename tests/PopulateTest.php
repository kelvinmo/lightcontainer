<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use LightContainer\ServiceInterface;
use LightContainer\Attributes\Service;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock
 * ------------------------------------------------------------------------- */
interface PopulateTestInterfaceA extends ServiceInterface {}

interface PopulateTestInterfaceB extends ServiceInterface {}

interface PopulateTestInterfaceC extends PopulateTestInterfaceA {}

#[Service]
interface PopulateTestInterfaceD {}

#[Service]
interface PopulateTestInterfaceE {}

interface PopulateTestInterfaceF extends PopulateTestInterfaceD {}

interface PopulateTestNonServiceInterface {}

class PopulateTestAB implements PopulateTestInterfaceA, PopulateTestInterfaceB {}

class PopulateTestC implements PopulateTestInterfaceC {}

class PopulateTestDE implements PopulateTestInterfaceD, PopulateTestInterfaceE {}

class PopulateTestF implements PopulateTestInterfaceF {}

class PopulateTestNonService implements PopulateTestInterfaceA, PopulateTestNonServiceInterface {}

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

    public function testPopulateAttribute() {
        if (!method_exists(\ReflectionClass::class, 'getAttributes')) {
            $this->markTestSkipped('Attributes not supported in this version of PHP');
            return;
        }
        
        $container = new Container();
        $container->populate(PopulateTestDE::class);
        
        $d = $container->get(PopulateTestInterfaceD::class);
        $this->assertInstanceOf(PopulateTestDE::class, $d);

        $e = $container->get(PopulateTestInterfaceE::class);
        $this->assertInstanceOf(PopulateTestDE::class, $e);
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

    public function testPopulateNonService() {
        $this->expectException('LightContainer\\NotFoundException');

        $container = new Container();
        $container->populate(PopulateTestNonService::class);
        
        // PopulateTestNonService should not have been registered by populate()
        $non_service = $container->get(PopulateTestNonServiceInterface::class);
    }
}
?>