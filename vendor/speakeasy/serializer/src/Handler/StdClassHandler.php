<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Handler;

use Speakeasy\Serializer\GraphNavigatorInterface;
use Speakeasy\Serializer\Metadata\StaticPropertyMetadata;
use Speakeasy\Serializer\SerializationContext;
use Speakeasy\Serializer\Visitor\SerializationVisitorInterface;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
final class StdClassHandler implements SubscribingHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribingMethods()
    {
        $methods = [];
        $formats = ['json', 'xml'];

        foreach ($formats as $format) {
            $methods[] = [
                'direction' => GraphNavigatorInterface::DIRECTION_SERIALIZATION,
                'type' => \stdClass::class,
                'format' => $format,
                'method' => 'serializeStdClass',
            ];
        }

        return $methods;
    }

    /**
     * @return mixed
     */
    public function serializeStdClass(SerializationVisitorInterface $visitor, \stdClass $stdClass, array $type, SerializationContext $context)
    {
        $classMetadata = $context->getMetadataFactory()->getMetadataForClass('stdClass');
        $visitor->startVisitingObject($classMetadata, $stdClass, ['name' => 'stdClass']);

        foreach ((array) $stdClass as $name => $value) {
            $metadata = new StaticPropertyMetadata('stdClass', $name, $value);
            $visitor->visitProperty($metadata, $value);
        }

        return $visitor->endVisitingObject($classMetadata, $stdClass, ['name' => 'stdClass']);
    }
}
