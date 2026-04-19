<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\ContextFactory;

use Speakeasy\Serializer\SerializationContext;

/**
 * Default Serialization Context Factory.
 */
final class DefaultSerializationContextFactory implements SerializationContextFactoryInterface
{
    public function createSerializationContext(): SerializationContext
    {
        return new SerializationContext();
    }
}
