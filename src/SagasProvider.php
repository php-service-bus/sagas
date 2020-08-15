<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas;

use ServiceBus\Mutex\InMemory\InMemoryMutexFactory;
use ServiceBus\Sagas\Exceptions\CantSaveUnStartedSaga;
use ServiceBus\Sagas\Exceptions\ReopenFailed;
use ServiceBus\Sagas\Exceptions\SagaMetaDataNotFound;
use function Amp\call;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\now;
use function ServiceBus\Common\readReflectionPropertyValue;
use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Mutex\Lock;
use ServiceBus\Mutex\MutexFactory;
use ServiceBus\Sagas\Configuration\SagaMetadata;
use ServiceBus\Sagas\Store\Exceptions\LoadedExpiredSaga;
use ServiceBus\Sagas\Store\SagasStore;

/**
 * Sagas provider.
 */
final class SagasProvider
{
    /** @var SagasStore */
    private $sagaStore;

    /** @var MutexFactory */
    private $mutexFactory;

    /**
     * Sagas meta data.
     *
     * @psalm-var array<string, \ServiceBus\Sagas\Configuration\SagaMetadata>
     *
     * @var SagaMetadata[]
     */
    private $sagaMetaDataCollection = [];

    /** @var Lock[] */
    private $lockCollection = [];

    public function __construct(SagasStore $sagaStore, ?MutexFactory $mutexFactory = null)
    {
        $this->sagaStore    = $sagaStore;
        $this->mutexFactory = $mutexFactory ?? new InMemoryMutexFactory();
    }

    public function __destruct()
    {
        unset($this->lockCollection);
    }

    /**
     * Start a new saga.
     *
     * @return Promise<\ServiceBus\Sagas\Saga|null>
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     * @throws \ServiceBus\Sagas\Exceptions\SagaMetaDataNotFound
     * @throws \ServiceBus\Sagas\Store\Exceptions\DuplicateSaga The specified saga has already been added
     */
    public function start(SagaId $id, object $command, ServiceBusContext $context): Promise
    {
        return call(
            function () use ($id, $command, $context): \Generator
            {
                yield from $this->setupMutex($id);

                try
                {
                    $sagaMetaData = $this->extractSagaMetaData($id->sagaClass);

                    /** @var \DateTimeImmutable $expireDate */
                    $expireDate = datetimeInstantiator($sagaMetaData->expireDateModifier);

                    /** @var Saga $saga */
                    $saga = new $id->sagaClass($id, $expireDate);
                    $saga->start($command);

                    yield from $this->doStore($saga, $context, true);

                    return $saga;
                }
                finally
                {
                    yield from $this->releaseMutex($id);
                }
            }
        );
    }

    /**
     * Load saga.
     *
     * @return Promise<\ServiceBus\Sagas\Saga|null>
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\LoadedExpiredSaga Expired saga loaded
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while deserializing saga
     */
    public function obtain(SagaId $id, ServiceBusContext $context): Promise
    {
        return call(
            function () use ($id, $context): \Generator
            {
                yield from $this->setupMutex($id);

                try
                {
                    /** @var Saga|null $saga */
                    $saga = yield $this->sagaStore->obtain($id);
                }
                catch (\Throwable $throwable)
                {
                    yield from $this->releaseMutex($id);

                    throw $throwable;
                }

                if ($saga !== null)
                {
                    /** Non-expired saga */
                    if ($saga->expireDate() > now())
                    {
                        return $saga;
                    }

                    yield from $this->doCloseExpired($saga, $context);

                    throw new LoadedExpiredSaga(
                        \sprintf('Unable to load the saga (ID: "%s") whose lifetime has expired', $id->toString())
                    );
                }

                yield from $this->releaseMutex($id);
            }
        );
    }

    /**
     * Save\update a saga.
     *
     * @return Promise<void>
     *
     * @throws \ServiceBus\Sagas\Exceptions\CantSaveUnStartedSaga Attempt to save un-started saga
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     */
    public function save(Saga $saga, ServiceBusContext $context): Promise
    {
        return call(
            function () use ($saga, $context): \Generator
            {
                try
                {
                    /** @var Saga|null $existsSaga */
                    $existsSaga = yield $this->sagaStore->obtain($saga->id());

                    if ($existsSaga !== null)
                    {
                        /** The saga has not been updated */
                        if ($existsSaga->stateHash() !== $saga->stateHash())
                        {
                            yield from $this->doStore($saga, $context, false);
                        }

                        return;
                    }

                    throw CantSaveUnStartedSaga::create($saga);
                }
                finally
                {
                    yield from $this->releaseMutex($saga->id());
                }
            }
        );
    }

