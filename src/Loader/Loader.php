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

namespace LightContainer\Loader;

use LightContainer\Container;
use LightContainer\Resolvers\ResolverInterface;
use LightContainer\Resolvers\BaseInstanceResolver;
use LightContainer\Resolvers\ConstantResolver;
use LightContainer\Resolvers\GlobalResolver;
use LightContainer\Resolvers\ClassResolver;
use LightContainer\Resolvers\ReferenceResolver;
use LightContainer\Resolvers\ValueResolver;

/**
 * The standard LightContainer configuration loader.
 * 
 * This configuration loader loads configuration information from a
 * plain PHP array.  This provides flexibility for the user to determine
 * how the PHP array is populated, e.g. loading from a JSON or YAML
 * configuration file.
 */
class Loader implements LoaderInterface {
    // Canonical encoding
    const TYPE = '_type';
    const ARGS = '_args';

    const REF = '_ref';

    // Compact encoding
    const LITERAL_VALUE = '_value';
    const CONSTANT = '_const';
    const GLOBAL_VARIABLE = '_global';

    /**
     * {@inheritdoc}
     */
    public function load($value, string $id = null, int $context = self::NO_CONTEXT): ResolverInterface {
        $resolver_class_name = null;
        $args = [];

        if (is_array($value)) {
            /* If $value is an array, depending the presence of various
             * reserved keys, it can be one of the following
             * 
             * - _type: a canonical form resolver declaration
             * - _ref: a reference resolver
             * - _value: a value resolver
             * - _const: a value resolver for a PHP constant
             * - _global: a resolver for a PHP global variable
             * 
             * Otherwise, if it is a sequential array (i.e. with sequential numeric)
             * keys, then treat it as a value resolver with an array literal.
             * 
             * Otherwise treat it as a wildcard or class resolver.
             */

            if (isset($value[self::TYPE])) {
                // _type
                $resolver_class_name = $this->getClassName($value[self::TYPE]);

                if (isset($value[self::ARGS])) {
                    $args = $value[self::ARGS];
                } else {
                    $args = $value;
                }
            } elseif (isset($value[self::REF])) {
                // _ref
                $resolver_class_name = ReferenceResolver::class;
                $args = array_merge($value, ['target' => $value[self::REF]]);
            } elseif (isset($value[self::LITERAL_VALUE])) {
                // _value
                $resolver_class_name = ValueResolver::class;
                $args = $value[self::LITERAL_VALUE];
            } elseif (isset($value[self::CONSTANT])) {
                // _constant
                $resolver_class_name = ValueResolver::class;
                $args = constant($value[self::CONSTANT]);
            } elseif (isset($value[self::GLOBAL_VARIABLE])) {
                // _global
                $resolver_class_name = GlobalResolver::class;
                $args = $value[self::GLOBAL_VARIABLE];
            } elseif (array_keys($value) === range(0, count($value) - 1)) {
                // sequential array - treat as regular array
                $resolver_class_name = ValueResolver::class;
                $args = $value;
            } elseif ($context == self::REFERENCE_CONTEXT) {
                // Assume class resolver
                $resolver_class_name = (!is_null($id) && ($id == '*')) ? BaseInstanceResolver::class : ClassResolver::class;
                $args = (empty($value)) ? null : $value;
            }
        } elseif (is_string($value)) {
            /*
             * If $value is a string, then it can be a reference to a value,
             * or a literal string, depending on the specified context.
             */
            switch ($context) {
                case self::REFERENCE_CONTEXT:
                    $resolver_class_name = ReferenceResolver::class;
                    $args = ['target' => $value];
                    break;
                case self::LITERAL_CONTEXT:
                    $resolver_class_name = ValueResolver::class;
                    $args = $value;
                    break;
                default:
                    throw new LoaderException('Unexpected string: ' . $value);
            }
        } else {
            $resolver_class_name = ValueResolver::class;
            $args = $value;
        }

        if ($resolver_class_name == null) throw new LoaderException('Cannot parse value');
        if (!is_a($resolver_class_name, LoadableInterface::class, true))
            throw new LoaderException('Resolver does not implement LoadableInterface: ' . $resolver_class_name);

        if (($id != null) && (is_a($resolver_class_name, ReferenceResolver::class, true)) && !Container::isValidTypeName($id)) {
            $args['named'] = true;
        }

        return $resolver_class_name::createFromLoader($args, $id, $this);
    }

    /**
     * Parses a class name specified by `#type` in the configuration
     * array.
     * 
     * The class name is parsed based on the following special rules:
     * 
     * - Classes within the `LightContainer\Resolvers` namespace
     *   do not need to be prefixed by the namespace
     * - Classes which belongs to the root namespace are required
     *   to be prefixed by a single backslash (`\`)
     * 
     * @param string $value the class name to parse
     * @return string a fully qualified class name
     */
    protected function getClassName(string $value): string {
        if (strpos($value, '\\') === false) {
            return 'LightContainer\\Resolvers\\' . $value;
        }
        return ltrim($value, '\\');
    }
}

?>