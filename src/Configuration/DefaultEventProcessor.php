<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration;

use function Amp\call;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\readReflectionPropertyValue;
use Amp\Promise;
use Psr\Log\LogLevel;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Mutex\Lock;
use ServiceBus\Mutex\MutexFactory;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\Store\SagasStore;

/**
 *
 */
final class DefaultEventProcessor implements EventProcessor
{
    /**
     * The event for which the handler is registered.
     *
     * @psalm-var class-string
     *
     * @var string
     */
    private $forEvent;

    /**
     * @var SagasStore
     */
    private $sagasStore;

    /**
     * Listener options.
     *
     * @var SagaListenerOptions
     */
    private $sagaListenerOptions;

    /**
     * @var MutexFactory
     */
    private $mutexFactory;

    /**
     * @psalm-param class-string $forEvent
     *
     * @param string              $forEvent
     * @param SagasStore          $sagasStore
     * @param SagaListenerOptions $sagaListenerOptions
     * @param MutexFactory        $mutexFactory
     */
    public function __construct(
        string $forEvent,
        SagasStore $sagasStore,
        SagaListenerOptions $sagaListenerOptions,
        MutexFactory $mutexFactory
    )
    {
        $this->forEvent            = $forEvent;
        $this->sagasStore          = $sagasStore;
        $this->sagaListenerOptions = $sagaListenerOptions;
        $this->mutexFactory        = $mutexFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function event(): string
    {
        return $this->forEvent;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(object $event, ServiceBusContext $context): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(object $event, ServiceBusContext $context): \Generator
            {
                try
                {
                    /** @var \ServiceBus\Sagas\Saga $saga */
                    $saga = yield from $this->loadSaga($event, $context->headers());

                    $mutexKey = \sha1($saga->id()->id . $saga->id()->sagaClass);

                    /** @var Lock $lock */
                    $lock = yield $this->mutexFactory->create($mutexKey)->acquire();

                    invokeReflectionMethod($saga, 'applyEvent', $event);

                    /**
                     * @var object[] $commands
                     * @psalm-var array<int, object> $commands
                     */
                    $commands = invokeReflectionMethod($saga, 'firedCommands');

                    /**
                     * @var object[] $events
                     * @psalm-var array<int, object> $events
                     */
                    $events = invokeReflectionMethod($saga, 'raisedEvents');

                    yield $this->sagasStore->update($saga);

                    yield from $this->deliveryMessages($context, $commands, $events);

                    $lock->release();
                }
                catch(\Throwable $throwable)
                {
                    $context->logContextMessage(
                        'Error in applying event to saga: "{throwableMessage}"',
                        [
                            'eventClass'       => \get_class($event),
                            'throwableMessage' => $throwable->getMessage(),
                            'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                        ],
                        LogLevel::ERROR
                    );
                }
                finally
                {
                    unset($lock);
                }
            },
            $event,
            $context
        );
    }

    /**
     * Delivery events & commands to message bus.
     *
     * @psalm-param array<int, object> $commands
     * @psalm-param array<int, object> $events
     *
     * @param ServiceBusContext $context
     * @param object[]          $commands
     * @param object[]          $events
     *
     * @return \Generator Doesn't return result
     */
    private function deliveryMessages(ServiceBusContext $context, array $commands, array $events): \Generator
    {
        $promises = [];

        /** @var object $command */
        foreach($commands as $command)
        {
            $promises[] = $context->delivery($command);
        }

        /** @var object $event */
        foreach($events as $event)
        {
            $promises[] = $context->delivery($event);
        }

        yield $promises;
    }

    /**
     * @psalm-param array<string, string|float|int> $headers
     *
     * @param object $event
     * @param array  $headers
     *
     * @return \Generator
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed
     * @throws \RuntimeException
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     */
    private function loadSaga(object $event, array $headers): \Generator
    {
        /** @var \DateTimeImmutable $currentDatetime */
        $currentDatetime = datetimeInstantiator('NOW');

        $id = SagaMetadata::CORRELATION_ID_SOURCE_EVENT === $this->sagaListenerOptions->containingIdentifierSource()
            ? $this->searchSagaIdentifierInEvent($event)
            : $this->searchSagaIdentifierInHeaders($headers);

        /** @var \ServiceBus\Sagas\Saga|null $saga */
        $saga = yield $this->sagasStore->obtain($id);

        if(null === $saga)
        {
            throw new \RuntimeException(
                \sprintf(
                    'Attempt to apply event to non-existent saga (ID: %s)',
                    $id
                )
            );
        }

        /** Non-expired saga */
        if($saga->expireDate() > $currentDatetime)
        {
            unset($currentDatetime, $id);

            return $saga;
        }

        throw new \RuntimeException(
            \sprintf('Attempt to apply event to completed saga (ID: %s)', $id)
        );
    }

    /**
     * @psalm-param array<string, string|float|int> $headers
     *
     * @param array $headers
     *
     * @return SagaId
     *
     * @throws \RuntimeException
     *
     */
    private function searchSagaIdentifierInHeaders(array $headers): SagaId
    {
        $identifierClass = $this->getSagaIdentifierClass();

        $headerKeyValue = $headers[$this->sagaListenerOptions->containingIdentifierProperty()] ?? '';

        if('' !== (string) $headerKeyValue)
        {
            /** @var SagaId $id */
            $id = self::identifierInstantiator(
                $identifierClass,
                (string) $headerKeyValue,
                $this->sagaListenerOptions->sagaClass()
            );

            return $id;
        }

        throw  new \RuntimeException(
            \sprintf(
                'The value of the "%s" header key can\'t be empty, since it is the saga id',
                $this->sagaListenerOptions->containingIdentifierProperty()
            )
        );
    }

    /**
     * Search saga identifier in the event payload.
     *
     * @param object $event
     *
     * @return SagaId
     *
     * @throws \RuntimeException
     *
     */
    private function searchSagaIdentifierInEvent(object $event): SagaId
    {
        $identifierClass = $this->getSagaIdentifierClass();

        $propertyName = \lcfirst($this->sagaListenerOptions->containingIdentifierProperty());

        try
        {
            $propertyValue = self::readEventProperty($event, $propertyName);
        }
        catch(\Throwable $throwable)
        {
            throw new \RuntimeException(
                \sprintf(
                    'A property that contains an identifier ("%s") was not found in class "%s"',
                    $propertyName,
                    \get_class($event)
                )
            );
        }

        if('' !== $propertyValue)
        {
            /** @var SagaId $id */
            $id = self::identifierInstantiator(
                $identifierClass,
                $propertyValue,
                $this->sagaListenerOptions->sagaClass()
            );

            return $id;
        }

        throw  new \RuntimeException(
            \sprintf(
                'The value of the "%s" property of the "%s" event can\'t be empty, since it is the saga id',
                $propertyName,
                \get_class($event)
            )
        );
    }

    /**
     * @psalm-return class-string<\ServiceBus\Sagas\SagaId>
     *
     * @return string
     *
     *
     * @throws \RuntimeException
     *
     */
    private function getSagaIdentifierClass(): string
    {
        $identifierClass = $this->sagaListenerOptions->identifierClass();

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if(true === \class_exists($identifierClass))
        {
            return $identifierClass;
        }

        throw new \RuntimeException(
            \sprintf(
                'Identifier class "%s" specified in the saga "%s" not found',
                $identifierClass,
                $this->sagaListenerOptions->sagaClass()
            )
        );
    }

    /**
     * Create identifier instance.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $idClass
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     *
     * @param string $idClass
     * @param string $idValue
     * @param string $sagaClass
     *
     * @return SagaId
     *
     * @throws \RuntimeException
     *
     */
    private static function identifierInstantiator(string $idClass, string $idValue, string $sagaClass): SagaId
    {
        /** @var object|SagaId $identifier */
        $identifier = new $idClass($idValue, $sagaClass);

        if($identifier instanceof SagaId)
        {
            return $identifier;
        }

        throw new \RuntimeException(
            \sprintf(
                'Saga identifier mus be type of "%s". "%s" type specified',
                SagaId::class,
                \get_class($identifier)
            )
        );
    }

    /**
     * Read event property value.
     *
     * @param object $event
     * @param string $propertyName
     *
     * @return string
     *
     * @throws \Throwable Reflection property not found
     *
     */
    private static function readEventProperty(object $event, string $propertyName): string
    {
        if(true === isset($event->{$propertyName}))
        {
            return (string) $event->{$propertyName};
        }

        return (string) readReflectionPropertyValue($event, $propertyName);
    }
}
