<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests;

use ServiceBus\Sagas\Tests\stubs\CorrectSagaInitialCommand;
use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\Contract\SagaClosed;
use ServiceBus\Sagas\Contract\SagaCreated;
use ServiceBus\Sagas\Contract\SagaStatusChanged;
use ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed;
use ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;
use ServiceBus\Sagas\SagaStatus;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Common\uuid;

/**
 *
 */
final class SagaTest extends TestCase
{
    /**
     * @test
     */
    public function createWithNotEqualsSagaClass(): void
    {
        $this->expectException(InvalidSagaIdentifier::class);

        new CorrectSaga(new TestSagaId('123456789', \get_class($this)));
    }

    /**
     * @test
     */
    public function successfulStart(): void
    {
        $id = new TestSagaId(uuid(), CorrectSaga::class);

        $saga = new CorrectSaga($id);
        $saga->start(new CorrectSagaInitialCommand(uuid()));
        $saga->doSomething();

        self::assertTrue(
            $id->equals(
                readReflectionPropertyValue($saga, 'id')
            )
        );

        self::assertNotFalse(\strtotime($saga->createdAt()->format('Y-m-d H:i:s')));

        self::assertSame($id->toString(), $saga->id()->toString());

        $messages = invokeReflectionMethod($saga, 'messages');

        self::assertCount(2, $messages);

        $messages = invokeReflectionMethod($saga, 'messages');

        self::assertCount(0, $messages);

        self::assertSame(
            SagaStatus::IN_PROGRESS,
            readReflectionPropertyValue($saga, 'status')
        );

        $saga->doSomethingElse();

        $messages  = invokeReflectionMethod($saga, 'messages');

        self::assertCount(1, $messages);

        self::assertSame(
            SagaStatus::IN_PROGRESS,
            readReflectionPropertyValue($saga, 'status')
        );
    }

    /**
     * @test
     */
    public function changeStateOnClosedSaga(): void
    {
        $this->expectException(ChangeSagaStateFailed::class);
        $this->expectExceptionMessage('Changing the state of the saga is impossible: the saga is complete');

        $id = new TestSagaId('123456789', CorrectSaga::class);

        $saga = new CorrectSaga($id);
        $saga->start(new CorrectSagaInitialCommand(uuid()));

        $saga->closeWithSuccessStatus();
        $saga->doSomethingElse();
    }

    /**
     * @test
     */
    public function changeStateToCompleted(): void
    {
        $id = new TestSagaId('123456789', CorrectSaga::class);

        $saga = new CorrectSaga($id);
        $saga->start(new CorrectSagaInitialCommand(uuid()));
        $saga->closeWithSuccessStatus();

        self::assertSame(
            SagaStatus::COMPLETED,
            readReflectionPropertyValue($saga, 'status')
        );

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'messages');

        self::assertNotEmpty($events);
        self::assertCount(3, $events);

        /** @var \ServiceBus\Sagas\Contract\SagaStatusChanged $changedStatusEvent */
        $changedStatusEvent = $events[1];

        self::assertInstanceOf(SagaStatusChanged::class, $changedStatusEvent);

        self::assertInstanceOf(\DateTimeImmutable::class, $changedStatusEvent->datetime);
        self::assertSame($id->toString(), $changedStatusEvent->id);
        self::assertSame(\get_class($id), $changedStatusEvent->idClass);
        self::assertSame(CorrectSaga::class, $changedStatusEvent->sagaClass);
        self::assertTrue(SagaStatus::IN_PROGRESS->equals(SagaStatus::from($changedStatusEvent->previousStatus)));
        self::assertTrue(SagaStatus::COMPLETED->equals(SagaStatus::from($changedStatusEvent->newStatus)));
        self::assertNull($changedStatusEvent->withReason);

        /** @var \ServiceBus\Sagas\Contract\SagaClosed $sagaClosedEvent */
        $sagaClosedEvent = $events[2];

        self::assertInstanceOf(SagaClosed::class, $sagaClosedEvent);

