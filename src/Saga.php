<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Sagas;

use ServiceBus\Sagas\Contract\SagaReopened;
use ServiceBus\Sagas\Exceptions\ReopenFailed;
use function ServiceBus\Common\datetimeInstantiator;
use ServiceBus\Sagas\Configuration\SagaMetadata;
use ServiceBus\Sagas\Contract\SagaClosed;
use ServiceBus\Sagas\Contract\SagaCreated;
use ServiceBus\Sagas\Contract\SagaStatusChanged;
use ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed;
use ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;
use function ServiceBus\Common\now;

/**
 * Base class for all sagas.
 */
abstract class Saga
{
    /**
     * The prefix from which all names of methods-listeners of events should begin.
     */
    public const EVENT_APPLY_PREFIX = 'on';

    /**
     * Saga identifier.
     *
     * @var SagaId
     */
    private $id;

    /**
     * List of messages that should be fired while saving.
     *
     * @psalm-var array<int, object>
     *
     * @var object[]
     */
    private $messages;

    /**
     * SagaStatus of the saga.
     *
     * @var SagaStatus
     */
    private $status;

    /**
     * Date of saga creation.
     *
     * @var \DateTimeImmutable
     */
    private $createdAt;

    /**
     * Saga expiration date.
     *
     * @var \DateTimeImmutable
     */
    private $expireDate;

    /**
     * Saga closing date.
     *
     * @var \DateTimeImmutable|null
     */
    private $closedAt;

    /**
     * @throws \ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     * @throws \ServiceBus\Common\Exceptions\DateTimeException
     */
    final public function __construct(
        SagaId $id,
        ?\DateTimeImmutable $expireDate = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->assertSagaClassEqualsWithId($id);
        $this->clear();

        /** @var \DateTimeImmutable $expireDate */
        $expireDate = $expireDate ?? datetimeInstantiator(SagaMetadata::DEFAULT_EXPIRE_INTERVAL);

        $this->assertExpirationDateIsCorrect($id, $expireDate);

        $this->id     = $id;
        $this->status = SagaStatus::created();

        $this->createdAt  = $createdAt ?? now();
        $this->expireDate = $expireDate;

        $this->raise(
            new SagaCreated($id, $this->createdAt, $this->expireDate)
        );
    }

    /**
     * Flush commands/events on wakeup.
     */
    final public function __wakeup(): void
    {
        $this->clear();
    }

    /**
     * Start saga flow.
     */
    abstract public function start(object $command): void;

    /**
     * Receive saga id.
     */
    final public function id(): SagaId
    {
        return $this->id;
    }

    /**
     * Date of creation.
     */
    final public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Date of expiration.
     */
    final public function expireDate(): \DateTimeImmutable
    {
        return $this->expireDate;
    }

    /**
     * Saga closing date.
     */
    final public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    /**
     * Receive current state hash.
     */
    final public function stateHash(): string
    {
        return \sha1(\serialize($this));
    }

    /**
     * Raise (apply event).
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function raise(object $event): void
    {
        $this->assertNotClosedSaga();

        $this->applyEvent($event);
        $this->attachMessage($event);
    }

    /**
     * Fire command.
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function fire(object $command): void
    {
        $this->assertNotClosedSaga();

        $this->attachMessage($command);
    }

    /**
     * Change saga status to completed.
     *
     * @see SagaStatus::STATUS_COMPLETED
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function makeCompleted(string $withReason = null): void
    {
        $this->assertNotClosedSaga();

        $this->changeState(SagaStatus::completed(), $withReason);
        $this->close($withReason);
    }

    /**
     * Change saga status to failed.
     *
     * @see SagaStatus::STATUS_FAILED
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function makeFailed(string $withReason = null): void
    {
        $this->assertNotClosedSaga();

        $this->changeState(SagaStatus::failed(), $withReason);
        $this->close($withReason);
    }

    /**
     * Receive a list of messages that should be fired while saving
     * Called using Reflection API from the infrastructure layer.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @psalm-return array<int, object>
     */
    private function messages(): array
    {
        $messages = $this->messages;

        $this->clear();

        return $messages;
    }

    /**
     * Apply event.
     * Called using Reflection API from the infrastructure layer.
     */
    private function applyEvent(object $event): void
    {
        $eventListenerMethodName = createEventListenerName(\get_class($event));

        /**
         * Call child class method.
         *
         * @param object $event
         *
         * @return void
         */
        $closure = function (object $event) use ($eventListenerMethodName): void
        {
            if (\method_exists($this, $eventListenerMethodName))
            {
                $this->{$eventListenerMethodName}($event);
            }
        };

        $closure->call($this, $event);
    }

    /**
     * Reopen saga.
     *
     * Called using Reflection API from the infrastructure layer.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @throws \ServiceBus\Sagas\Exceptions\ReopenFailed
     */
    private function reopen(\DateTimeImmutable $withNewExpirationDate, string $withReason = ''): void
    {
        if ($this->status->equals(SagaStatus::created()) ||   $this->status->equals(SagaStatus::reopened()))
        {
            throw ReopenFailed::stillALive($this->id);
        }

        $currentDate = now();

        if ($currentDate > $withNewExpirationDate)
        {
            throw ReopenFailed::incorrectExpirationDate($this->id);
        }

        $this->changeState(SagaStatus::reopened(), $withReason);

        $this->expireDate = $withNewExpirationDate;
        $this->closedAt   = null;

        $this->raise(
            new SagaReopened($this->id->toString(), $currentDate, $this->expireDate, $withReason)
        );
    }

    /**
     * Change saga status to expired.
     * Called using Reflection API from the infrastructure layer.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function expire(): void
    {
        $this->changeState(SagaStatus::expired());
        $this->close('expired');

        $this->expireDate = now();
    }

    /**
     * Close saga.
     */
    private function close(string $withReason = null): void
    {
        $event = new SagaClosed($this->id, now(), $withReason);

        $this->closedAt = $event->datetime;

        $this->attachMessage($event);
    }

    /**
     * Change saga state.
     */
    private function changeState(SagaStatus $toState, string $withReason = null): void
    {
        $this->attachMessage(
            new SagaStatusChanged(
                $this->id,
                $this->status,
                $toState,
                now(),
                $withReason
            )
        );

        $this->status = $toState;
    }

    /**
     * Clear fired messages.
     */
    private function clear(): void
    {
        $this->messages = [];
    }

    private function attachMessage(object $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * Checking the possibility of changing the state of the saga.
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    private function assertNotClosedSaga(): void
    {
        if ($this->status->inProgress() === false)
        {
            throw ChangeSagaStateFailed::create($this->status);
        }
    }

    /**
     * @throws \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     */
    private function assertSagaClassEqualsWithId(SagaId $id): void
    {
        $currentSagaClass = \get_class($this);

        if ($currentSagaClass !== $id->sagaClass)
        {
            throw InvalidSagaIdentifier::sagaClassMismatch($currentSagaClass, $id->sagaClass);
        }
    }

    /**
     * @throws \ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval
     */
    private function assertExpirationDateIsCorrect(SagaId $id, \DateTimeImmutable $dateTime): void
    {
        if (now() > $dateTime)
        {
            throw InvalidExpireDateInterval::create($id);
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }
}
