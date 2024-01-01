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

/**
 * An interface for resolvers that can be created by autowiring.
 * 
 * When dependencies are autowired, LightContainer creates resolvers
 * for classes which have not been previously been explicitly
 * configured.
 * 
 * Resolvers that have been created by autowiring do not get
 * exported as part of the container's configuration, and can be
 * excluded from searches of resolvers in the 
 * {@link LightContainer\LightContainerInterface::getResolver()}
 * function.
 */
interface AutowireInterface extends ResolverInterface {
    /**
     * Returns whether this resolver was created by autowiring
     * 
     * @return bool true if the resolver was created by
     * autowiring
     */
    public function isAutowired(): bool;

    /**
     * Sets whether the resolver was created by autowiring
     * 
     * @param bool $autowired true if the resolver was created by
     * autowiring
     */
    public function setAutowired(bool $autowired): AutowireInterface;
}

?>