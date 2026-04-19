<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Builder;

use Doctrine\Common\Annotations\Reader;
use Metadata\Driver\DriverInterface;

interface DriverFactoryInterface
{
    public function createDriver(array $metadataDirs, ?Reader $annotationReader = null): DriverInterface;
}
