<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Accessor;

use Speakeasy\Serializer\DeserializationContext;
use Speakeasy\Serializer\Metadata\PropertyMetadata;
use Speakeasy\Serializer\SerializationContext;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
interface AccessorStrategyInterface
{
    /**
     * @return mixed
     */
    public function getValue(object $object, PropertyMetadata $metadata, SerializationContext $context);

    /**
     * @param mixed $value
     */
    public function setValue(object $object, $value, PropertyMetadata $metadata, DeserializationContext $context): void;
}
