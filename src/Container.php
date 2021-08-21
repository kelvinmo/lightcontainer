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

namespace LightContainer;

use LightContainer\Resolvers\AutowireInterface;
use LightContainer\Resolvers\ResolverInterface;
use LightContainer\Resolvers\BaseInstanceResolver;
use LightContainer\Resolvers\ClassResolver;
use LightContainer\Resolvers\FactoryResolver;
use LightContainer\Resolvers\ReferenceResolver;
use LightContainer\Resolvers\ValueResolver;

/**
 * A lightweight, autowiring, PSR-11 compliant container
 */
class Container implements LightContainerInterface {
    /**
     * An array of resolvers
     * 
     * @var array
     */
    protected $resolvers = [];

    /**
     * A resolver that resolves to this container
     * 
     * @var ValueResolver
     */
    protected $self_resolver;

    public function __construct() {
        $this->self_resolver = new ValueResolver($this);
        $this->set(self::class, $this->self_resolver);
    }
    
    /**
     * Configures a resolver against an identifier
     * 
     * @param string $id the identifier
     * @param mixed $value the value
     * @return ResolverInterface the resolver
     * @throws InvalidArgumentException
     */
    public function set(string $id, $value = null): ResolverInterface {
        $id = ltrim($id, '\\');

        if ($id == '*') {
            $resolver = new BaseInstanceResolver();
        } elseif ($value != null) {
            if ($value instanceof ResolverInterface) {
                // We clone the resolver to make sure caches are cleared
                $resolver = clone $value;
            } elseif (is_callable($value)) {
                $resolver = new FactoryResolver($value);
            } elseif (is_string($value)) {
                $value = ltrim($value, '\\');
                $resolver = new ReferenceResolver($value);

                // TODO Should we set this to shared if $id is not a recognisable class/interface?
                // Or if it contains an invalid class/interface character?
                if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/', $id)) {
                    $resolver->shared();
                }
            } else {
                $resolver = new ValueResolver($value);
            }
        } elseif (class_exists($id)) {
            $resolver = new ClassResolver($id);
        } else {
            throw new \InvalidArgumentException('Missing $value');
        }

        $this->resolvers[$id] = $resolver;
        return $resolver;
    }

    /**
     * Remove a resolver from a container
     * 
     * @param string $id the identifier
     */
    public function unset(string $id) {
        if (isset($this->resolvers[$id])) {
            if ($this->resolvers[$id]->isAutowired()) return;
            unset($this->resolvers[$id]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id) {
        if (!$this->has($id)) throw new NotFoundException('ID not found: ' . $id);

        $result = $this->getResolver($id)->resolve($this);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool {
        // We set $include_autowire to false here because we don't want to autowire
        // until we have to
        if ($this->getResolver($id, false) != null) return true;
        return class_exists($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getResolver(string $id, bool $include_autowire = true): ?ResolverInterface {
        if (isset($this->resolvers[$id])) {
            $resolver = $this->resolvers[$id];
            if (($resolver instanceof AutowireInterface) && $resolver->isAutowired() && !$include_autowire) {
                return null;
            } else {
                return $resolver;
            }
        } else {
            if (!$include_autowire) return null;
            if (!class_exists($id)) return null;
            $this->resolvers[$id] = (new ClassResolver($id))->setAutowired(true);
            return $this->resolvers[$id];
        }
    }

    /**
     * Utility function to wrap a value with a ValueResolver
     * 
     * @param mixed $value the value to wrap
     * @return ValueResolver the resolver
     */
    public static function value($value) {
        return new ValueResolver($value);
    }

    public static function ref(string $id) {
        return new ReferenceResolver($id);
    }

    /**
     * Returns a ValueResolver that resolves to the instance of the
     * container.
     * 
     * @return ValueResolver the resolver
     */
    public function getSelfResolver() {
        return $this->self_resolver;
    }
}

?>