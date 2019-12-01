<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration\Annotations;

use function ServiceBus\Sagas\createEventListenerName;
use ServiceBus\AnnotationsReader\Annotation;
use ServiceBus\AnnotationsReader\AnnotationCollection;
use ServiceBus\AnnotationsReader\AnnotationsReader;
use ServiceBus\AnnotationsReader\DoctrineAnnotationsReader;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Sagas\Configuration\Annotations\Exceptions\InvalidSagaEventListenerMethod;
use ServiceBus\Sagas\Configuration\EventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration;
use ServiceBus\Sagas\Configuration\SagaConfiguration;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use ServiceBus\Sagas\Configuration\SagaListenerOptions;
use ServiceBus\Sagas\Configuration\SagaMetadata;

/**
 * Annotation based saga configuration loader.
 */
final class SagaAnnotationBasedConfigurationLoader implements SagaConfigurationLoader
{
    private const SUPPORTED_TYPES = [
        SagaHeader::class,
        SagaEventListener::class,
    ];

    private AnnotationsReader $annotationReader;

    private EventListenerProcessorFactory $eventListenerProcessorFactory;

    /**
     * @throws \ServiceBus\AnnotationsReader\Exceptions\ParserConfigurationError
     */
    public function __construct(
        EventListenerProcessorFactory $eventListenerProcessorFactory,
        ?AnnotationsReader $annotationReader = null
    ) {
        $this->eventListenerProcessorFactory = $eventListenerProcessorFactory;
        $this->annotationReader              = $annotationReader ?? new DoctrineAnnotationsReader(null, ['psalm']);
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $sagaClass): SagaConfiguration
    {
        try
        {
            /** @psalm-var class-string<\ServiceBus\Sagas\Saga> $sagaClass */
            $annotations = $this->annotationReader
                ->extract($sagaClass)
                ->filter(
                    static function (Annotation $annotation): ?Annotation
                    {
                        $annotationClass = \get_class($annotation->annotationObject);

                        return true === \in_array($annotationClass, self::SUPPORTED_TYPES, true)
                            ? $annotation
                            : null;
                    }
                );

            $sagaMetadata = self::createSagaMetadata(
                $sagaClass,
                self::searchSagaHeader($sagaClass, $annotations)
            );

            $handlersCollection = $this->collectSagaEventHandlers($annotations, $sagaMetadata);

            return new SagaConfiguration($sagaMetadata, $handlersCollection);
        }
        catch (\Throwable $throwable)
        {
            throw InvalidSagaConfiguration::fromThrowable($throwable);
        }
    }

    /**
     * Collect a saga event handlers.
     *
     * @psalm-return \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler>
     *
     * @throws \ServiceBus\Sagas\Configuration\Annotations\Exceptions\InvalidSagaEventListenerMethod
     */
    private function collectSagaEventHandlers(AnnotationCollection $annotationCollection, SagaMetadata $sagaMetadata): \SplObjectStorage
    {
        $handlersCollection = new \SplObjectStorage();

        $methodAnnotations = $annotationCollection->filter(
            static function (Annotation $annotation): ?Annotation
            {
                return $annotation->annotationObject instanceof SagaEventListener ? $annotation : null;
            }
        );

        /** @var Annotation $methodAnnotation */
        foreach ($methodAnnotations as $methodAnnotation)
        {
            $handlersCollection->attach(
                $this->createMessageHandler($methodAnnotation, $sagaMetadata)
            );
        }

        /** @psalm-var \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler> $handlersCollection */

        return $handlersCollection;
    }

