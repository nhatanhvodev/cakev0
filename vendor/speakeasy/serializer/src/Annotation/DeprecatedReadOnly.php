<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Annotation;

/**
 * @Annotation
 * @Target({"CLASS","PROPERTY"})
 *
 * @deprecated use `@ReadOnlyProperty` instead
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
final class DeprecatedReadOnly extends ReadOnlyProperty
{
}
