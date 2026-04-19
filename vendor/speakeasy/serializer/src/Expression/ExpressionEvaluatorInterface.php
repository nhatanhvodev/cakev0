<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Expression;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
interface ExpressionEvaluatorInterface
{
    /**
     * @return mixed
     */
    public function evaluate(string $expression, array $data = []);
}