    /**
     * Create a saga event handler.
     *
     * @throws \ServiceBus\Sagas\Configuration\Annotations\Exceptions\InvalidSagaEventListenerMethod
     */
    private function createMessageHandler(Annotation $annotation, SagaMetadata $sagaMetadata): MessageHandler
    {
        /** @var SagaEventListener $listenerAnnotation */
        $listenerAnnotation = $annotation->annotationObject;

        $listenerOptions = true === $listenerAnnotation->hasContainingIdProperty()
            ? SagaListenerOptions::withCustomContainingIdentifierProperty(
                (string) $listenerAnnotation->containingIdSource,
                (string) $listenerAnnotation->containingIdProperty,
                $sagaMetadata
            )
            : SagaListenerOptions::withGlobalOptions($sagaMetadata);

        /** @var \ReflectionMethod $eventListenerReflectionMethod */
        $eventListenerReflectionMethod = $annotation->reflectionMethod;

        $eventClass = $this->extractEventClass($eventListenerReflectionMethod);

        $expectedMethodName = createEventListenerName($eventClass);

        if ($expectedMethodName === $eventListenerReflectionMethod->name)
        {
            /** @var \ReflectionMethod $reflectionMethod */
            $reflectionMethod = $annotation->reflectionMethod;

            /** @var callable $processor */
            $processor = $this->eventListenerProcessorFactory->createProcessor(
                $eventClass,
                $listenerOptions
            );

            $closure = \Closure::fromCallable($processor);

            /** @psalm-var \Closure(object, \ServiceBus\Common\Context\ServiceBusContext):\Amp\Promise $closure */

            return MessageHandler::create($eventClass, $closure, $reflectionMethod, $listenerOptions);
        }

        throw InvalidSagaEventListenerMethod::unexpectedName($expectedMethodName, $eventListenerReflectionMethod->name);
    }

    /**
     * Search for an event class among method arguments.
     *
     * @psalm-return class-string
     *
     * @throws \ServiceBus\Sagas\Configuration\Annotations\Exceptions\InvalidSagaEventListenerMethod
     */
    private function extractEventClass(\ReflectionMethod $reflectionMethod): string
    {
        $reflectionParameters = $reflectionMethod->getParameters();

        if (1 === \count($reflectionParameters))
        {
            $firstArgumentClass = true === isset($reflectionParameters[0]) && null !== $reflectionParameters[0]->getClass()
                ? $reflectionParameters[0]->getClass()
                : null;

            if (null !== $firstArgumentClass)
            {
                /** @var \ReflectionClass $reflectionClass */
                $reflectionClass = $reflectionParameters[0]->getClass();

                /**
                 * @noinspection       OneTimeUseVariablesInspection PhpUnnecessaryLocalVariableInspection
                 * @psalm-var          class-string $eventClass
                 */
                $eventClass = $reflectionClass->getName();

                return $eventClass;
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
        if (
            null === $sagaHeader->idClass ||
            false === \class_exists((string) $sagaHeader->idClass)
        ) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'In the meta data of the saga "%s" an incorrect value of the "idClass"',
                    $sagaClass
                )
            );
        }

        $containingIdentifierSource = SagaMetadata::CORRELATION_ID_SOURCE_EVENT;

        if (null !== $sagaHeader->containingIdSource && '' !== (string) $sagaHeader->containingIdSource)
        {
            $containingIdentifierSource = \strtolower($sagaHeader->containingIdSource);
        }

        return new SagaMetadata(
            $sagaClass,
            $sagaHeader->idClass,
            $containingIdentifierSource,
            (string) $sagaHeader->containingIdProperty,
            (string) $sagaHeader->expireDateModifier
        );
    }

    /**
     * Search saga header information.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     *
     * @throws \InvalidArgumentException
     */
    private static function searchSagaHeader(string $sagaClass, AnnotationCollection $annotationCollection): SagaHeader
    {
        /** @var \ServiceBus\AnnotationsReader\Annotation $annotation */
        foreach ($annotationCollection->classLevelAnnotations() as $annotation)
        {
            $annotationObject = $annotation->annotationObject;

            if ($annotationObject instanceof SagaHeader)
            {
                return $annotationObject;
            }
        }

        throw new \InvalidArgumentException(
            \sprintf(
                'Could not find class-level annotation "%s" in "%s"',
                SagaHeader::class,
                $sagaClass
            )
        );
    }
}
