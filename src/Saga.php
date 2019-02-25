<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas;

use function ServiceBus\Common\datetimeInstantiator;
use ServiceBus\Sagas\Configuration\SagaMetadata;
use ServiceBus\Sagas\Contract\SagaClosed;
use ServiceBus\Sagas\Contract\SagaCreated;
use ServiceBus\Sagas\Contract\SagaStatusChanged;
use ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed;
use ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;

/**
 * Base class for all sagas
 */
abstract class Saga
{
    /**
     * The prefix from which all names of methods-listeners of events should begin
     *
     * @var string
     */
    public const EVENT_APPLY_PREFIX = 'on';

    /**
     * Saga identifier
     *
     * @var SagaId
     */
    private $id;

    /**
     * List of events that should be published while saving
     *
     * @var array<int, object>
     */
    private $events;

    /**
     * List of commands that should be fired while saving
     *
     * @var array<int, object>
     */
    private $commands;

    /**
     * SagaStatus of the saga
     *
     * @var SagaStatus
     */
    private $status;

    /**
     * Date of saga creation
     *
     * @var \DateTimeImmutable
     */
    private $createdAt;

    /**
     * Saga expiration date
     *
     * @var \DateTimeImmutable
     */
    private $expireDate;

    /**
     * Saga closing date
     *
     * @var \DateTimeImmutable|null
     */
    private $closedAt;

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param SagaId                  $id
     * @param \DateTimeImmutable|null $expireDate
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     */
    final public function __construct(SagaId $id, ?\DateTimeImmutable $expireDate = null)
    {
        $this->assertSagaClassEqualsWithId($id);
        $this->clear();

        /** @var \DateTimeImmutable $currentDatetime */
        $currentDatetime = datetimeInstantiator('NOW');

        /** @var \DateTimeImmutable $expireDate */
        $expireDate = $expireDate ?? datetimeInstantiator(SagaMetadata::DEFAULT_EXPIRE_INTERVAL);

        $this->assertExpirationDateIsCorrect($expireDate);

        $this->id     = $id;
        $this->status = SagaStatus::created();

        $this->createdAt  = $currentDatetime;
        $this->expireDate = $expireDate;

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->raise(SagaCreated::create($id, $currentDatetime, $expireDate));
    }

    /**
     * Flush commands/events on wakeup
     *
     * @return void
     */
    final public function __wakeup(): void
    {
        $this->clear();
    }

    /**
     * Start saga flow
     *
     * @param object $command
     *
     * @return void
     */
    abstract public function start(object $command): void;

    /**
     * Receive saga id
     *
     * @return SagaId
     */
    final public function id(): SagaId
    {
        return $this->id;
    }

    /**
     * Date of creation
     *
     * @return \DateTimeImmutable
     */
    final public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Date of expiration
     *
     * @return \DateTimeImmutable
     */
    final public function expireDate(): \DateTimeImmutable
    {
        return $this->expireDate;
    }

    /**
     * Saga closing date
     *
     * @return \DateTimeImmutable|null
     */
    final public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    /**
     * Raise (apply event)
     *
     * @param object $event
     *
     * @return void
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function raise(object $event): void
    {
        $this->assertNotClosedSaga();

        $this->applyEvent($event);
        $this->attachEvent($event);
    }

    /**
     * Fire command
     *
     * @param object $command
     *
     * @return void
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function fire(object $command): void
    {
        $this->assertNotClosedSaga();

        $this->attachCommand($command);
    }

    /**
     * Change saga status to completed
     *
     * @see SagaStatus::STATUS_COMPLETED
     *
     * @param string|null $withReason
     *
     * @return void
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function makeCompleted(string $withReason = null): void
    {
        $this->assertNotClosedSaga();

        $this->doChangeState(SagaStatus::completed(), $withReason);
        $this->doClose($withReason);
    }

    /**
     * Change saga status to failed
     *
     * @see SagaStatus::STATUS_FAILED
     *
     * @param string|null $withReason
     *
     * @return void
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function makeFailed(string $withReason = null): void
    {
        $this->assertNotClosedSaga();

        $this->doChangeState(SagaStatus::failed(), $withReason);
        $this->doClose($withReason);
    }

    /**
     * Receive a list of commands that should be fired while saving
     * Called using Reflection API from the infrastructure layer
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @return array<int, object>
     */
    private function firedCommands(): array
    {
        /** @var array<int, object> $commands */
        $commands = $this->commands;

        $this->clearFiredCommands();

        return $commands;
    }

