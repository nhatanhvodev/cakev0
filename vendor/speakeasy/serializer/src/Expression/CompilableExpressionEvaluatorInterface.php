<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\Expression;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
interface CompilableExpressionEvaluatorInterface
{
    public function parse(string $expression, array $names = []): Expression;

    /**
     * @return mixed
     */
    public function evaluateParsed(Expression $expression, array $data = []);
}
