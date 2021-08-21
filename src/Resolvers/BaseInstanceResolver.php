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
use LightContainer\NotFoundException;

/**
 * A class for resolvers that can resolve to an instance
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
 */
class BaseInstanceResolver implements ResolverInterface {
    /**
     * An array of instantiation options
     * 
     * @var array
     */
    protected $options = [
        'shared' => false,
        'propagate' => true,
        'alias' => [],
        'args' => [],
        'call' => []
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
     * @param array $options instantiation options
     */
    public function __construct(array $options = null) {
        if ($options != null) {
            $this->options = $options;
        }
    }

    /**
     * Sets whether the same instance of the class will be returned for
     * all calls to this resolver.
     * 
     * Note that once this is set to true and the object has been
     * resolved, this cannot be set to false again.  An attempt to do
     * so will result in an `InvalidArgumentException`
     * 
     * @param bool $shared true if shared
     * @return BaseInstanceResolver
     * @throws \InvalidArgumentException if the shared object has already
     * been created and $shared is false
     */
    public function shared(bool $shared = true) {
        if (($this->shared != null) && (!$shared)) {
            throw new \InvalidArgumentException('Cannot set shared to false once shared object is created');
        }
        $this->options['shared'] = $shared;
        return $this;
    }

    /**
     * Sets whether the instantiation options set for this resolver
     * will propagate to autowired resolvers.
     * 
     * What "autowired resolvers" mean is defined by the resolvers which
     * are based on this class.  For example {@link ClassResolver}
     * defines "autowired resolvers" as automatically generated
     * resolvers for classes which are subclasses of the parent class.
     * 
     * @param bool $propagate true if options will be propagated
     * @return BaseInstanceResolver
     */
    public function propagate(bool $propagate = true) {
        $this->options['propagate'] = $propagate;
        return $this;
    }

    /**
     * Set aliases to be used when autowring type-hinted arguments
     * in the constructor and the setter methods.
     * 
     * These aliases override the resolvers registered directly
     * with the container.
     * 
     * This method can be called with two string arguments, or
     * an array mapping (which allows for multiple aliases
     * to be mapped).
     * 
     * <code>
     * $resolver->alias(From::class, Target::class);
     * $resolver->alias([ From::class => Target::class ]);
     * </code>
     * 
     * For passing values to constructor arguments that do not have
     * type hints, use the {@link args()} method.
     * 
     * @param $args the aliases
     * @return BaseInstanceResolver
     */
    public function alias(...$args) {
        if (count($args) == 2) {
            $args = [[ $args[0] => $args[1] ]];
        }
        if ((count($args) != 1) || !is_array($args[0])) {
            throw new \InvalidArgumentException();
        }
        foreach ($args[0] as $id => $target) {
            if (!is_string($id) || !is_string($target)) {
                throw new \InvalidArgumentException();
            }
            $this->options['alias'][$id] = ltrim($target, '\\');
        }
        return $this;
    }

    /**
     * Sets the values of non-type-hinted or scalar arguments to be passed onto
     * the constructor.  Type-hinted class arguments are retrieved from the
     * container (with autowiring where required).
     * 
     * The arguments should be specified in the same order as the parameters
     * are declared in the constructor (excluding the type-hinted class
     * parameters).  These can be specified as
     * 
     * - literal values, which are passed directly to the constructor; or
     * - resolvers that implement {@link ResolverInterface}, which are
     *   resolved before passing onto the constructor.
     * 
     * In particular, strings are passed on as literal values. Therefore, in
     * order to refer to an entry (such as a class definition or a named
     * instance) in the container, use {@link LightContainer\Container::ref()}
     * to create a ReferenceResolver.
     * 
     * @param $args values to be passed onto the constructor
     * @return BaseInstanceResolver
     */
    public function args(...$args) {
        foreach ($args as $arg) {
            if ($arg instanceof ResolverInterface) {
                $this->options['args'][] = $arg;
            } else {
                $this->options['args'][] = new ValueResolver($arg);
            }
        }

        return $this;
    }

    /**
     * Adds a method to be called after the object has been constructed.  Typically
     * these are setter methods for which dependency injection from the container
     * is required.
     * 
     * The method is specified in the first parameter.  The following parameters
     * are passed onto the setter function in the same way as {@link ::args()},
     * that is, type-hinted class arguments are retrieved from the container, and
     * other arguments are specified in the arguments to this method.
     * 
     * @param string $method the name of the method to call
     * @param array $args the arguments
     * @return BaseInstanceResolver
     */
    public function call(string $method, ...$args) {
        $this->options['call'][] = [
            'method' => $method,
            'args' => $args,
        ];

        return $this;
    }

    /**
     * Sets the instantiation options.
     * 
     * @param array $options the instantiation option.
     */
    protected function setOptions(array $options) {
        $this->options = $options;
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
     * {@inheritdoc}
     */
    public function resolve(LightContainerInterface $container) {
        throw new NotFoundException('Cannot resolve without an underlying class');
    }

    public function __clone() {
        $this->shared = null;
    }
}

?>