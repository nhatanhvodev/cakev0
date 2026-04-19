<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\ContextFactory;

use Speakeasy\Serializer\DeserializationContext;

/**
 * Deserialization Context Factory Interface.
 */
interface DeserializationContextFactoryInterface
{
    public function createDeserializationContext(): DeserializationContext;
}
