<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas;

use ServiceBus\Sagas\Contract\SagaReopened;
use ServiceBus\Sagas\Exceptions\ReopenFailed;
use ServiceBus\Sagas\Configuration\SagaMetadata;
use ServiceBus\Sagas\Contract\SagaClosed;
use ServiceBus\Sagas\Contract\SagaCreated;
use ServiceBus\Sagas\Contract\SagaStatusChanged;
use ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed;
use ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\now;

/**
 * Base class for all sagas.
 */
abstract class Saga
{
    /**
     * Saga identifier.
     *
     * @var SagaId
     */
    private $id;

    /**
     * List of messages that should be fired while saving.
     *
     * @psalm-var list<object>
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
        SagaId              $id,
        ?\DateTimeImmutable $expireDate = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->assertSagaClassEqualsWithId($id);
        $this->clear();

        /** @var \DateTimeImmutable $expireDate */
        $expireDate = $expireDate ?? datetimeInstantiator(SagaMetadata::DEFAULT_EXPIRE_INTERVAL);

        $this->assertExpirationDateIsCorrect($id, $expireDate);

        $this->id     = $id;
        $this->status = SagaStatus::IN_PROGRESS;

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
     *
     * @psalm-return non-empty-string
     */
    final public function hash(): string
    {
        /** @psalm-var non-empty-string $hash */
        $hash = \sha1(\serialize($this));

        return $hash;
    }

    /**
     * Raise (apply event).
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function raise(object $event): void
    {
        $this->assertNotClosedSaga();
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
    final protected function complete(string $withReason = null): void
    {
        $this->assertNotClosedSaga();

        $this->changeState(SagaStatus::COMPLETED, $withReason);
        $this->close($withReason);
    }

    /**
     * Change saga status to failed.
     *
     * @see SagaStatus::STATUS_FAILED
     *
     * @throws \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     */
    final protected function fail(string $withReason = null): void
    {
        $this->assertNotClosedSaga();

        $this->changeState(SagaStatus::FAILED, $withReason);
        $this->close($withReason);
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
        if ($this->status->equals(SagaStatus::IN_PROGRESS) || $this->status->equals(SagaStatus::REOPENED))
        {
            throw ReopenFailed::stillALive($this->id);
        }

        $currentDate = now();

        if ($currentDate > $withNewExpirationDate)
        {
            throw ReopenFailed::incorrectExpirationDate($this->id);
        }

        $this->changeState(SagaStatus::REOPENED, $withReason);

        $this->expireDate = $withNewExpirationDate;
        $this->closedAt   = null;

        $this->raise(
            new SagaReopened($this->id->toString(), $currentDate, $this->expireDate, $withReason)
        );
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
     * Change saga status to expired.
     * Called using Reflection API from the infrastructure layer.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function expire(): void
    {
        $this->changeState(SagaStatus::EXPIRED);
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
        if (
            $this->status->equals(SagaStatus::IN_PROGRESS) === false &&
            $this->status->equals(SagaStatus::REOPENED) === false
        ) {
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
