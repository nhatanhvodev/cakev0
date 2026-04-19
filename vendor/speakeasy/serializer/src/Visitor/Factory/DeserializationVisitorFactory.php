<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Visitor\Factory;

use Speakeasy\Serializer\Visitor\DeserializationVisitorInterface;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
interface DeserializationVisitorFactory
{
    public function getVisitor(): DeserializationVisitorInterface;
}