    /**
     * Reopen the saga.
     *
     * @return Promise<void>
     *
     * @throws \ServiceBus\Sagas\Exceptions\CantSaveUnStartedSaga Attempt to save un-started saga
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     * @throws \ServiceBus\Sagas\Exceptions\ReopenFailed
     */
    public function reopen(
        SagaId $id,
        ServiceBusContext $context,
        \DateTimeImmutable $newExpireDate,
        string $reason = ''
    ): Promise {
        return call(
            function () use ($id, $context, $newExpireDate, $reason): \Generator
            {
                yield from $this->setupMutex($id);

                try
                {
                    /** @var Saga|null $saga */
                    $saga = yield $this->sagaStore->obtain($id);

                    if ($saga !== null)
                    {
                        invokeReflectionMethod($saga, 'reopen', $newExpireDate, $reason);

                        yield from $this->doStore($saga, $context, false);

                        return;
                    }

                    throw new ReopenFailed(
                        \sprintf('Saga `%s` doesn\'t exists', $id->toString())
                    );
                }
                catch (\Throwable $throwable)
                {
                    throw $throwable;
                }
                finally
                {
                    yield from $this->releaseMutex($id);
                }
            }
        );
    }

    /**
     * Close expired saga.
     *
     * @throws \ServiceBus\Sagas\Exceptions\CantSaveUnStartedSaga Attempt to save un-started saga
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     */
    private function doCloseExpired(Saga $saga, ServiceBusContext $context): \Generator
    {
        /** @var \ServiceBus\Sagas\SagaStatus $currentStatus */
        $currentStatus = readReflectionPropertyValue($saga, 'status');

        if ($currentStatus->inProgress())
        {
            invokeReflectionMethod($saga, 'expire');

            yield $this->save($saga, $context);
        }
    }

    /**
     * Execute add/update saga entry.
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     * @throws \ServiceBus\Sagas\Store\Exceptions\DuplicateSaga The specified saga has already been added
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Common\Context\Exceptions\MessageDeliveryFailed
     */
    private function doStore(Saga $saga, ServiceBusContext $context, bool $isNew): \Generator
    {
        /**
         * @psalm-var  array<int, object> $messages
         *
         * @var object[] $messages
         */
        $messages = invokeReflectionMethod($saga, 'messages');

        $isNew ? yield $this->sagaStore->save($saga) : yield $this->sagaStore->update($saga);

        $promises = [];

        foreach ($messages as $message)
        {
            $promises[] = $context->delivery($message);
        }

        if (\count($promises) !== 0)
        {
            yield $promises;
        }
    }

    /**
     * Receive saga meta data information.
     *
     * @throws \ServiceBus\Sagas\Exceptions\SagaMetaDataNotFound
     */
    private function extractSagaMetaData(string $sagaClass): SagaMetadata
    {
        if (isset($this->sagaMetaDataCollection[$sagaClass]))
        {
            return $this->sagaMetaDataCollection[$sagaClass];
        }

        throw SagaMetaDataNotFound::create($sagaClass);
    }

    /**
     * Add meta data for specified saga
     * Called from the infrastructure layer using Reflection API.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @see          SagaMessagesRouterConfigurator::configure
     */
    private function appendMetaData(string $sagaClass, SagaMetadata $metadata): void
    {
        $this->sagaMetaDataCollection[$sagaClass] = $metadata;
    }

    /**
     * Setup mutex on saga.
     *
     * @throws \ServiceBus\Mutex\Exceptions\SyncException An error occurs when attempting to obtain the lock
     */
    private function setupMutex(SagaId $id): \Generator
    {
        $mutexKey = createMutexKey($id);

        $mutex = $this->mutexFactory->create($mutexKey);

        /** @var Lock $lock */
        $lock = yield $mutex->acquire();

        $this->lockCollection[$mutexKey] = $lock;
    }

    /**
     * Remove lock from saga.
     */
    private function releaseMutex(SagaId $id): \Generator
    {
        $mutexKey = createMutexKey($id);

        if (\array_key_exists($mutexKey, $this->lockCollection))
        {
            /** @var Lock $lock */
            $lock = $this->lockCollection[$mutexKey];

            unset($this->lockCollection[$mutexKey]);

            yield $lock->release();
        }
    }
}
