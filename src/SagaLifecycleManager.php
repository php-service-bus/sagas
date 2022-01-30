<?php

declare(strict_types=1);

namespace ServiceBus\Sagas;

use Amp\Promise;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Mutex\InMemory\InMemoryMutexService;
use ServiceBus\Mutex\MutexService;
use ServiceBus\Sagas\Exceptions\ReopenFailed;
use ServiceBus\Sagas\Store\SagasStore;
use function Amp\call;
use function ServiceBus\Common\invokeReflectionMethod;

final class SagaLifecycleManager
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
     * Reopen the saga.
     *
     * @psalm-return Promise<void>
     *
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed Database interaction error
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError Error while serializing saga
     * @throws \ServiceBus\Sagas\Exceptions\ReopenFailed
     */
    public function reopen(
        SagaId             $id,
        ServiceBusContext  $context,
        \DateTimeImmutable $newExpireDate,
        string             $reason = ''
    ): Promise {
        return call(
            function () use ($id, $context, $newExpireDate, $reason): \Generator
            {
                yield $this->mutexService->withLock(
                    id: createMutexKey($id),
                    code: function () use ($id, $context, $newExpireDate, $reason): \Generator
                    {
                        /** @var Saga|null $saga */
                        $saga = yield $this->sagaStore->obtain($id);

                        if ($saga !== null)
                        {
                            invokeReflectionMethod($saga, 'reopen', $newExpireDate, $reason);

                            return yield from $this->doStore(
                                saga: $saga,
                                context: $context
                            );
                        }

                        throw new ReopenFailed(
                            \sprintf('Saga `%s` doesn\'t exists', $id->toString())
                        );
                    }
                );
            }
        );
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
