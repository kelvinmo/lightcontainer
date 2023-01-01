<?php
/*
 * LightContainer
 *
 * Copyright (C) Kelvin Mo 2021-2023
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
use LightContainer\Loader\LoadableInterface;
use LightContainer\Loader\LoaderInterface;

/**
 * A resolver that resolves to an object by looking up another entry
 * from the container, obtaining the resolver for that entry from the
 * container (which may be created by autowiring), then using that to
 * resolve.
 * 
 * ReferenceResolvers are used for aliases (both entry and global) and
 * named instances.  Internally this resolver is also used by
 * ClassResolver to resolve type-hinted parameters during constructor
 * or setter injection.
 */
class ReferenceResolver extends BaseInstanceResolver implements LoadableInterface {
    /**
     * The entry identifier of the target
     * 
     * @var string
     */
    protected $target;

    /**
     * Whether this resolver relates to a named instance.  Named instances
     * are required to be shared.
     * 
     * @var bool
     */
    protected $named = false;

    /**
     * Creates a reference resolver to point to a particular target.
     * 
     * @param string $target the identifier of the target
     */
    public function __construct(string $target, ?ResolverInterface $default_resolver = null) {
        parent::__construct(['propagate' => false, 'default' => $default_resolver]);
        $this->target = $target;
    }

    /**
     * Flags that this resolver relates to a named instance.
     * 
     * @return ReferenceResolver
     */
    public function setNamedInstance() {
        $this->named = true;
        /** @var ReferenceResolver */
        $self = $this->shared();
        return $self;
    }

    /**
     * {@inheritdoc}
     */
    public function shared(bool $shared = true) {
        if (($this->named) && (!$shared)) {
            throw new \InvalidArgumentException('Cannot set shared to false for named instances');
        }
        if ($this->hasSharedObject() && (!$shared)) {
            throw new \InvalidArgumentException('Cannot set shared to false once shared object is created');
        }
        return parent::shared($shared);
    }

    /**
     * {@inheritdoc}
     */
    public function propagate(bool $propagate = true) {
        throw new \InvalidArgumentException('Propagate cannot be set on a ReferenceResolver');
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromLoader($value, ?string $id, LoaderInterface $loader): ResolverInterface {
        $resolver = new ReferenceResolver($value['target']);

        if (isset($value['named']) && ($value['named'] === true))
            $resolver->setNamedInstance();

        // Remove 'propagate' from the configuration array before passing onto load
        if (isset($value['propagate'])) unset($value['propagate']);

        return $resolver->load($value, $id, $loader);
    }

    /**
     * Returns whether custom instantiation options `alias`,
     * `args` and `call` have been set for this resolver.  In these
     * cases the target resolver may need to be cloned instead of
     * being referenced directly.
     * 
     * Note that the instantiation option `shared` is excluded
     * from this process.
     * 
     * @return bool true if custom instantiation options are set
     */
    public function hasCustomOptions(): bool {
        return (!empty($this->options['alias'])
            || !empty($this->options['args'])
            || !empty($this->options['call'])
            || !empty($this->options['modify']));
    }

    /**
     * Retrieves the identifier of the target referenced by this
     * resolver.
     * 
     * @return string the identifier of the target
     */
    public function getTarget(): string {
        return $this->target;
    }

    /**
     * Gets the resolver for the target, traversing through ReferenceResolvers
     * where required.
     * 
     * @param LightContainerInterface $container the container
     * @return ResolverInterface the traversed resolver
     */
    protected function getTargetResolver(LightContainerInterface $container): ?ResolverInterface {
        $resolver = $container->getResolver($this->target);
        if (($resolver instanceof ReferenceResolver) && (!$resolver->options['shared'])) {
            return $resolver->getTargetResolver($container);
        } else {
            return $resolver;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(LightContainerInterface $container) {
        // 1. Retrieved saved shared object (if it exists)
        if ($this->hasSharedObject()) return $this->getSharedObject();

        // 2. Get the target resolver
        $resolver = $this->getTargetResolver($container);
        if ($resolver == null) {
            if ($this->options['default'] != null) {
                return $this->options['default']->resolve($container);
            } else {
                throw new NotFoundException('Cannot resolve reference to: ' . $this->target);
            }
        }

        // 3. Clone the resolver if the target resolver is a ClassResolver
        //    and the target resolver is not shared.  This way we can set specific
        //    options for the resolver
        if (($resolver instanceof ClassResolver)
            && !$resolver->resolveShared($container)
            && $this->hasCustomOptions()) {
            $resolver = clone $resolver;
            $resolver->setAutowired(false);

            // Set options - only alias, args, call and modify should be set
            $options = array_filter($this->options, function ($v, $k) { 
                return (in_array($k, ['alias', 'args', 'call', 'modify']) && !empty($v)); 
            }, ARRAY_FILTER_USE_BOTH);

            $resolver->setOptions($options);
        }

        // 4. Create the object
        $object = $resolver->resolve($container);

        // 5. Save the object if it is shared
        if (($resolver instanceof ClassResolver) && $this->options['shared'])
            $this->saveSharedObject($object);
        
        // 6. Return the object
        return $object;
    }
}

?>