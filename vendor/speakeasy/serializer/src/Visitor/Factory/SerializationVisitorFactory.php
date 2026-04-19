<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Visitor\Factory;

use Speakeasy\Serializer\Visitor\SerializationVisitorInterface;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
interface SerializationVisitorFactory
{
    public function getVisitor(): SerializationVisitorInterface;
}
