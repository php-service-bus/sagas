<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas\Configuration;

use ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;
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
     */
    public function __construct(
        string              $forEvent,
        SagasStore          $sagasStore,
        SagaListenerOptions $sagaListenerOptions,
        MutexFactory        $mutexFactory
    ) {
        $this->forEvent            = $forEvent;
        $this->sagasStore          = $sagasStore;
        $this->sagaListenerOptions = $sagaListenerOptions;
        $this->mutexFactory        = $mutexFactory;
    }

    public function event(): string
    {
        return $this->forEvent;
    }

    public function __invoke(object $event, ServiceBusContext $context): Promise
    {
        return call(
            function () use ($event, $context): \Generator
            {
                $id = $this->obtainSagaId(
                    event: $event,
                    headers: $context->headers()
                );

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
                        $context->logger()->debug($description);
                    }

                    invokeReflectionMethod($saga, 'applyEvent', $event);

                    if ($stateHash !== $saga->stateHash())
                    {
                        /**
                         * @var object[] $messages
                         */
                        $messages = invokeReflectionMethod($saga, 'messages');

                        yield $this->sagasStore->update($saga);
                        yield $context->deliveryBulk($messages);
                    }
                }
                finally
                {
                    yield $lock->release();
                }
            }
        );
    }

    /**
     * Search and instantiate saga identifier.
     *
     * @psalm-param array<string, int|float|string|null> $headers
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    private function obtainSagaId(object $event, array $headers): SagaId
    {
        return SagaMetadata::CORRELATION_ID_SOURCE_EVENT === $this->sagaListenerOptions->containingIdentifierSource()
            ? $this->searchSagaIdentifierInEvent($event)
            : $this->searchSagaIdentifierInHeaders($headers);
    }

    /**
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed
     */
    private function loadSaga(SagaId $id): \Generator
    {
        /** @var \ServiceBus\Sagas\Saga|null $saga */
        $saga = yield $this->sagasStore->obtain($id);

        if ($saga === null)
        {
            throw ChangeSagaStateFailed::applyEventFailed(
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

        throw ChangeSagaStateFailed::applyEventFailed(
            \sprintf('Attempt to apply event to completed saga (ID: %s)', $id->toString())
        );
    }

    /**
     * @psalm-param array<string, int|float|string|null> $headers
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    private function searchSagaIdentifierInHeaders(array $headers): SagaId
    {
        $identifierClass = $this->getSagaIdentifierClass();

        $headerKeyValue = $headers[$this->sagaListenerOptions->containingIdentifierProperty()] ?? '';

        if ((string) $headerKeyValue !== '')
        {
            return self::identifierInstantiator(
                $identifierClass,
                (string) $headerKeyValue,
                $this->sagaListenerOptions->sagaClass()
            );
        }

        throw InvalidSagaIdentifier::headerKeyCantBeEmpty($this->sagaListenerOptions->containingIdentifierProperty());
    }

    /**
     * Search saga identifier in the event payload.
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     * @throws \Throwable Reflection property not found
     */
    private function searchSagaIdentifierInEvent(object $event): SagaId
    {
        $identifierClass = $this->getSagaIdentifierClass();

        $propertyName = \lcfirst($this->sagaListenerOptions->containingIdentifierProperty());

        try
        {
            /** @psalm-suppress MixedAssignment */
            $propertyValue = self::readEventProperty($event, $propertyName);

            if (\is_object($propertyValue) && \method_exists($propertyValue, 'toString'))
            {
                $propertyValue = (string) $propertyValue->toString();
            }
        }
        catch (\Throwable)
        {
            throw InvalidSagaIdentifier::propertyNotFound($propertyName, $event);
        }

        if ($propertyValue !== '')
        {
            return self::identifierInstantiator(
                $identifierClass,
                (string) $propertyValue,
                $this->sagaListenerOptions->sagaClass()
            );
        }

        throw InvalidSagaIdentifier::propertyCantBeEmpty($propertyName, $event);
    }

    /**
     * @psalm-return class-string<\ServiceBus\Sagas\SagaId>
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    private function getSagaIdentifierClass(): string
    {
        $identifierClass = $this->sagaListenerOptions->identifierClass();

        if (\class_exists($identifierClass))
        {
            return $identifierClass;
        }

        throw InvalidSagaIdentifier::typeNotFound($identifierClass, $this->sagaListenerOptions->sagaClass());
    }

    /**
     * Create identifier instance.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\SagaId> $idClass
     * @psalm-param class-string<\ServiceBus\Sagas\Saga>   $sagaClass
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    private static function identifierInstantiator(string $idClass, string $idValue, string $sagaClass): SagaId
    {
        /** @var object|SagaId $identifier */
        $identifier = new $idClass($idValue, $sagaClass);

        if ($identifier instanceof SagaId)
        {
            return $identifier;
        }

        throw InvalidSagaIdentifier::wrongType($identifier);
    }

    /**
     * Read event property value.
     *
     * @return mixed
     *
     * @throws \Throwable Reflection property not found
     */
    private static function readEventProperty(object $event, string $propertyName): mixed
    {
        return $event->{$propertyName} ?? readReflectionPropertyValue($event, $propertyName);
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
}
