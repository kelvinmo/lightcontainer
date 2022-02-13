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
 * A resolver that resolves to a specified value.
 * 
 * This resolver is useful for storing fixed values (such as
 * configuration information) in the container.  Internally, this resolver
 * is also used by BaseInstanceResolver to store argument values that
 * can be passed on during constructor or setter injection.
 */
class ValueResolver implements ResolverInterface, TypeCheckInterface, LoadableInterface {
    /** @var array<string, ?ValueResolver> */
    private static $shared = [
        'null' => null,
        'true' => null,
        'false' => null
    ];

    /** @var mixed */
    protected $value;

    /**
     * Creates a ValueResolver with the specified value
     * 
     * @param mixed $value the value
     */
    protected function __construct($value) {
        $this->value = $value;
    }

    /**
     * Creates a ValueResolver with the specified value.
     * 
     * The resolvers for the values `true`, `false` and `null`
     * are shared singletons.  A call to this method with any
     * of these values will return the same resolver
     * 
     * @param mixed $value the value
     * @return ValueResolver the value resolver
     */
    public static function create($value): ValueResolver {
        if ($value == null) {
            return self::nullResolver();
        } elseif (is_bool($value)) {
            if ($value == true) {
                return self::getSharedResolver('true', true);
            } else {
                return self::getSharedResolver('false', false);
            }
        } else {
            return new ValueResolver($value);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    protected static function getSharedResolver(string $key, $value): ValueResolver {
        if (self::$shared[$key] == null) {
            self::$shared[$key] = new ValueResolver($value);
        }
 
        return self::$shared[$key];
    }

    /**
     * Returns a value resolver that resolves to a null.
     * 
     * This is a convenience function for `ValueResolver::create(null)`
     * 
     * @return ValueResolver
     */
    public static function nullResolver(): ValueResolver {
        return self::getSharedResolver('null', null);
    }

    /**
     * {@inheritdoc}
     */
    public function checkType($expected_type, $allow_null = true): bool {
        if ($allow_null && ($this->value == null)) return true;
        switch ($expected_type) {
            case '*';
            case 'mixed':
                return true;
            case 'array':
                return is_array($this->value);
            case 'bool':
                return is_bool($this->value);
            case 'callable':
                return is_callable($this->value);
            case 'float':
                return is_float($this->value);
            case 'int':
                return is_int($this->value);
            case 'iterable':
                return is_iterable($this->value);
            case 'resource':
                return is_resource($this->value);
            case 'string':
                return is_string($this->value);
            default:
                return is_a($this->value, $expected_type);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromLoader($value, ?string $id, LoaderInterface $loader): ResolverInterface {
        return self::create($value);
    }
    /**
     * {@inheritdoc}
     */
    public function resolve(LightContainerInterface $container) {
        return $this->value;
    }
}

?>