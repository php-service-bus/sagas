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
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\now;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Sagas\createMutexKey;
use Amp\Promise;
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

    /** @var SagasStore */
    private $sagasStore;

    /**
     * Listener options.
     *
     * @var SagaListenerOptions
     */
    private $sagaListenerOptions;

    /** @var MutexFactory */
    private $mutexFactory;

    /**
     * @psalm-param class-string $forEvent
     */
    public function __construct(
        string $forEvent,
        SagasStore $sagasStore,
        SagaListenerOptions $sagaListenerOptions,
        MutexFactory $mutexFactory
    ) {
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
        return call(
            function () use ($event, $context): \Generator
            {
                try
                {
                    $id = $this->obtainSagaId($event, $context->headers());
                }
                catch (\Throwable $throwable)
                {
                    $this->logThrowable($throwable, $context);

                    return false;
                }

                /** @var Lock $lock */
                $lock = yield from $this->setupMutex($id);

                try
                {
                    /** @var \ServiceBus\Sagas\Saga $saga */
                    $saga = yield from $this->loadSaga($id);

                    $stateHash = $saga->stateHash();

                    $description = $this->sagaListenerOptions->description();

                    if ($description !== null)
                    {
                        $context->logContextMessage($description);
                    }

                    invokeReflectionMethod($saga, 'applyEvent', $event);

                    /** The saga has not been updated */
                    if ($stateHash === $saga->stateHash())
                    {
                        return false;
                    }

                    /**
                     * @psalm-suppress MixedArgument
                     * @var object[] $messages
                     */
                    $messages = \array_merge(
                        invokeReflectionMethod($saga, 'firedCommands'),
                        invokeReflectionMethod($saga, 'raisedEvents')
                    );

                    yield $this->sagasStore->update($saga);

                    yield from $this->deliveryMessages($context, $messages);

                    return true;
                }
                catch (\Throwable $throwable)
                {
                    $this->logThrowable($throwable, $context);

                    return false;
                }
                finally
                {
                    yield $lock->release();
                }
            }
        );
    }

    /**
     * Delivery events & commands to message bus.
     *
     * @param object[] $messages
     *
     * @throws \ServiceBus\Common\Context\Exceptions\MessageDeliveryFailed
     */
    private function deliveryMessages(ServiceBusContext $context, array $messages): \Generator
    {
        $promises = [];

        /** @var object $event */
        foreach ($messages as $message)
        {
            $promises[] = $context->delivery($message);
        }

        if (\count($promises) !== 0)
        {
            yield $promises;
        }

        unset($promises);
    }

    /**
     * Search and instantiate saga identifier.
     *
     * @psalm-param array<string, string|float|int> $headers
     *
     * @throws \RuntimeException A property that contains an identifier was not found
     */
    private function obtainSagaId(object $event, array $headers): SagaId
    {
        return SagaMetadata::CORRELATION_ID_SOURCE_EVENT === $this->sagaListenerOptions->containingIdentifierSource()
            ? $this->searchSagaIdentifierInEvent($event)
            : $this->searchSagaIdentifierInHeaders($headers);
    }

    /**
     * @throws \RuntimeException
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed
     */
    private function loadSaga(SagaId $id): \Generator
    {
        /** @var \ServiceBus\Sagas\Saga|null $saga */
        $saga = yield $this->sagasStore->obtain($id);

        if ($saga === null)
        {
            throw new \RuntimeException(
                \sprintf(
                    'Attempt to apply event to non-existent saga (ID: %s)',
                    $id->toString()
                )
            );
        }

        /** Non-expired saga */
        if ($saga->expireDate() > now())
        {
            return $saga;
        }

        throw new \RuntimeException(
            \sprintf('Attempt to apply event to completed saga (ID: %s)', $id->toString())
        );
    }

    /**
     * @psalm-param array<string, string|float|int> $headers
     *
     * @throws \RuntimeException
     */
    private function searchSagaIdentifierInHeaders(array $headers): SagaId
    {
        $identifierClass = $this->getSagaIdentifierClass();

        $headerKeyValue = $headers[$this->sagaListenerOptions->containingIdentifierProperty()] ?? '';

        if ((string) $headerKeyValue !== '')
        {
            /** @var SagaId $id */
            $id = self::identifierInstantiator(
                $identifierClass,
                (string) $headerKeyValue,
                $this->sagaListenerOptions->sagaClass()
            );

            return $id;
        }

        throw new \RuntimeException(
            \sprintf(
                'The value of the "%s" header key can\'t be empty, since it is the saga id',
                $this->sagaListenerOptions->containingIdentifierProperty()
            )
        );
    }

    /**
     * Search saga identifier in the event payload.
     *
     * @throws \RuntimeException
     */
    private function searchSagaIdentifierInEvent(object $event): SagaId
    {
        $identifierClass = $this->getSagaIdentifierClass();

        $propertyName = \lcfirst($this->sagaListenerOptions->containingIdentifierProperty());

        try
        {
            $propertyValue = self::readEventProperty($event, $propertyName);
        }
        catch (\Throwable $throwable)
        {
            throw new \RuntimeException(
                \sprintf(
                    'A property that contains an identifier ("%s") was not found in class "%s"',
                    $propertyName,
                    \get_class($event)
                )
            );
        }

        if ($propertyValue !== '')
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
     * @throws \RuntimeException
     */
    private function getSagaIdentifierClass(): string
    {
        $identifierClass = $this->sagaListenerOptions->identifierClass();

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (\class_exists($identifierClass) === true)
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
     * @throws \RuntimeException
     */
    private static function identifierInstantiator(string $idClass, string $idValue, string $sagaClass): SagaId
    {
        /** @var object|SagaId $identifier */
        $identifier = new $idClass($idValue, $sagaClass);

        if ($identifier instanceof SagaId)
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
     * @throws \Throwable Reflection property not found
     */
    private static function readEventProperty(object $event, string $propertyName): string
    {
        if (isset($event->{$propertyName}) === true)
        {
            return (string) $event->{$propertyName};
        }

        return (string) readReflectionPropertyValue($event, $propertyName);
    }

    /**
     * @throws \ServiceBus\Mutex\Exceptions\SyncException
     */
    private function setupMutex(SagaId $id): \Generator
    {
        $mutexKey = createMutexKey($id);

        $mutex = $this->mutexFactory->create($mutexKey);

        /** @var \ServiceBus\Mutex\Lock $lock */
        $lock = yield $mutex->acquire();

        return $lock;
    }

    private function logThrowable(\Throwable $throwable, ServiceBusContext $context): void
    {
        $context->logContextMessage(
            $throwable->getMessage(),
            [
                'eventClass'       => $this->forEvent,
                'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine()),
                'throwableMessage' => $throwable->getMessage(),
            ]
        );
    }
}
