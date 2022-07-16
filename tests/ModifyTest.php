<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use LightContainer\LightContainerInterface;
use LightContainer\InstanceModifierInterface;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock
 * ------------------------------------------------------------------------- */
interface ModifyTestInterface {}

class ModifyTestModifier implements InstanceModifierInterface {
    public function modify(object $obj, LightContainerInterface $container): object {
        $obj->modified = true;
        return $obj;
    }
}

class ModifyTestClass implements ModifyTestInterface {
    public $modified;

    public function __construct() {
        $this->modified = false;
    }
}

/* -------------------------------------------------------------------------
 * Tests
 * ------------------------------------------------------------------------- */
class ModifyTest extends TestCase {
    public function testModifyBasic() {
        $container = new Container();
        $modifier = new ModifyTestModifier();
        $container->set(ModifyTestClass::class)->modify($modifier);
        $obj = $container->get(ModifyTestClass::class);
        $this->assertEquals($obj->modified, true);
    }

    public function testModifyReference() {
        $container = new Container();
        $modifier = new ModifyTestModifier();
        $container->set(ModifyTestInterface::class, ModifyTestClass::class)->modify($modifier);

        // If we resolve the class directly, it should not be passed to the modifier
        $obj = $container->get(ModifyTestClass::class);
        $this->assertEquals($obj->modified, false);

        // If we resolve the interface, it should be passed to the modifier
        $obj2 = $container->get(ModifyTestInterface::class);
        $this->assertEquals($obj2->modified, true);
    }
}
?>