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
use LightContainer\ContainerException;

/**
 * A resolver that instantiates a class.
 */
class ClassResolver extends BaseInstanceResolver implements AutowireInterface, TypeCheckInterface {
    /**
     * The name of the class to create
     * 
     * @var string
     */
    protected $class_name;

    /**
     * Whether this class resolver is created via autowiring
     * 
     * @var bool
     */
    protected $autowired = false;

    protected $cache = [
        'tree' => [],
        'interfaces' => [],
        'constructor' => null,   // ReflectionMethod for the constructor
        'params' => [],
        'resolvers' => [],
        'is_internal' => false   // Whether the class is defined by C code or PHP code
    ];

    /**
     * Creates a class resolver
     * 
     * @param string $class_name the name of the class
     * @param array $options instantiation options
     */
    public function __construct(string $class_name, $options = null) {
        parent::__construct($options);

        if (!class_exists($class_name)) {
            throw new \InvalidArgumentException('ClassResolver can only be used for concrete classes (not interfaces or traits)');
        }
        $this->class_name = $class_name;

        // Cache class reflection information
        $refl = new \ReflectionClass($this->class_name);

        $constructor = $refl->getConstructor();
        if (($constructor != null) && !$constructor->isPublic()) {
            throw new \InvalidArgumentException('Cannot create resolver for class with a non-public constructor');
        }
        $this->cache['params']['__construct'] = ($constructor != null) ? $this->buildMethodParamsCache($constructor) : [];
        $this->cache['constructor'] = $constructor;

        $this->cache['interfaces'] = $refl->getInterfaceNames();
        $this->cache['is_internal'] = $refl->isInternal();

        while ($parent = $refl->getParentClass()) {
            $this->cache['tree'][] = $parent->getName();
            $refl = $parent;
        }

        // Add wildcard
        $this->cache['tree'][] = '*';
    }

