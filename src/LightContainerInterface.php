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

use LightContainer\Resolvers\ResolverInterface;
use Psr\Container\ContainerInterface;

/**
 * The core LightContainer interface implemented by {@link Container}.
 * 
 * This interface is a subinterface of {@link Psr\Container\ContainerInterface},
 * meaning all implementations of this interface will be PSR-11
 * compliant.
 */
interface LightContainerInterface extends ContainerInterface {
    /**
     * Configures and registers a resolver for a specified identifier.
     * 
     * This method takes the string entry identifier and the specified value,
     * then creates and registers a resolver, and returns the resolver.
     * 
     * The kind of resolver the method creates depends on the format of the
     * entry identifier, and the type of value that is specified. These
     * are detailed in the documentation under the *See* section.
     * 
     * @param string $id the entry identifier
     * @param mixed $value the configuration
     * @return ResolverInterface the resolver
     * @throws \InvalidArgumentException if the specified configuration
     * is invalid
     * @see https://github.com/kelvinmo/lightcontainer/blob/master/README.md
     */
    public function set(string $id, $value = null): ResolverInterface;

    /**
     * Registers a resolver that resolves to a specified class, with respect to
     * all the interfaces implemented by the class.  This is useful when
     * registering a class that implements one or more services specified using
     * interfaces.
     * 
     * This method uses PHP reflection to determine all the interfaces
     * implemented by the specified `$class_name` (including all parents of
     * these interfaces), and then registers a class resolver against all these
     * interface names that resolves to this class.  This methods overwrites
     * all the resolvers previously registered against those interfaces.
     * 
     * Interfaces to be excluded from registration can be specified using the
     * `$exclude` parameter.
     * 
     * Note that a single instance of the class resolver is created by this
     * method.  All the registered interfaces point to the shared resolver.
     * This resolver is returned by the method for further configuration.
     * 
     * @param string $class_name the name of the class to find interfaces
     * @param array<string> $exclude an array of interface names to exclude from
     * registration
     * @return ResolverInterface the resolver
     */
    public function populate(string $class_name, array $exclude = []): ResolverInterface;

    /**
     * Returns the resolver associated with a particular entry identifier.
     * 
     * If a resolver cannot be found, it returns null
     * 
     * @param string $id the entry identifier
     * @param bool $include_autowire whether to include resolvers automatically
     * generated through autowiring
     * @return ResolverInterface the resolver or null if the resolver cannot
     * be found
     */
    public function getResolver(string $id, bool $include_autowire = true): ?ResolverInterface;

    /**
     * Loads a container configuration from an array
     * 
     * @param array<string, mixed> $config the array to load
     * @throws \LightContainer\Loader\LoaderException if an error occurs
     * in the load
     * @return void
     */
    public function load(array $config);
}
?>