<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\GraphNavigator\Factory;

use Speakeasy\Serializer\GraphNavigatorInterface;

interface GraphNavigatorFactoryInterface
{
    public function getGraphNavigator(): GraphNavigatorInterface;
}
