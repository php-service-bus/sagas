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

use ServiceBus\AnnotationsReader\Annotation\ClassLevel;
use ServiceBus\AnnotationsReader\Annotation\MethodLevel;
use ServiceBus\AnnotationsReader\DoctrineReader;
use ServiceBus\AnnotationsReader\Reader;
use function ServiceBus\Sagas\createEventListenerName;
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
    /** @var Reader */
    private $annotationReader;

    /** @var EventListenerProcessorFactory */
    private $eventListenerProcessorFactory;

    /**
     * @throws \ServiceBus\AnnotationsReader\Exceptions\ParserConfigurationError
     */
    public function __construct(
        EventListenerProcessorFactory $eventListenerProcessorFactory,
        ?Reader $annotationReader = null
    ) {
        $this->eventListenerProcessorFactory = $eventListenerProcessorFactory;
        $this->annotationReader              = $annotationReader ?? new DoctrineReader(null, ['psalm']);
    }

    /**
     * {@inheritdoc}
     */
    public function load(string $sagaClass): SagaConfiguration
    {
        try
        {
            $annotations = $this->annotationReader->extract($sagaClass);

            $sagaMetadata = self::createSagaMetadata(
                $sagaClass,
                self::searchSagaHeader($sagaClass, $annotations->classLevelCollection)
            );

            $handlersCollection = $this->collectSagaEventHandlers($annotations->methodLevelCollection, $sagaMetadata);

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
     * @throws \ServiceBus\Sagas\Configuration\Annotations\Exceptions\InvalidSagaEventListenerMethod
     */
    private function collectSagaEventHandlers(\SplObjectStorage $methodLevelAnnotations, SagaMetadata $sagaMetadata): \SplObjectStorage
    {
        $handlersCollection = new \SplObjectStorage();

        /** @var MethodLevel $methodAnnotation */
        foreach ($methodLevelAnnotations as $methodAnnotation)
        {
            if ($methodAnnotation->annotation instanceof SagaEventListener)
            {
                $handlersCollection->attach(
                    $this->createMessageHandler($methodAnnotation, $sagaMetadata)
                );
            }
        }

        return $handlersCollection;
    }

    /**
     * Create a saga event handler.
     *
     * @throws \ServiceBus\Sagas\Configuration\Annotations\Exceptions\InvalidSagaEventListenerMethod
     */
    private function createMessageHandler(MethodLevel $methodAnnotation, SagaMetadata $sagaMetadata): MessageHandler
    {
        /** @var SagaEventListener $listenerAnnotation */
        $listenerAnnotation = $methodAnnotation->annotation;

        $listenerOptions = $listenerAnnotation->hasContainingIdProperty()
            ? SagaListenerOptions::withCustomContainingIdentifierProperty(
                (string) $listenerAnnotation->containingIdSource,
                (string) $listenerAnnotation->containingIdProperty,
                $sagaMetadata,
                $listenerAnnotation->description
            )
            : SagaListenerOptions::withGlobalOptions($sagaMetadata, $listenerAnnotation->description);

        $eventListenerReflectionMethod = $methodAnnotation->reflectionMethod;

        $eventClass = $this->extractEventClass($eventListenerReflectionMethod);

        $expectedMethodName = createEventListenerName($eventClass);

        if ($expectedMethodName === $eventListenerReflectionMethod->name)
        {
            $reflectionMethod = $methodAnnotation->reflectionMethod;

            /** @var callable $processor */
            $processor = $this->eventListenerProcessorFactory->createProcessor(
                $eventClass,
                $listenerOptions
            );

            $closure = \Closure::fromCallable($processor);

            /** @psalm-var \Closure(object, \ServiceBus\Common\Context\ServiceBusContext):\Amp\Promise<void> $closure */

            return new MessageHandler($eventClass, $closure, $reflectionMethod, $listenerOptions);
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

        if (\count($reflectionParameters) === 1)
        {
            $firstArgumentClass = isset($reflectionParameters[0]) && $reflectionParameters[0]->getClass() !== null
                ? $reflectionParameters[0]->getClass()
                : null;

            if ($firstArgumentClass !== null)
            {
                /** @var \ReflectionClass $reflectionClass */
                $reflectionClass = $reflectionParameters[0]->getClass();

                /**
                 * @noinspection OneTimeUseVariablesInspection PhpUnnecessaryLocalVariableInspection
                 * @psalm-var    class-string $eventClass
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
             $sagaHeader->idClass === null ||
             \class_exists((string) $sagaHeader->idClass) === false
        ) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'In the meta data of the saga "%s" an incorrect value of the "idClass"',
                    $sagaClass
                )
            );
        }

        $containingIdentifierSource = SagaMetadata::CORRELATION_ID_SOURCE_EVENT;

        if ($sagaHeader->containingIdSource !== null &&  (string) $sagaHeader->containingIdSource !== '')
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
    private static function searchSagaHeader(string $sagaClass, \SplObjectStorage $classLevelAnnotations): SagaHeader
    {
        /** @var ClassLevel $annotation */
        foreach ($classLevelAnnotations as $annotation)
        {
            $annotationObject = $annotation->annotation;

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