    /**
     * Receive a list of events that should be published while saving
     * Called using Reflection API from the infrastructure layer
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @return array<int, object>
     */
    private function raisedEvents(): array
    {
        /** @var array<int, object> $commands */
        $events = $this->events;

        $this->clearRaisedEvents();

        return $events;
    }

    /**
     * Apply event
     *
     * @param object $event
     *
     * @return void
     */
    private function applyEvent(object $event): void
    {
        $eventListenerMethodName = createEventListenerName(\get_class($event));

        /**
         * Call child class method
         *
         * @param object $event
         *
         * @return void
         */
        $closure = function(object $event) use ($eventListenerMethodName): void
        {
            if(true === \method_exists($this, $eventListenerMethodName))
            {
                $this->{$eventListenerMethodName}($event);
            }
        };

        $closure->call($this, $event);
    }

    /**
     * Change saga status to expired
     *
     * @noinspection PhpDocMissingThrowsInspection PhpUnusedPrivateMethodInspection
     *
     * @see          SagaStatus::STATUS_EXPIRED
     *
     * @return void
     */
    private function makeExpired(): void
    {
        $this->doChangeState(SagaStatus::expired());
        $this->doClose('expired');

        /**
         * @noinspection PhpUnhandledExceptionInspection
         *
         * @var \DateTimeImmutable $currentDateTime
         */
        $currentDateTime = datetimeInstantiator('NOW');

        $this->expireDate = $currentDateTime;
    }

    /**
     * Close saga
     *
     * @param string|null $withReason
     *
     * @return void
     */
    private function doClose(string $withReason = null): void
    {
        $event = SagaClosed::create($this->id, $withReason);

        $this->closedAt = $event->datetime;

        $this->attachEvent($event);
    }

    /**
     * Change saga state
     *
     * @param SagaStatus  $toState
     * @param string|null $withReason
     *
     * @return void
     */
    private function doChangeState(SagaStatus $toState, string $withReason = null): void
    {
        $this->attachEvent(
            SagaStatusChanged::create(
                $this->id,
                $this->status,
                $toState,
                $withReason
            )
        );

        $this->status = $toState;
    }

    /**
     * Clear raised events and fired commands
     *
     * @return void
     */
    private function clear(): void
    {
        $this->clearFiredCommands();
        $this->clearRaisedEvents();
    }

    /**
     * Clear raised events
     *
     * @return void
     */
    private function clearRaisedEvents(): void
    {
        $this->events = [];
    }

    /**
     * Clear fired commands
     *
     * @return void
     */
    private function clearFiredCommands(): void
    {
        $this->commands = [];
    }

    /**
     * @param object $event
     *
     * @return void
     */
    private function attachEvent(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @param object $command
     *
     * @return void
     */
    private function attachCommand(object $command): void
    {
        $this->commands[] = $command;
    }

    /**
     * Checking the possibility of changing the state of the saga
     *
     * @return void
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    private function assertNotClosedSaga(): void
    {
        if(false === $this->status->inProgress())
        {
            throw ChangeSagaStateFailed::create($this->status);
        }
    }

    /**
     * @param SagaId $id
     *
     * @return void
     *
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    private function assertSagaClassEqualsWithId(SagaId $id): void
    {
        $currentSagaClass = \get_class($this);

        if($currentSagaClass !== $id->sagaClass)
        {
            throw new InvalidSagaIdentifier(
                \sprintf(
                    'The class of the saga in the identifier ("%s") differs from the saga to which it was transmitted ("%s")',
                    $currentSagaClass,
                    $id->sagaClass
                )
            );
        }
    }

    /**
     * @param \DateTimeImmutable $dateTime
     *
     * @return void
     *
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     * @throws \ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval
     */
    private function assertExpirationDateIsCorrect(\DateTimeImmutable $dateTime): void
    {
        /** @var \DateTimeImmutable $currentDate */
        $currentDate = datetimeInstantiator('NOW');

        if($currentDate > $dateTime)
        {
            throw new InvalidExpireDateInterval(
                'The expiration date of the saga can not be less than the current date'
            );
        }
    }

    /**
     * Close clone method
     *
     * @codeCoverageIgnore
     */
    private function __clone()
    {

    }
}
