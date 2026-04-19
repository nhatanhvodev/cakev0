<?php

declare(strict_types=1);

namespace Speakeasy\Serializer\GraphNavigator\Factory;

use Speakeasy\Serializer\Accessor\AccessorStrategyInterface;
use Speakeasy\Serializer\Accessor\DefaultAccessorStrategy;
use Speakeasy\Serializer\EventDispatcher\EventDispatcher;
use Speakeasy\Serializer\EventDispatcher\EventDispatcherInterface;
use Speakeasy\Serializer\Expression\ExpressionEvaluatorInterface;
use Speakeasy\Serializer\GraphNavigator\SerializationGraphNavigator;
use Speakeasy\Serializer\GraphNavigatorInterface;
use Speakeasy\Serializer\Handler\HandlerRegistryInterface;
use Metadata\MetadataFactoryInterface;

final class SerializationGraphNavigatorFactory implements GraphNavigatorFactoryInterface
{
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    /**
     * @var HandlerRegistryInterface
     */
    private $handlerRegistry;
    /**
     * @var AccessorStrategyInterface
     */
    private $accessor;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var ExpressionEvaluatorInterface
     */
    private $expressionEvaluator;

    public function __construct(
        MetadataFactoryInterface $metadataFactory,
        HandlerRegistryInterface $handlerRegistry,
        ?AccessorStrategyInterface $accessor = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?ExpressionEvaluatorInterface $expressionEvaluator = null
    ) {
        $this->metadataFactory = $metadataFactory;
        $this->handlerRegistry = $handlerRegistry;
        $this->accessor = $accessor ?: new DefaultAccessorStrategy();
        $this->dispatcher = $dispatcher ?: new EventDispatcher();
        $this->expressionEvaluator = $expressionEvaluator;
    }

    public function getGraphNavigator(): GraphNavigatorInterface
    {
        return new SerializationGraphNavigator($this->metadataFactory, $this->handlerRegistry, $this->accessor, $this->dispatcher, $this->expressionEvaluator);
    }
}