    /**
     * {@inheritdoc}
     */
    public function alias(...$args) {
        parent::alias(...$args);

        // Clear the resolver cache
        $this->cache['resolvers'] = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function call(string $method, ...$args) {
        if (!method_exists($this->class_name, $method)) {
            throw new \InvalidArgumentException('Method does not exist: ' . $method);
        }
        parent::call($method, ...$args);

        if (!isset($this->cache['params'][$method])) {
            $refl = new \ReflectionMethod($this->class_name, $method);
            $this->cache['params'][$method] = $this->buildMethodParamsCache($refl);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isAutowired(): bool {
        return $this->autowired;
    }

    /**
     * {@inheritdoc}
     */
    public function setAutowired(bool $autowired): AutowireInterface {
        $this->autowired = $autowired;
        return $this;
    }


    /**
     * {@inheritdoc}
     */
    public function checkType($expected_type, $allow_null = true): bool {
        return is_a($this->class_name, $expected_type);
    }

    /**
     * {@inheritdoc}
     */
    protected function setOptions(array $options) {
        parent::setOptions($options);
        $this->rebuildCache($options);
    }

    /**
     * Flush and rebuild cache based on new instantiation options
     * 
     * @param array $options the new instantiation options
     */
    protected function rebuildCache(array $options) {
        if (isset($options['alias'])) {
            $this->cache['resolvers'] = [];
        }
        if (isset($options['call'])) {
            foreach ($options['call'] as $call) {
                $method = $call['method'];
                if (!isset($this->cache['params'][$method])) {
                    $refl = new \ReflectionMethod($this->class_name, $method);
                    $this->cache['params'][$method] = $this->buildMethodParamsCache($refl);
                }
            }
        }
    }

    /**
     * Determine whether the same instance of the class will be returned for
     * all calls to this resolver.
     * 
     * If this resolver is autowired, this method calls
     * {@link buildOptionsFromParents()} to get the instantiation
     *  options array from the parents, then returns the value of the
     * `shared` option.
     * 
     * @return bool true if shared
     */
    public function buildIsShared(LightContainerInterface $container) {
        if (!$this->isAutowired()) return $this->options['shared'];

        $options = $this->buildOptionsFromParents($container);
        return $options['shared'];
    }

    /**
     * Builds the instantiation options array from resolvers for the parents
     * of this class.
     * 
     * If this resolver is *not* autowired, this returns the instantiation
     * options set for this resolver
     * 
     * @param LightContainerInterface $container 
     * @return array
     */
    protected function buildOptionsFromParents(LightContainerInterface $container): array {
        if (!$this->isAutowired()) return $this->options;

        $options = [];
        foreach ($this->cache['tree'] as $parent) {
            $parent_resolver = $container->getResolver($parent, false);
            if (($parent_resolver != null) && $parent_resolver->options['propagate']) {
                $options = $parent_resolver->options;
                $this->rebuildCache($options);
                break;
            }
        }
        // If default resolver is not defined
        if (empty($options)) $options = $this->options;

        return $options;
    }

    protected function buildMethodParamsCache(\ReflectionMethod $method): array {
        $results = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType) {
                // Parameter has a type hint. Note that the type may not necessarily
                // be a class - it can be an built-in type (e.g. int, array)
                $entry = [
                    'type' => ltrim($type->getName(), '?'),
                    'builtin' => $type->isBuiltIn(),
                    'optional' => ($param->allowsNull() || $param->isDefaultValueAvailable())
                ];
            } else {
                // Type hint is a ReflectionUnionType or no type hint
                // at all
                $entry = [
                    'type' => ($param->isVariadic()) ? '...' : '*',
                    'builtin' => true,
                    'optional' => $param->isDefaultValueAvailable()
                ];
            }
            if ($param->isDefaultValueAvailable()) {
                $entry['default'] = $param->getDefaultValue();
            }

            $results[]  = $entry;
        }

        return $results;
    }

    protected function buildMethodResolvers(string $method, array $options): array {
        $resolvers = [];
        $params = $this->cache['params'][$method];
        $aliases = $options['alias'];
        if ($method == '__construct') {
            $args = $options['args'];
        } else {
            $i = array_search($method, array_column($options['call'], 'method'));
            if ($i === false) {
                throw new \UnexpectedValueException('Method not found: ' . $method);
            }
            $args = $options['call'][$i]['args'];
        }

        $n = 1;
        foreach ($params as $param) {
            if ($param['type'] == '...') {
                // variadic
                foreach ($args as $value) {
                    if ($value instanceof ResolverInterface) {
                        $resolvers[] = $value;
                    } else {
                        $resolvers[] = new ValueResolver($value);
                    }
                }
                break;
            } elseif (($param['type'] == '*') || $param['builtin']) {
                // Union type, builtin type or no type hint
                // We pick up whatever's next in the argument array, with
                // some type checking if applicable
                if (empty($args)) {
                    if (!$param['optional']) {
                        throw new ContainerException('Mandatory parameter ' . $n . ' not provided for method ' . $method);
                    }
                    break;
                }

                $resolver = array_shift($args);
                if (($param['type'] != '*') && ($resolver instanceof TypeCheckInterface)) {
                    // Do type checking where available
                    if (!$resolver->checkType($param['type'])) {
                        throw new ContainerException('Incorrect type given for parameter ' . $n . ': expected ' . $param['type']);
                    }
                }

                $resolvers[] = $resolver;
            } else {
                try {
                    if (isset($options['alias'][$param['type']])) {
                        if ($options['alias'][$param['type']] == null) {
                            $resolvers[] = ValueResolver::nullResolver();
                        } else {
                            $resolvers[] = new ReferenceResolver($options['alias'][$param['type']]);
                        }
                    } elseif (!$param['optional']) {
                        $resolvers[] = new ReferenceResolver($param['type']);
                    } elseif (isset($param['default'])) {
                        $resolvers[] = new ValueResolver($param['default']);
                    } else {
                        $resolvers[] = ValueResolver::nullResolver();
                    }
                } catch (\InvalidArgumentException $e) {

                }
            }
            $n++;
        }

        return $resolvers;
    }

    public function resolve(LightContainerInterface $container) {
        // 1. Retrieve saved shared object (if it exists)
        if ($this->hasSharedObject()) return $this->getSharedObject();

        // 2. Get options from parent classes if autowired
        if ($this->isAutowired()) {
            $options = $this->buildOptionsFromParents($container);
        } else {
            $options = $this->options;
        }

        // 3. Resolve constructor parameters
        if (!isset($this->cache['resolvers']['__construct'])) {
            if ($this->cache['constructor'] == null) {
                $this->cache['resolvers']['__construct'] = [];
            } else {
                $this->cache['resolvers']['__construct'] = $this->buildMethodResolvers('__construct', $options);
            }
        }

        // 4. Create the object
        if (empty($this->cache['resolvers']['__construct'])) {
            $object = new $this->class_name;
        } else {
            if ($options['shared'] && !$this->cache['is_internal']) {
                // Here we use some trickery to instantiate the object and store it in cache
                // before calling the constructor.  This avoids an infinite dependency loop
                // that may occur if A depends on B but then B depends on A
                //
                // See https://github.com/Level-2/Dice/issues/7
                $refl = new \ReflectionClass($this->class_name);
                $object = $refl->newInstanceWithoutConstructor();
                $this->saveSharedObject($object);
                $this->cache['constructor']->invokeArgs($object, array_map(function ($param_resolver) use ($container) {
                    return $param_resolver->resolve($container);
                }, $this->cache['resolvers']['__construct']));
            } else {
                $object = new $this->class_name(...array_map(function ($param_resolver) use ($container) {
                    return $param_resolver->resolve($container);
                }, $this->cache['resolvers']['__construct']));
            }
        }

        // 5. Call the setters
        if (isset($options['call'])) {
            foreach ($options['call'] as $call) {
                $method = $call['method'];
                if (!isset($this->cache['resolvers'][$method])) {
                    $this->cache['resolvers'][$method] = $this->buildMethodResolvers($method, $options);
                }
                $object->$method(...array_map(function ($param_resolver) use ($container) {
                    return $param_resolver->resolve($container);
                }, $this->cache['resolvers'][$method]));
            }
        }
        
        // 6. Save the object if it is shared
        if ($options['shared']) $this->saveSharedObject($object);

        // 7. Return the object
        return $object;
    }

    public function __clone() {
        parent::__clone();
        $this->cache['resolvers'] = [];
    }
}

?>