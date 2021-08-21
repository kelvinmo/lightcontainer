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
 * A resolver that instantiates a class.
 */
class ClassResolver extends BaseInstanceResolver implements AutowireInterface {
    protected $class_name;

    protected $autowired = false;

    protected $cache = [
        'tree' => [],
        'constructor' => null,   // ReflectionMethod for the constructor
        'params' => [],
        'resolvers' => [],
        'is_internal' => false   // Whether the class is defined by C code or PHP code
    ];

    public function __construct($class_name, $options = null) {
        parent::__construct($options);

        if (!class_exists($class_name)) {
            throw new \InvalidArgumentException('ClassResolver can only be used for concrete classes (not interfaces or traits)');
        }
        $this->class_name = $class_name;

        // Cache class reflection information
        $refl = new \ReflectionClass($this->class_name);

        $constructor = $refl->getConstructor();
        $this->cache['params']['__construct'] = ($constructor != null) ? $this->buildMethodParamsCache($constructor) : [];
        $this->cache['constructor'] = $constructor;

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

        // TODO Clear the resolver cache or rebuild?
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
    protected function setOptions(array $options) {
        // TODO check method exists, clear caches
        parent::setOptions($options);
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
    protected function buildOptionsFromParents(LightContainerInterface $container) {
        if (!$this->isAutowired()) return $this->options;

        $options = [];
        foreach ($this->cache['tree'] as $parent) {
            $parent_resolver = $container->getResolver($parent, false);
            if (($parent_resolver != null) && $parent_resolver->options['propagate']) {
                // TODO clear and reset cache if $parent_resolver->options has ['alias']???
                // TODO array_merge for everything other than call?
                $options = array_merge($parent_resolver->options, $options);
                break;
            }
        }
        // If default resolver is not defined
        if (empty($options)) $options = $this->options;

        return $options;
    }

    protected function buildMethodParamsCache(\ReflectionMethod $method) {
        $results = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType) {
                // Parameter has a type hint. Note that the type may not necessarily
                // be a class - it can be an built-in type (e.g. int, array)
                $entry = [
                    'type' => $type->getName(),
                    'builtin' => $type->isBuiltIn(),
                    'optional' => $param->allowsNull()
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

    protected function buildMethodResolvers($method, $options) {
        $resolvers = [];
        $params = $this->cache['params'][$method];
        $aliases = $options['alias'];
        if ($method == '__construct') {
            $args = $options['args'];
        } else {
            $i = array_search($method, array_column($options['call'], 'method'));
            if ($i === false) {
                // TODO Method not found!
            }
            $args = $options['call'][$i]['args'];
        }

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
            } elseif ($param['type'] == '*') {
                // Union type or no type hint

                if (empty($args)) {
                        if (!$param['optional']) {
                            // Error
                        }
                        break;
                    }
                    //$value = array_shift($args);
            } elseif ($param['builtin']) {

            } else {
                try {
                    if (isset($options['alias'][$param['type']])) {
                        $resolvers[] = new ReferenceResolver($options['alias'][$param['type']]);
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
        }

        return $resolvers;
    }

    protected function updateMethodResolvers() {

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