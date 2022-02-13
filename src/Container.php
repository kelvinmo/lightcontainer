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
use LightContainer\Loader\Loader;
use LightContainer\Loader\LoaderInterface;
use LightContainer\Loader\LoaderException;

/**
 * A lightweight, autowiring, PSR-11 compliant container
 * 
 * @link https://github.com/kelvinmo/lightcontainer
 */
class Container implements LightContainerInterface {
    /**
     * Regular expression for potentially valid class/interface/trait
     * names.
     * 
     * @see https://stackoverflow.com/questions/3195614/validate-class-method-names-with-regex/12011255
     */
    const CLASS_NAME_REGEX = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/';

    /**
     * An array of resolvers
     * 
     * @var array<string, ResolverInterface>
     */
    protected $resolvers = [];

    /**
     * A resolver that resolves to this container
     * 
     * @var ValueResolver
     */
    protected $self_resolver;

    public function __construct() {
        $this->self_resolver = ValueResolver::create($this);
        $this->set(self::class, $this->self_resolver);
        $this->set(LoaderInterface::class, Loader::class);
    }
    
    /**
     * {@inheritdoc}
     */
    public function set(string $id, $value = null): ResolverInterface {
        $id = ltrim($id, '\\');

        if ($id == '*') {
            $resolver = new BaseInstanceResolver();
        } elseif ($value !== null) {
            if ($value instanceof ResolverInterface) {
                // We clone the resolver to make sure caches are cleared
                $resolver = clone $value;
            } elseif (is_callable($value)) {
                $resolver = new FactoryResolver($value);
            } elseif (is_string($value)) {
                $value = ltrim($value, '\\');
                $resolver = new ReferenceResolver($value);

                if (!self::isValidTypeName($id)) {
                    $resolver->setNamedInstance();
                }
            } else {
                $resolver = ValueResolver::create($value);
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
     * @return void
     */
    public function unset(string $id) {
        if (isset($this->resolvers[$id])) {
            if (($this->resolvers[$id] instanceof AutowireInterface) && ($this->resolvers[$id]->isAutowired())) return;
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
            try {
                $this->resolvers[$id] = (new ClassResolver($id))->setAutowired(true);
                return $this->resolvers[$id];
            } catch (\InvalidArgumentException $e) {
                return null;
            }
            
        }
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $config) {
        $loader = $this->get(LoaderInterface::class);
        $resolvers = [];

        // We want this to be an atomic operation, so we decode all the resolvers
        // before setting them.
        foreach ($config as $id => $value) {
            try {
                $resolvers[$id] = $loader->load($value, $id, LoaderInterface::REFERENCE_CONTEXT);
            } catch (LoaderException $e) {
                throw new LoaderException('Cannot load entry ' . $id . ': ' . $e->getMessage(), 0, $e);
            }
            
        }

        $this->resolvers = array_merge($this->resolvers, $resolvers);
    }

    /**
     * Determines whether or not a string resembles a valid type (class, interface
     * or trait name), regardless of whether the type
     * exists.
     * 
     * @param string $name the name to test
     * @return bool truw if the name is a valid type name
     */
    public static function isValidTypeName(string $name): bool {
        return preg_match(self::CLASS_NAME_REGEX, $name);
    }

    /**
     * Utility function to wrap a value with a ValueResolver.  This may be
     * required when storing string values using {@link set()}, where
     * by default strings are recognised as global aliases.
     * 
     * @param mixed $value the value to wrap
     * @return ResolverInterface the resolver
     */
    public static function value($value): ResolverInterface {
        return ValueResolver::create($value);
    }

    /**
     * Utility function to wrap a string with a ReferenceResolver.  This may be
     * required when passing on non-type hinted arguments using
     * {@link ClassResolver::args()} or {@link ClassResolver::call()}, where
     * by default strings are recognised as literal values rather than
     * references to entries in the container.
     * 
     * @param string $id the entry identifier to wrap
     * @return ResolverInterface the resolver
     */
    public static function ref(string $id): ResolverInterface {
        return new ReferenceResolver($id);
    }

    /**
     * Returns a ValueResolver that resolves to the instance of the
     * container.
     * 
     * @return ResolverInterface the resolver
     */
    public function getSelfResolver(): ResolverInterface {
        return $this->self_resolver;
    }
}

?>