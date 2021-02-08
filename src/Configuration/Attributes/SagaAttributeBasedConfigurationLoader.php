<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Configuration\Attributes;

use ServiceBus\AnnotationsReader\Attribute\ClassLevel;
use ServiceBus\AnnotationsReader\Attribute\MethodLevel;
use ServiceBus\AnnotationsReader\AttributesReader;
use ServiceBus\AnnotationsReader\Reader;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Sagas\Configuration\Attributes\Exceptions\InvalidSagaEventListenerMethod;
use ServiceBus\Sagas\Configuration\EventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration;
use ServiceBus\Sagas\Configuration\SagaConfiguration;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use ServiceBus\Sagas\Configuration\SagaListenerOptions;
use ServiceBus\Sagas\Configuration\SagaMetadata;
use function ServiceBus\Sagas\createEventListenerName;

/**
 *
 */
final class SagaAttributeBasedConfigurationLoader implements SagaConfigurationLoader
{
    /**
     * @var Reader
     */
    private $attributesReader;

    /**
     * @var EventListenerProcessorFactory
     */
    private $eventListenerProcessorFactory;

    public function __construct(
        EventListenerProcessorFactory $eventListenerProcessorFactory,
        ?Reader $attributesReader = null
    )
    {
        $this->eventListenerProcessorFactory = $eventListenerProcessorFactory;
        $this->attributesReader              = $attributesReader ?? new AttributesReader();
    }

    public function load(string $sagaClass): SagaConfiguration
    {
        try
        {
            $attributes = $this->attributesReader->extract($sagaClass);

            $sagaHeader = self::searchSagaHeader(
                sagaClass: $sagaClass,
                classLevelAttributes: $attributes->classLevelCollection
            );

            $sagaMetadata = self::createSagaMetadata(
                sagaClass: $sagaClass,
                sagaHeader: $sagaHeader
            );

            $handlersCollection = $this->collectSagaEventHandlers(
                methodLevelAttributes: $attributes->methodLevelCollection,
                sagaMetadata: $sagaMetadata
            );

            return new SagaConfiguration(
                sagaMetadata: $sagaMetadata,
                handlerCollection: $handlersCollection
            );
        }
        catch(\Throwable $throwable)
        {
            throw InvalidSagaConfiguration::fromThrowable($throwable);
        }
    }

    /**
     * Collect a saga event handlers.
     *
     * @throws \ServiceBus\Sagas\Configuration\Attributes\Exceptions\InvalidSagaEventListenerMethod
     */
    private function collectSagaEventHandlers(
        \SplObjectStorage $methodLevelAttributes,
        SagaMetadata $sagaMetadata
    ): \SplObjectStorage
    {
        /** @psalm-var \SplObjectStorage<MessageHandler, int> $handlersCollection */
        $handlersCollection = new \SplObjectStorage();

        /** @var MethodLevel $methodLevelAttribute */
        foreach($methodLevelAttributes as $methodLevelAttribute)
        {
            if($methodLevelAttribute->attribute instanceof SagaEventListener)
            {
                $handlersCollection->attach(
                    $this->createMessageHandler(
                        methodLevelAttribute: $methodLevelAttribute,
                        sagaMetadata: $sagaMetadata
                    )
                );
            }
        }

        return $handlersCollection;
    }

