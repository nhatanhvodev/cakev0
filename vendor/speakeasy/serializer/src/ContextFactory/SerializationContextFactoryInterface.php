<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\ContextFactory;

use Speakeasy\Serializer\SerializationContext;

/**
 * Serialization Context Factory Interface.
 */
interface SerializationContextFactoryInterface
{
    public function createSerializationContext(): SerializationContext;
}
