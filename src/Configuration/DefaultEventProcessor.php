<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration;

use function Amp\call;
use Amp\Promise;
use Psr\Log\LogLevel;
use ServiceBus\Common\Context\ServiceBusContext;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\invokeReflectionMethod;
use ServiceBus\Common\Messages\Event;
use function ServiceBus\Common\readReflectionPropertyValue;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\Store\SagasStore;

/**
 *
 */
final class DefaultEventProcessor implements EventProcessor
{
    /**
     * The event for which the handler is registered
     *
     * @psalm-var class-string<\ServiceBus\Common\Messages\Event>
     *
     * @var string
     */
    private $forEvent;

    /**
     * @var SagasStore
     */
    private $sagasStore;

    /**
     * Listener options
     *
     * @var SagaListenerOptions
     */
    private $sagaListenerOptions;

    /**
     * @psalm-param class-string<\ServiceBus\Common\Messages\Event> $forEvent
     *
     * @param string              $forEvent
     * @param SagasStore          $sagasStore
     * @param SagaListenerOptions $sagaListenerOptions
     */
    public function __construct(string $forEvent, SagasStore $sagasStore, SagaListenerOptions $sagaListenerOptions)
    {
        $this->forEvent            = $forEvent;
        $this->sagasStore          = $sagasStore;
        $this->sagaListenerOptions = $sagaListenerOptions;
    }

    /**
     * @inheritDoc
     */
    public function event(): string
    {
        return $this->forEvent;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(Event $event, ServiceBusContext $context): Promise
    {
        /** @psalm-suppress InvalidArgument Incorrect psalm unpack parameters (...$args) */
        return call(
            function(Event $event, ServiceBusContext $context): \Generator
            {
                try
                {
                    /** @var \ServiceBus\Sagas\Saga $saga */
                    $saga = yield from $this->loadSaga($event);

                    invokeReflectionMethod($saga, 'applyEvent', $event);

                    /** @var array<int, \ServiceBus\Common\Messages\Command> $commands */
                    $commands = invokeReflectionMethod($saga, 'firedCommands');

                    /** @var array<int, \ServiceBus\Common\Messages\Event> $events */
                    $events = invokeReflectionMethod($saga, 'raisedEvents');

                    yield $this->sagasStore->update($saga);

                    yield from $this->deliveryMessages($context, $commands, $events);
                }
                catch(\Throwable $throwable)
                {
                    $context->logContextMessage(
                        'Error in applying event to saga: "{throwableMessage}"', [
                        'eventClass'       => \get_class($event),
                        'throwableMessage' => $throwable->getMessage(),
                        'throwablePoint'   => \sprintf('%s:%d', $throwable->getFile(), $throwable->getLine())
                    ],
                        LogLevel::ERROR
                    );
                }

            },
            $event, $context
        );
    }

    /**
     * Delivery events & commands to message bus
     *
     * @param ServiceBusContext                               $context
     * @param array<int, \ServiceBus\Common\Messages\Command> $commands
     * @param array<int, \ServiceBus\Common\Messages\Event>   $events
     *
     * @return \Generator Doesn't return result
     */
    private function deliveryMessages(ServiceBusContext $context, array $commands, array $events): \Generator
    {
        $promises = [];

        /** @var \ServiceBus\Common\Messages\Command $command */
        foreach($commands as $command)
        {
            $promises[] = $context->delivery($command);
        }

        /** @var \ServiceBus\Common\Messages\Event $event */
        foreach($events as $event)
        {
            $promises[] = $context->delivery($event);
        }

        yield $promises;
    }

    /**
     * @param Event $event
     *
     * @return \Generator
     *
     * @throws \RuntimeException
     * @throws \ServiceBus\Common\Exceptions\DateTime\CreateDateTimeFailed
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagaSerializationError
     * @throws \ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed
     */
    private function loadSaga(Event $event): \Generator
    {
        /** @var \DateTimeImmutable $currentDatetime */
        $currentDatetime = datetimeInstantiator('NOW');

        $id = $this->searchSagaIdentifier($event);

        /** @var \ServiceBus\Sagas\Saga|null $saga */
        $saga = yield $this->sagasStore->obtain($id);

        if(null === $saga)
        {
            throw new \RuntimeException(
                \sprintf(
                    'Attempt to apply event to non-existent saga (ID: %s)', $id)
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
     * Search saga identifier in the event payload
     *
     * @param Event $event
     *
     * @return SagaId
     *
     * @throws \RuntimeException
     */
    private function searchSagaIdentifier(Event $event): SagaId
    {
        $identifierClass = $this->sagaListenerOptions->identifierClass();

        if(true === \class_exists($identifierClass))
        {
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

        throw new \RuntimeException(
            \sprintf(
                'Identifier class "%s" specified in the saga "%s" not found',
                $identifierClass,
                $this->sagaListenerOptions->sagaClass()
            )
        );
    }

    /**
     * Create identifier instance
     *
     * @template        \ServiceBus\Sagas\SagaId
     * @template-typeof \ServiceBus\Sagas\SagaId $idClass
     *
     * @param string $idClass
     * @param string $idValue
     * @param string $sagaClass
     *
     * @return SagaId
     *
     * @throws \RuntimeException
     */
    private static function identifierInstantiator(string $idClass, string $idValue, string $sagaClass): SagaId
    {
        $identifier = new $idClass($idValue, $sagaClass);

        if($identifier instanceof SagaId)
        {
            return $identifier;
        }

        throw new \RuntimeException(
            \sprintf(
                'Saga identifier mus be type of "%s". "%s" type specified',
                SagaId::class, \get_class($identifier)
            )
        );
    }

    /**
     * Read event property value
     *
     * @param Event  $event
     * @param string $propertyName
     *
     * @return string
     *
     * @throws \Throwable Reflection property not found
     */
    private static function readEventProperty(Event $event, string $propertyName): string
    {
        if(true === isset($event->{$propertyName}))
        {
            return (string) $event->{$propertyName};
        }

        return (string) readReflectionPropertyValue($event, $propertyName);
    }
}
