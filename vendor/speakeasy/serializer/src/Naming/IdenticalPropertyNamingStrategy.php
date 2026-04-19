<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Naming;

use Speakeasy\Serializer\Metadata\PropertyMetadata;

final class IdenticalPropertyNamingStrategy implements PropertyNamingStrategyInterface
{
    public function translateName(PropertyMetadata $property): string
    {
        return $property->name;
    }
}
