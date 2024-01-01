<?php
/*
 * LightContainer
 *
 * Copyright (C) Kelvin Mo 2021-2024
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
use LightContainer\InstanceModifierInterface;
use LightContainer\NotFoundException;
use LightContainer\Loader\LoadableInterface;
use LightContainer\Loader\LoaderInterface;

/**
 * A base class for resolvers that can resolve to an instance
 * of an object.
 * 
 * This class provides methods for setting instantiation options,
 * including {@link shared()}, {@link propagate()}, {@link alias()},
 * {@link args()} and {@link call()}.
 * 
 * Note that resolvers that are subclasses of this class may not
 * necessarily resolve to an object all the time.  For example, a
 * {@link ReferenceResolver} can resolve to anything that the
 * container can resolve, not just objects.
 * 
 * The two resolvers that are derived from this class are:
 * 
 * - {@link ClassResolver} - a resolver that creates an instance
 *   of a specified class
 * - {@link ReferenceResolver} - a resolver that looks up another
 *   entry in the container
 */
class BaseInstanceResolver implements ResolverInterface, LoadableInterface, InstantiationOptionsInterface {
    /**
     * An array of instantiation options
     * 
     * - `shared` (`bool`): true if this is a shared instance
     * - `propagate` (`bool`): true if instantiation options are propagated to autowired classes
     * - `alias` (`array<string, string>`): an array of aliases
     * - `args` (`array<mixed>`): an array of arguments to be passed to the constructor
     * - `call` (`array<array<string, mixed>>`): an array of arrays, each of which has the following
     *   keys:
     *         - `method` (`string`): the method to call
     *         - `args` (`array<ResolverInterface>`): an array of resolvers
     * - `modify` (`InstanceModifierInterface?`): the modifier or null
     * 
     * @var array<string, mixed>
     */
    protected $options = [
        'shared' => false,
        'propagate' => true,
        'alias' => [],
        'args' => [],
        'call' => [],
        'modify' => null
    ];

    /**
     * Stores the shared object
     * 
     * @var mixed
     */
    private $shared = null;

    /**
     * Creates a BaseInstanceResolver
     * 
     * @param array<string, mixed>|null $options instantiation options
     */
    public function __construct(array $options = null) {
        if ($options != null) {
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function shared(bool $shared = true) {
        if (($this->shared != null) && (!$shared)) {
            throw new \InvalidArgumentException('Cannot set shared to false once shared object is created');
        }
        $this->options['shared'] = $shared;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function propagate(bool $propagate = true) {
        $this->options['propagate'] = $propagate;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function alias(...$args) {
        if (count($args) == 2) {
            $args = [[ $args[0] => $args[1] ]];
        }
        if ((count($args) != 1) || !is_array($args[0])) {
            throw new \InvalidArgumentException('alias requires two arguments or a single array');
        }
        foreach ($args[0] as $id => $target) {
            if (!is_string($id)) {
                throw new \InvalidArgumentException('alias identifier must be a string');
            }
            if (!(is_string($target) || ($target == null))) {
                throw new \InvalidArgumentException('alias target must be a string or null');
            }
            $this->options['alias'][$id] = ($target == null) ? '' : ltrim($target, '\\');
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function args(...$args) {
        $this->options['args'] = array_merge($this->options['args'], $this->buildResolversFromArgs($args));
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function call(string $method, ...$args) {
        $this->options['call'][] = [
            'method' => $method,
            'args' => $this->buildResolversFromArgs($args),
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function modify(?InstanceModifierInterface $modifier) {
        $this->options['modify'] = $modifier;
        return $this;
    }

    /**
     * Builds resolvers from arguments of {@link ::args()} and
     * {@link ::call()}.
     * 
     * @param array<mixed> $args the arguments
     * @return array<ResolverInterface> an array of resolvers
     */
    protected function buildResolversFromArgs(array $args) {
        $resolvers = [];

        foreach ($args as $arg) {
            if ($arg instanceof ResolverInterface) {
                $resolvers[] = $arg;
            } else {
                $resolvers[] = ValueResolver::create($arg);
            }
        }

        return $resolvers;
    }

    /**
     * Sets the instantiation options.
     * 
     * @param array<string, mixed> $options the instantiation options
     * @return void
     */
    protected function setOptions(array $options) {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Returns whether a shared object has been created and saved with this
     * resolver.
     * 
     * @returns bool true if a shared object has been saved
     */
    protected function hasSharedObject(): bool {
        return ($this->shared != null);
    }

    /**
     * Saves an object as a shared object for this container.
     * 
     * @param mixed $object the object to save
     * @return void
     */
    protected function saveSharedObject($object) {
        $this->shared = $object;
    }

    /**
     * Retrieves the shared object saved with this container.
     * 
     * @return mixed the shared object
     */
    protected function getSharedObject() {
        return $this->shared;
    }

    /**
     * Loads instantiation options from a configuration array.
     * 
     * @param mixed $value the part of the configuration array to load
     * @param string $id the entry ID, if applicable
     * @param LoaderInterface $loader the configuration loader
     * @return ResolverInterface the resolver
     * @throws \LightContainer\Loader\LoaderException if an error occurs
     */
    protected function load($value, ?string $id, LoaderInterface $loader): ResolverInterface {
        if (isset($value['shared'])) $this->shared($value['shared']);
        if (isset($value['propagate'])) $this->propagate($value['propagate']);
        if (isset($value['alias'])) $this->alias($value['alias']);
        if (isset($value['args'])) $this->args(...array_map(function ($arg) use ($loader) {
            return $loader->load($arg, null, LoaderInterface::LITERAL_CONTEXT);
        }, $value['args']));

        if (isset($value['call'])) {
            foreach ($value['call'] as $args) {
                $method = array_shift($args);
                $this->call($method, ...array_map(function ($arg) use ($loader) {
                    return $loader->load($arg, null, LoaderInterface::LITERAL_CONTEXT);
                }, $args));
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromLoader($value, ?string $id, LoaderInterface $loader): ResolverInterface {
        $resolver = new BaseInstanceResolver();
        return $resolver->load($value, $id, $loader);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(LightContainerInterface $container) {
        throw new NotFoundException('Cannot resolve without an underlying class');
    }

    /**
     * Remove the shared object from any clone of the resolver.
     */
    public function __clone() {
        $this->shared = null;
    }
}

?>