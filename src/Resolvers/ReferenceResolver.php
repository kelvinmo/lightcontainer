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
    protected $target;

    protected $named = false;

    public function __construct(string $target, $options = null) {
        parent::__construct($options);
        $this->options['propagate'] = false;
        $this->target = $target;
    }

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
        $target_resolver = $this->getTargetResolver($container);
        if ($target_resolver == null) {
            throw new NotFoundException('Cannot resolve reference to: ' . $this->target);
        }

        // 3. Clone the resolver if the target resolver is a ClassResolver
        //    and the target resolver is not shared.  This way we can set specific
        //    options for the resolver
        if (($target_resolver instanceof ClassResolver) && !$target_resolver->buildIsShared($container)) {
            // TODO buildIsShared or is cached?
            $resolver = clone $target_resolver;
            $resolver->setAutowired(false);

            // Set options - only alias, args and call are set
            foreach (['alias', 'args', 'call'] as $option) {
                if (!empty($this->options[$option])) {
                    $resolver->options[$option] = $this->options[$option];
                }
            }
        } else {
            $resolver = $target_resolver;
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