        self::assertInstanceOf(\DateTimeImmutable::class, $sagaClosedEvent->datetime);
        self::assertSame($id->toString(), $sagaClosedEvent->id);
        self::assertSame(\get_class($id), $sagaClosedEvent->idClass);
        self::assertSame(CorrectSaga::class, $sagaClosedEvent->sagaClass);
        self::assertNull($sagaClosedEvent->withReason);
    }

    /**
     * @test
     */
    public function sagaCreated(): void
    {
        $id   = new TestSagaId('123456789', CorrectSaga::class);
        $saga = new CorrectSaga($id);
        $saga->start(new CorrectSagaInitialCommand(uuid()));

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'messages');

        self::assertNotEmpty($events);
        self::assertCount(1, $events);

        /** @var \ServiceBus\Sagas\Contract\SagaCreated $sagaCreatedEvent */
        $sagaCreatedEvent = \end($events);

        self::assertInstanceOf(SagaCreated::class, $sagaCreatedEvent);
        self::assertInstanceOf(\DateTimeImmutable::class, $sagaCreatedEvent->datetime);
        self::assertInstanceOf(\DateTimeImmutable::class, $sagaCreatedEvent->expirationDate);
        self::assertSame($id->toString(), $sagaCreatedEvent->id);
        self::assertSame(\get_class($id), $sagaCreatedEvent->idClass);
        self::assertSame(CorrectSaga::class, $sagaCreatedEvent->sagaClass);
    }

    /**
     * @test
     */
    public function makeFailed(): void
    {
        $id   = new TestSagaId('123456789', CorrectSaga::class);
        $saga = new CorrectSaga($id);
        $saga->start(new CorrectSagaInitialCommand(uuid()));

        invokeReflectionMethod($saga, 'fail', 'fail reason');

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'messages');

        $latest = \end($events);

        self::assertInstanceOf(SagaClosed::class, $latest);
        self::assertNotNull($saga->closedAt());
    }

    /**
     * @test
     */
    public function expire(): void
    {
        $id   = new TestSagaId('123456789', CorrectSaga::class);
        $saga = new CorrectSaga($id);
        $saga->start(new CorrectSagaInitialCommand(uuid()));

        invokeReflectionMethod($saga, 'expire', 'fail reason');

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'messages');

        $latest = \end($events);

        self::assertInstanceOf(SagaClosed::class, $latest);
        self::assertNotNull($saga->closedAt());
    }

    /**
     * @test
     */
    public function compareStateVersion(): void
    {
        $id   = new TestSagaId('123456789', CorrectSaga::class);
        $saga = new CorrectSaga($id);
        $saga->start(new CorrectSagaInitialCommand(uuid()));

        $startHash = $saga->hash();

        self::assertSame(\sha1(\serialize($saga)), $startHash);

        $saga->changeValue(\str_repeat('x', 100000));

        $newHash = $saga->hash();

        self::assertNotSame($startHash, $newHash);
        self::assertSame(\sha1(\serialize($saga)), $newHash);

        $saga->changeValue(\str_repeat('x', 10000000));

        $latestHash = $saga->hash();

        self::assertNotSame($newHash, $latestHash);
        self::assertSame(\sha1(\serialize($saga)), $latestHash);
    }

    /**
     * @test
     */
    public function createWithIncorrectExpireInterval(): void
    {
        $this->expectException(InvalidExpireDateInterval::class);
        $this->expectExceptionMessage('The expiration date of the saga `123456789` can not be less than the current date');

        new CorrectSaga(
            new TestSagaId('123456789', CorrectSaga::class),
            datetimeInstantiator('-1 hour')
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaCanBeCreatedAtGivenTime(): void
    {
        $id                 = new TestSagaId('123456789', CorrectSaga::class);
        $createdAt          = datetimeInstantiator('2012-12-12');
        $nonsenseExpireDate = datetimeInstantiator('+4543 days');
        $saga               = new CorrectSaga($id, $nonsenseExpireDate, $createdAt);
        $saga->start(new CorrectSagaInitialCommand(uuid()));

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'messages');

        self::assertEquals([new SagaCreated($id, $createdAt, $nonsenseExpireDate)], $events);
    }
}
