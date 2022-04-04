<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Configuration\MessageProcessor;

use Amp\Promise;
use ServiceBus\ArgumentResolver\ChainArgumentResolver;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Mutex\MutexService;
use ServiceBus\Sagas\Configuration\Metadata\SagaHandlerOptions;
use ServiceBus\Sagas\Configuration\SagaIdLocator;
use ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\Store\SagasStore;
use function Amp\call;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\now;
use function ServiceBus\Sagas\Configuration\createClosure;
use function ServiceBus\Sagas\createEventListenerName;
use function ServiceBus\Sagas\createMutexKey;

final class DefaultEventListenerProcessor implements MessageProcessor
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
     * @var SagaHandlerOptions
     */
    private $sagaListenerOptions;

    /**
     * @var MutexService
     */
    private $mutexService;

    /**
     * @var ChainArgumentResolver
     */
    private $argumentResolver;

    /**
     * @var SagaIdLocator
     */
    private $sagaIdLocator;

    /**
     * @psalm-param class-string $forEvent
     */
    public function __construct(
        string                $forEvent,
        SagasStore            $sagasStore,
        SagaHandlerOptions    $sagaListenerOptions,
        MutexService          $mutexService,
        ChainArgumentResolver $argumentResolver,
        SagaIdLocator         $sagaIdLocator
    ) {
        $this->forEvent            = $forEvent;
        $this->sagasStore          = $sagasStore;
        $this->sagaListenerOptions = $sagaListenerOptions;
        $this->mutexService        = $mutexService;
        $this->argumentResolver    = $argumentResolver;
        $this->sagaIdLocator       = $sagaIdLocator;
    }

    public function message(): string
    {
        return $this->forEvent;
    }

    public function __invoke(object $message, ServiceBusContext $context): Promise
    {
        return call(
            function () use ($message, $context): \Generator
            {
                /** @psalm-var SagaId $id */
                $id = yield $this->sagaIdLocator->process(
                    handlerOptions: $this->sagaListenerOptions,
                    message: $message,
                    headers: $context->headers()
                );

                yield $this->mutexService->withLock(
                    id: createMutexKey($id),
                    code: function () use ($id, $message, $context): \Generator
                    {
                        /** @var \ServiceBus\Sagas\Saga $saga */
                        $saga = yield from $this->loadSaga($id);

                        $stateHash = $saga->hash();

                        $description = $this->sagaListenerOptions->description();

                        if ($description !== null)
                        {
                            $context->logger()->debug($description);
                        }

                        $messageHandler = $this->buildMessageHandler($saga, $message);

                        $resolvedArgs = $this->argumentResolver->resolve(
                            arguments: $messageHandler->arguments,
                            message: $message,
                            context: $context
                        );

                        yield call($messageHandler->closure, ...$resolvedArgs);

                        if ($stateHash !== $saga->hash())
                        {
                            /**
                             * @var object[] $messages
                             */
                            $messages = invokeReflectionMethod($saga, 'messages');

                            yield $this->sagasStore->update(
                                saga: $saga,
                                publisher: static function () use ($messages, $context): \Generator
                                {
                                    yield $context->deliveryBulk($messages);
                                }
                            );
                        }
                    }
                );
            }
        );
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
     * @throws \RuntimeException
     */
    private function buildMessageHandler(Saga $saga, object $event): MessageHandler
    {
        try
        {
            $reflectionMethod = new \ReflectionMethod($saga, createEventListenerName($event));

            return new MessageHandler(
                messageClass: \get_class($event),
                closure: createClosure($saga, $reflectionMethod),
                reflectionMethod: $reflectionMethod,
                options: $this->sagaListenerOptions,
                description: $this->sagaListenerOptions->description()
            );
        }
        catch (\Throwable $throwable)
        {
            throw new \RuntimeException(
                \sprintf(
                    'Unable to compile message handler for `%s`: %s',
                    \get_class($event),
                    $throwable->getMessage()
                )
            );
        }
    }
}
