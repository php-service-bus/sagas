<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Configuration\Attributes;

use ServiceBus\AnnotationsReader\Attribute\ClassLevel;
use ServiceBus\AnnotationsReader\Attribute\MethodLevel;
use ServiceBus\AnnotationsReader\AttributesReader;
use ServiceBus\AnnotationsReader\Reader;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Sagas\Configuration\Attributes\Exceptions\InvalidSagaHandlerMethod;
use ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration;
use ServiceBus\Sagas\Configuration\MessageProcessor\SagaMessageProcessorFactory;
use ServiceBus\Sagas\Configuration\Metadata\SagaConfiguration;
use ServiceBus\Sagas\Configuration\Metadata\SagaHandlerOptions;
use ServiceBus\Sagas\Configuration\Metadata\SagaMetadata;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use function ServiceBus\Sagas\createEventListenerName;

final class SagaAttributeBasedConfigurationLoader implements SagaConfigurationLoader
{
    /**
     * @var Reader
     */
    private $attributesReader;

    /**
     * @var SagaMessageProcessorFactory
     */
    private $eventListenerProcessorFactory;

    public function __construct(
        SagaMessageProcessorFactory $eventListenerProcessorFactory,
        ?Reader                     $attributesReader = null
    ) {
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

            return new SagaConfiguration(
                metadata: $sagaMetadata,
                initialCommandHandler: $this->createMessageHandler(
                    methodLevelAttribute: $this->findInitialCommandHandlerAttribute(
                        sagaClass: $sagaClass,
                        methodLevelAttributes: $attributes->methodLevelCollection
                    ),
                    sagaMetadata: $sagaMetadata,
                    handlerType: SagaMessageHandlerType::INITIAL_COMMAND_HANDLER
                ),
                listenerCollection: $this->collectSagaEventHandlers(
                    methodLevelAttributes: $attributes->methodLevelCollection,
                    sagaMetadata: $sagaMetadata
                )
            );
        }
        catch (\Throwable $throwable)
        {
            throw InvalidSagaConfiguration::fromThrowable($throwable);
        }
    }

    /**
     * Collect a saga event handlers.
     *
     * @psalm-param \SplObjectStorage<MethodLevel, null> $methodLevelAttributes
     *
     * @psalm-return \SplObjectStorage<MessageHandler, null>
     *
     * @throws \ServiceBus\Sagas\Configuration\Attributes\Exceptions\InvalidSagaHandlerMethod
     */
    private function collectSagaEventHandlers(
        \SplObjectStorage $methodLevelAttributes,
        SagaMetadata      $sagaMetadata
    ): \SplObjectStorage {
        /** @psalm-var \SplObjectStorage<MessageHandler, null> $handlersCollection */
        $handlersCollection = new \SplObjectStorage();

        /** @var MethodLevel $methodLevelAttribute */
        foreach ($methodLevelAttributes as $methodLevelAttribute)
        {
            if ($methodLevelAttribute->attribute instanceof SagaEventListener)
            {
                $handlersCollection->attach(
                    $this->createMessageHandler(
                        methodLevelAttribute: $methodLevelAttribute,
                        sagaMetadata: $sagaMetadata,
                        handlerType: SagaMessageHandlerType::EVENT_LISTENER
                    )
                );
            }
        }

        return $handlersCollection;
    }

    /**
     * Create a saga event handler.
     *
     * @throws \ServiceBus\Sagas\Configuration\Attributes\Exceptions\InvalidSagaHandlerMethod
     */
    private function createMessageHandler(
        MethodLevel            $methodLevelAttribute,
        SagaMetadata           $sagaMetadata,
        SagaMessageHandlerType $handlerType
    ): MessageHandler {
        /** @var SagaEventListener|SagaInitialHandler $attribute */
        $attribute = $methodLevelAttribute->attribute;

        $options = $attribute->hasContainingIdProperty()
            ? SagaHandlerOptions::withCustomContainingIdentifierProperty(
                containingIdentifierSource: (string) $attribute->containingIdSource,
                containingIdentifierProperty: (string) $attribute->containingIdProperty,
                metadata: $sagaMetadata,
                description: $attribute->description
            )
            : SagaHandlerOptions::withGlobalOptions(
                metadata: $sagaMetadata,
                description: $attribute->description
            );

        $reflectionMethod = $methodLevelAttribute->reflectionMethod;

        $messageClass = $this->extractMessageClass($reflectionMethod);

        $expectedMethodName = match ($handlerType)
        {
            SagaMessageHandlerType::INITIAL_COMMAND_HANDLER => self::INITIAL_COMMAND_METHOD,
            SagaMessageHandlerType::EVENT_LISTENER => createEventListenerName($messageClass)
        };

        if ($expectedMethodName === $reflectionMethod->name)
        {
            /** @var callable $processor */
            $processor = match ($handlerType)
            {
                SagaMessageHandlerType::INITIAL_COMMAND_HANDLER => $this->eventListenerProcessorFactory->createHandler(
                    command: $messageClass,
                    handlerOptions: $options
                ),
                SagaMessageHandlerType::EVENT_LISTENER => $this->eventListenerProcessorFactory->createListener(
                    event: $messageClass,
                    handlerOptions: $options
                )
            };

            $closure = $processor(...);

            /** @psalm-var \Closure(object, \ServiceBus\Common\Context\ServiceBusContext):\Amp\Promise<void> $closure */

            return new MessageHandler(
                messageClass: $messageClass,
                closure: $closure,
                reflectionMethod: $reflectionMethod,
                options: $options
            );
        }

        throw InvalidSagaHandlerMethod::unexpectedName($expectedMethodName, $reflectionMethod->name);
    }

    /**
     * Search for an event/command class among method arguments.
     *
     * @psalm-return class-string
     *
     * @throws \ServiceBus\Sagas\Configuration\Attributes\Exceptions\InvalidSagaHandlerMethod
     */
    private function extractMessageClass(\ReflectionMethod $reflectionMethod): string
    {
        $reflectionParameters = $reflectionMethod->getParameters();

        $firstArgumentType = isset($reflectionParameters[0]) && $reflectionParameters[0]->getType() !== null
            ? $reflectionParameters[0]->getType()
            : null;

        if ($firstArgumentType !== null)
        {
            /** @var \ReflectionNamedType $reflectionType */
            $reflectionType = $reflectionParameters[0]->getType();

            /** @psalm-var class-string $messageClass */
            $messageClass = $reflectionType->getName();

            /** @psalm-suppress RedundantConditionGivenDocblockType */
            if (\class_exists($messageClass))
            {
                return $messageClass;
            }
        }

        throw InvalidSagaHandlerMethod::wrongEventArgument($reflectionMethod);
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
        if (\class_exists($sagaHeader->idClass) === false)
        {
            throw new \InvalidArgumentException(
                \sprintf(
                    'In the metadata of the saga "%s" an incorrect value of the "idClass"',
                    $sagaClass
                )
            );
        }

        return new SagaMetadata(
            sagaClass: $sagaClass,
            identifierClass: $sagaHeader->idClass,
            containingIdentifierSource: $sagaHeader->containingIdSource,
            containingIdentifierProperty: $sagaHeader->containingIdProperty,
            expireDateModifier: $sagaHeader->expireDateModifier
        );
    }

    /**
     * Search saga initial handler.
     *
     * @psalm-param class-string                         $sagaClass
     * @psalm-param \SplObjectStorage<MethodLevel, null> $methodLevelAttributes
     *
     * @throws \InvalidArgumentException
     */
    private function findInitialCommandHandlerAttribute(
        string            $sagaClass,
        \SplObjectStorage $methodLevelAttributes
    ): MethodLevel {
        /** @var MethodLevel[] $commandHandlersAttributes */
        $commandHandlersAttributes = \array_filter(
            \array_map(
                static function (MethodLevel $attribute): ?MethodLevel
                {
                    return $attribute->attribute instanceof SagaInitialHandler ? $attribute : null;
                },
                \iterator_to_array($methodLevelAttributes)
            )
        );

        if (\count($commandHandlersAttributes) === 1)
        {
            return \end($commandHandlersAttributes);
        }

        throw new \InvalidArgumentException(
            \sprintf('The `%s` saga should (may) contain 1 initial command handler', $sagaClass)
        );
    }

    /**
     * Search saga header information.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     * @psalm-param \SplObjectStorage<ClassLevel, null>  $classLevelAttributes
     *
     * @throws \InvalidArgumentException
     */
    private static function searchSagaHeader(string $sagaClass, \SplObjectStorage $classLevelAttributes): SagaHeader
    {
        /** @var ClassLevel $attributes */
        foreach ($classLevelAttributes as $attributes)
        {
            $attributeObject = $attributes->attribute;

            if ($attributeObject instanceof SagaHeader)
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
