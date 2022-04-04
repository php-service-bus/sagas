<?php

declare(strict_types=1);

namespace ServiceBus\Sagas\Configuration\MessageProcessor;

use Amp\Promise;
use ServiceBus\ArgumentResolver\ChainArgumentResolver;
use ServiceBus\Common\Context\ServiceBusContext;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Mutex\MutexService;
use ServiceBus\Sagas\Configuration\Metadata\SagaHandlerOptions;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use ServiceBus\Sagas\Configuration\SagaIdLocator;
use ServiceBus\Sagas\Exceptions\SagaMetaDataNotFound;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\SagaMetadataStore;
use ServiceBus\Sagas\Store\SagasStore;
use function Amp\call;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Sagas\Configuration\createClosure;
use function ServiceBus\Sagas\createMutexKey;

final class DefaultInitialCommandHandlerMessageProcessor implements MessageProcessor
{
    /**
     * The command for which the handler is registered.
     *
     * @psalm-var class-string
     *
     * @var string
     */
    private $forCommand;

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
     * @psalm-param class-string $forCommand
     */
    public function __construct(
        string                $forCommand,
        SagasStore            $sagasStore,
        SagaHandlerOptions    $sagaListenerOptions,
        MutexService          $mutexService,
        ChainArgumentResolver $argumentResolver,
        SagaIdLocator         $sagaIdLocator
    ) {
        $this->forCommand          = $forCommand;
        $this->sagasStore          = $sagasStore;
        $this->sagaListenerOptions = $sagaListenerOptions;
        $this->mutexService        = $mutexService;
        $this->argumentResolver    = $argumentResolver;
        $this->sagaIdLocator       = $sagaIdLocator;
    }

    public function message(): string
    {
        return $this->forCommand;
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
                        $sagaMetaData = SagaMetadataStore::instance()->get($id->sagaClass)
                            ?? throw SagaMetaDataNotFound::create($id->sagaClass);

                        /** @var \DateTimeImmutable $expireDate */
                        $expireDate = datetimeInstantiator($sagaMetaData->expireDateModifier);

                        /** @var Saga $saga */
                        $saga = new $id->sagaClass($id, $expireDate);

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

                        /** @var object[] $messages */
                        $messages = invokeReflectionMethod($saga, 'messages');

                        yield $this->sagasStore->save(
                            saga: $saga,
                            publisher: static function () use ($messages, $context): \Generator
                            {
                                yield $context->deliveryBulk($messages);
                            }
                        );
                    }
                );
            }
        );
    }

    /**
     * @throws \RuntimeException
     */
    private function buildMessageHandler(Saga $saga, object $command): MessageHandler
    {
        try
        {
            $reflectionMethod = new \ReflectionMethod($saga, SagaConfigurationLoader::INITIAL_COMMAND_METHOD);

            return new MessageHandler(
                messageClass: \get_class($command),
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
                    \get_class($command),
                    $throwable->getMessage()
                )
            );
        }
    }
}
