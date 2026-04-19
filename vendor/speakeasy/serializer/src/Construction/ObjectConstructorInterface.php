<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Construction;

use Speakeasy\Serializer\DeserializationContext;
use Speakeasy\Serializer\Metadata\ClassMetadata;
use Speakeasy\Serializer\Visitor\DeserializationVisitorInterface;

/**
 * Implementations of this interface construct new objects during deserialization.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface ObjectConstructorInterface
{
    /**
     * Constructs a new object.
     *
     * Implementations could for example create a new object calling "new", use
     * "unserialize" techniques, reflection, or other means.
     *
     * @param mixed $data
     * @param array $type ["name" => string, "params" => array]
     */
    public function construct(DeserializationVisitorInterface $visitor, ClassMetadata $metadata, $data, array $type, DeserializationContext $context): ?object;
}
