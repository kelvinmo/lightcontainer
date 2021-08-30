<?php

namespace LightContainer\Tests;

use LightContainer\Container;
use LightContainer\Resolvers\ConstantResolver;
use LightContainer\Resolvers\GlobalResolver;
use PHPUnit\Framework\TestCase;

/* -------------------------------------------------------------------------
 * Mock
 * ------------------------------------------------------------------------- */
const TEST_CONSTANT = 'test constant';
$global_var = 'global variable initial';

/* -------------------------------------------------------------------------
 * Tests
 * ------------------------------------------------------------------------- */

class MiscResolversTest extends TestCase {
    public function testGlobalResolver() {
        global $global_var;

        $container = new Container();
        $resolver = new GlobalResolver('global_var');

        $container->set('@global', $resolver);
        $a = $container->get('@global');
        $this->assertEquals($global_var, $a);

        $global_var = 'global variable changed';
        $b = $container->get('@global');
        $this->assertEquals($global_var, $b);
    }
}
?>