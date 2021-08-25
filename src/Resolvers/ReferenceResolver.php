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
 * A resolver that resolves to an object by calling another resolver
 * registered in the container.
 */
class ReferenceResolver extends BaseInstanceResolver {
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
    public function __construct(string $target) {
        parent::__construct(['propagate' => false]);
        $this->target = $target;
    }

    /**
     * Flags that this resolver relates to a named instance.
     */
    public function setNamedInstance() {
        $this->named = true;
        return $this->shared();
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
        return (!empty($this->options['alias']) || !empty($this->options['args']) || !empty($this->options['call']));
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
     * @param LightContainerInterface the container
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
            throw new NotFoundException('Cannot resolve reference to: ' . $this->target);
        }

        // 3. Clone the resolver if the target resolver is a ClassResolver
        //    and the target resolver is not shared.  This way we can set specific
        //    options for the resolver
        if (($resolver instanceof ClassResolver)
            && !$resolver->resolveShared($container)
            && $this->hasCustomOptions()) {
            $resolver = clone $resolver;
            $resolver->setAutowired(false);

            // Set options - only alias, args and call should be set
            $options = array_filter($this->options, function ($v, $k) { 
                return (in_array($k, ['alias', 'args', 'call']) && !empty($v)); 
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