<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Type;

interface ParserInterface
{
    public function parse(string $type): array;
}
