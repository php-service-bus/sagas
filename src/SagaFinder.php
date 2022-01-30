<?php

declare(strict_types=1);

namespace ServiceBus\Sagas;

use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Mutex\InMemory\InMemoryMutexService;
use ServiceBus\Mutex\MutexService;
use ServiceBus\Sagas\Exceptions\SagaNotFound;
use ServiceBus\Sagas\Store\Exceptions\LoadedExpiredSaga;
use ServiceBus\Sagas\Store\SagasStore;
use function Amp\call;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\now;
use function ServiceBus\Common\readReflectionPropertyValue;

final class SagaFinder
{
    /**
     * @var SagasStore
     */
    private $sagaStore;

    /**
     * @var MutexService
     */
    private $mutexService;

    public function __construct(SagasStore $sagaStore, ?MutexService $mutexService = null)
    {
        $this->sagaStore    = $sagaStore;
        $this->mutexService = $mutexService ?? new InMemoryMutexService();
    }

    /**
     * Load saga.
     *
     * @psalm-param callable(Saga):mixed $onLoaded
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Sagas\Exceptions\SagaNotFound
     * @throws \ServiceBus\Sagas\Store\Exceptions\LoadedExpiredSaga Expired saga loaded
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while deserializing saga
     */
    public function load(SagaId $id, ServiceBusContext $context, callable $onLoaded): Promise
    {
        return call(
            function () use ($id, $context, $onLoaded): \Generator
            {
                yield $this->mutexService->withLock(
                    id: createMutexKey($id),
                    code: function () use ($id, $context, $onLoaded): \Generator
                    {
                        /** @var Saga|null $saga */
                        $saga = yield $this->sagaStore->obtain($id);

                        if ($saga !== null)
                        {
                            /** Non-expired saga */
                            if ($saga->expireDate() > now())
                            {
                                return yield call($onLoaded, $saga);
                            }

                            yield from $this->doCloseExpired(
                                saga: $saga,
                                context: $context
                            );

                            throw new LoadedExpiredSaga(
                                \sprintf(
                                    'Unable to load the saga (ID: "%s") whose lifetime has expired',
                                    $id->toString()
                                )
                            );
                        }

                        throw new SagaNotFound($id);
                    }
                );
            }
        );
    }

    /**
     * Close expired saga.
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     */
    private function doCloseExpired(Saga $saga, ServiceBusContext $context): \Generator
    {
        /** @var \ServiceBus\Sagas\SagaStatus $currentStatus */
        $currentStatus = readReflectionPropertyValue($saga, 'status');

        if ($currentStatus->equals(SagaStatus::IN_PROGRESS))
        {
            invokeReflectionMethod($saga, 'expire');

            yield from $this->doStore(saga: $saga, context: $context);
        }
    }

    /**
     * Execute update saga entry.
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     * @throws \ServiceBus\Sagas\Store\Exceptions\DuplicateSaga The specified saga has already been added
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Common\Exceptions\ReflectionApiException
     * @throws \ServiceBus\Common\Context\Exceptions\MessageDeliveryFailed
     */
    private function doStore(Saga $saga, ServiceBusContext $context): \Generator
    {
        /**
         * @psalm-var  array<int, object> $messages
         *
         * @var object[]                  $messages
         */
        $messages = invokeReflectionMethod($saga, 'messages');

        $publisher = static function () use ($messages, $context): \Generator
        {
            yield $context->deliveryBulk($messages);
        };

        yield $this->sagaStore->update($saga, $publisher);
    }
}
