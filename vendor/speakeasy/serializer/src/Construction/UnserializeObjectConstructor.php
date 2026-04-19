<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Construction;

use Doctrine\Instantiator\Instantiator;
use Speakeasy\Serializer\DeserializationContext;
use Speakeasy\Serializer\Metadata\ClassMetadata;
use Speakeasy\Serializer\Visitor\DeserializationVisitorInterface;

final class UnserializeObjectConstructor implements ObjectConstructorInterface
{
    /** @var Instantiator */
    private $instantiator;

    /**
     * {@inheritdoc}
     */
    public function construct(DeserializationVisitorInterface $visitor, ClassMetadata $metadata, $data, array $type, DeserializationContext $context): ?object
    {
        return $this->getInstantiator()->instantiate($metadata->name);
    }

    private function getInstantiator(): Instantiator
    {
        if (null === $this->instantiator) {
            $this->instantiator = new Instantiator();
        }

        return $this->instantiator;
    }
}
