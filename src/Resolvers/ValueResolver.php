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

/**
 * A resolver that resolves to a specified value.
 * 
 * This resolver is useful for storing fixed values (such as
 * configuration information) in the container.
 */
class ValueResolver implements ResolverInterface {
    private static $nullResolver = null;

    protected $value;

    /**
     * Creates a ValueResolver with the specified value
     * 
     * @param mixed $value the value to return
     */
    public function __construct($value) {
        $this->value = $value;
    }

    /**
     * Checks whether the value return this resolver will
     * match a particular type.
     * 
     * @param string $expected_type the expected type
     * @return bool true if the type matches
     * @see https://www.php.net/manual/en/language.types.declarations.php
     */
    public function checkType($expected_type) {
        switch ($expected_type) {
            case '*';
            case 'mixed':
                return true;
                break;
            case 'array':
                return is_array($this->value);
                break;
            case 'bool':
                return is_bool($this->value);
                break;
            case 'callable':
                return is_callable($this->value);
                break;
            case 'float':
                return is_float($this->value);
                break;
            case 'int':
                return is_int($this->value);
                break;
            case 'iterable':
                return is_iterable($this->value);
                break;
            case 'resource':
                return is_resource($this->value);
                break;
            case 'string':
                return is_string($this->value);
                break;
            default:
                return is_a($this->value, $expected_type);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(LightContainerInterface $container) {
        return $this->value;
    }

    /**
     * Returns a value resolver that resolves to a null.
     * 
     * @return ValueResolver
     */
    public static function nullResolver(): ValueResolver {
        if (self::$nullResolver == null) {
            self::$nullResolver = new ValueResolver(null);
        }
 
        return self::$nullResolver;
    }
}

?>