    /**
     * Create a saga event handler.
     *
     * @throws \ServiceBus\Sagas\Configuration\Attributes\Exceptions\InvalidSagaEventListenerMethod
     */
    private function createMessageHandler(MethodLevel $methodLevelAttribute, SagaMetadata $sagaMetadata): MessageHandler
    {
        /** @var SagaEventListener $listenerAttribute */
        $listenerAttribute = $methodLevelAttribute->attribute;

        $listenerOptions = $listenerAttribute->hasContainingIdProperty()
            ? SagaListenerOptions::withCustomContainingIdentifierProperty(
                containingIdentifierSource: (string) $listenerAttribute->containingIdSource,
                containingIdentifierProperty: (string) $listenerAttribute->containingIdProperty,
                metadata: $sagaMetadata,
                description: $listenerAttribute->description
            )
            : SagaListenerOptions::withGlobalOptions(
                metadata: $sagaMetadata,
                description: $listenerAttribute->description
            );

        $eventListenerReflectionMethod = $methodLevelAttribute->reflectionMethod;

        $eventClass = $this->extractEventClass($eventListenerReflectionMethod);

        $expectedMethodName = createEventListenerName($eventClass);

        if($expectedMethodName === $eventListenerReflectionMethod->name)
        {
            $reflectionMethod = $methodLevelAttribute->reflectionMethod;

            /** @var callable $processor */
            $processor = $this->eventListenerProcessorFactory->createProcessor(
                event: $eventClass,
                listenerOptions: $listenerOptions
            );

            $closure = \Closure::fromCallable($processor);

            /** @psalm-var \Closure(object, \ServiceBus\Common\Context\ServiceBusContext):\Amp\Promise<void> $closure */

            return new MessageHandler(
                messageClass: $eventClass,
                closure: $closure,
                reflectionMethod: $reflectionMethod,
                options: $listenerOptions
            );
        }

        throw InvalidSagaEventListenerMethod::unexpectedName($expectedMethodName, $eventListenerReflectionMethod->name);
    }

    /**
     * Search for an event class among method arguments.
     *
     * @psalm-return class-string
     *
     * @throws \ServiceBus\Sagas\Configuration\Attributes\Exceptions\InvalidSagaEventListenerMethod
     */
    private function extractEventClass(\ReflectionMethod $reflectionMethod): string
    {
        $reflectionParameters = $reflectionMethod->getParameters();

        if(\count($reflectionParameters) === 1)
        {
            $firstArgumentType = isset($reflectionParameters[0]) && $reflectionParameters[0]->getType() !== null
                ? $reflectionParameters[0]->getType()
                : null;

            if($firstArgumentType !== null)
            {
                /** @var \ReflectionNamedType $reflectionType */
                $reflectionType = $reflectionParameters[0]->getType();

                /**
                 * @psalm-var class-string $eventClass
                 */
                $eventClass = $reflectionType->getName();

                if(\class_exists($eventClass))
                {
                    return $eventClass;
                }
            }

            throw InvalidSagaEventListenerMethod::wrongEventArgument($reflectionMethod);
        }

        throw InvalidSagaEventListenerMethod::tooManyArguments($reflectionMethod);
    }

    /**
     * Collect metadata information.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     *
     * @throws \InvalidArgumentException
     */
    private static function createSagaMetadata(string $sagaClass, SagaHeader $sagaHeader): SagaMetadata
    {
        if(\class_exists($sagaHeader->idClass) === false)
        {
            throw new \InvalidArgumentException(
                \sprintf(
                    'In the meta data of the saga "%s" an incorrect value of the "idClass"',
                    $sagaClass
                )
            );
        }

        $containingIdentifierSource = SagaMetadata::CORRELATION_ID_SOURCE_EVENT;

        if($sagaHeader->containingIdSource !== '')
        {
            $containingIdentifierSource = \strtolower($sagaHeader->containingIdSource);
        }

        return new SagaMetadata(
            sagaClass: $sagaClass,
            identifierClass: $sagaHeader->idClass,
            containingIdentifierSource: $containingIdentifierSource,
            containingIdentifierProperty: $sagaHeader->containingIdProperty,
            expireDateModifier: (string) $sagaHeader->expireDateModifier
        );
    }

    /**
     * Search saga header information.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     *
     * @throws \InvalidArgumentException
     */
    private static function searchSagaHeader(string $sagaClass, \SplObjectStorage $classLevelAttributes): SagaHeader
    {
        /** @var ClassLevel $attributes */
        foreach($classLevelAttributes as $attributes)
        {
            $attributeObject = $attributes->attribute;

            if($attributeObject instanceof SagaHeader)
            {
                return $attributeObject;
            }
        }

        throw new \InvalidArgumentException(
            \sprintf(
                'Could not find class-level attributes "%s" in "%s"',
                SagaHeader::class,
                $sagaClass
            )
        );
    }
}
