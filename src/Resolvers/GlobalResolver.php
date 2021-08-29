<?php
/*
 * LightContainer
 *
 * Copyright (C) Kelvin Mo 2021
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above
 *    copyright notice, this list of conditions and the following
 *    disclaimer in the documentation and/or other materials provided
 *    with the distribution.
 *
 * 3. The name of the author may not be used to endorse or promote
 *    products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE
 * GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
 * IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace LightContainer\Resolvers;

use LightContainer\LightContainerInterface;
use LightContainer\Loader\LoadableInterface;
use LightContainer\Loader\LoaderInterface;

/**
 * A resolver that resolves to a the value of a global variable.
 * 
 * This resolver is useful in non-PHP configuration files, where
 * you need to specify the value of a global variable.
 */
class GlobalResolver implements ResolverInterface, LoadableInterface {
    protected $variable;

    /**
     * Creates a GlobalResolver
     * 
     * @param string $variable the name of the global variable,
     * without the preceding dollar sign
     */
    public function __construct(string $variable) {
        $this->variable = $variable;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(LightContainerInterface $container) {
        return $GLOBALS[$this->variable];
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromLoader($value, string $id = null, LoaderInterface $loader): ResolverInterface {
        return new GlobalResolver($value);
    }
}

?>