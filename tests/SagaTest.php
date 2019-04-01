<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests;

use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Common\uuid;
use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\Contract\SagaClosed;
use ServiceBus\Sagas\Contract\SagaCreated;
use ServiceBus\Sagas\Contract\SagaStatusChanged;
use ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed;
use ServiceBus\Sagas\Exceptions\InvalidExpireDateInterval;
use ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier;
use ServiceBus\Sagas\SagaStatus;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\EmptyCommand;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;

/**
 *
 */
class SagaTest extends TestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function createWithNotEqualsSagaClass(): void
    {
        $this->expectException(InvalidSagaIdentifier::class);

        new CorrectSaga(new TestSagaId('123456789', \get_class($this)));
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function successfulStart(): void
    {
        $id = new TestSagaId(uuid(), CorrectSaga::class);

        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());
        $saga->doSomething();

        static::assertTrue(
            $id->equals(
                readReflectionPropertyValue($saga, 'id')
            )
        );

        static::assertNotFalse(\strtotime($saga->createdAt()->format('Y-m-d H:i:s')));

        static::assertSame((string) $id, (string) $saga->id());

        $raisedEvents  = invokeReflectionMethod($saga, 'raisedEvents');
        $firedCommands = invokeReflectionMethod($saga, 'firedCommands');

        static::assertCount(1, $raisedEvents);
        static::assertCount(1, $firedCommands);

        $raisedEvents  = invokeReflectionMethod($saga, 'raisedEvents');
        $firedCommands = invokeReflectionMethod($saga, 'firedCommands');

        static::assertCount(0, $raisedEvents);
        static::assertCount(0, $firedCommands);

        static::assertSame(
            (string) SagaStatus::create('in_progress'),
            (string) readReflectionPropertyValue($saga, 'status')
        );

        $saga->doSomethingElse();

        $raisedEvents  = invokeReflectionMethod($saga, 'raisedEvents');
        $firedCommands = invokeReflectionMethod($saga, 'firedCommands');

        static::assertCount(2, $raisedEvents);
        static::assertCount(0, $firedCommands);

        static::assertSame(
            (string) SagaStatus::create('in_progress'),
            (string) readReflectionPropertyValue($saga, 'status')
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function changeStateOnClosedSaga(): void
    {
        $this->expectException(ChangeSagaStateFailed::class);
        $this->expectExceptionMessage('Changing the state of the saga is impossible: the saga is complete');

        $id = new TestSagaId('123456789', CorrectSaga::class);

        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());

        $saga->closeWithSuccessStatus();
        $saga->doSomethingElse();
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function changeStateToCompleted(): void
    {
        $id = new TestSagaId('123456789', CorrectSaga::class);

        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());
        $saga->closeWithSuccessStatus();

        static::assertSame(
            (string) SagaStatus::create('completed'),
            (string) readReflectionPropertyValue($saga, 'status')
        );

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'raisedEvents');

        static::assertNotEmpty($events);
        static::assertCount(3, $events);

        /** @var \ServiceBus\Sagas\Contract\SagaStatusChanged $changedStatusEvent */
        $changedStatusEvent = $events[1];

        static::assertInstanceOf(SagaStatusChanged::class, $changedStatusEvent);
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(\DateTimeImmutable::class, $changedStatusEvent->datetime);
        static::assertSame((string) $id, $changedStatusEvent->id);
        static::assertSame(\get_class($id), $changedStatusEvent->idClass);
        static::assertSame(CorrectSaga::class, $changedStatusEvent->sagaClass);
        static::assertTrue(SagaStatus::created()->equals(SagaStatus::create($changedStatusEvent->previousStatus)));
        static::assertTrue(SagaStatus::completed()->equals(SagaStatus::create($changedStatusEvent->newStatus)));
        static::assertNull($changedStatusEvent->withReason);

        /** @var \ServiceBus\Sagas\Contract\SagaClosed $sagaClosedEvent */
        $sagaClosedEvent = $events[2];

        static::assertInstanceOf(SagaClosed::class, $sagaClosedEvent);
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(\DateTimeImmutable::class, $sagaClosedEvent->datetime);
        static::assertSame((string) $id, $sagaClosedEvent->id);
        static::assertSame(\get_class($id), $sagaClosedEvent->idClass);
        static::assertSame(CorrectSaga::class, $sagaClosedEvent->sagaClass);
        static::assertNull($sagaClosedEvent->withReason);
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function sagaCreated(): void
    {
        $id   = new TestSagaId('123456789', CorrectSaga::class);
        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'raisedEvents');

        static::assertNotEmpty($events);
        static::assertCount(1, $events);

        /** @var \ServiceBus\Sagas\Contract\SagaCreated $sagaCreatedEvent */
        $sagaCreatedEvent = \end($events);

        static::assertInstanceOf(SagaCreated::class, $sagaCreatedEvent);
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(\DateTimeImmutable::class, $sagaCreatedEvent->datetime);
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(\DateTimeImmutable::class, $sagaCreatedEvent->expirationDate);
        static::assertSame((string) $id, $sagaCreatedEvent->id);
        static::assertSame(\get_class($id), $sagaCreatedEvent->idClass);
        static::assertSame(CorrectSaga::class, $sagaCreatedEvent->sagaClass);
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function makeFailed(): void
    {
        $id   = new TestSagaId('123456789', CorrectSaga::class);
        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());

        invokeReflectionMethod($saga, 'makeFailed', 'fail reason');

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'raisedEvents');

        $latest = \end($events);

        static::assertInstanceOf(SagaClosed::class, $latest);
        static::assertNotNull($saga->closedAt());
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function makeExpired(): void
    {
        $id   = new TestSagaId('123456789', CorrectSaga::class);
        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());

        invokeReflectionMethod($saga, 'makeExpired', 'fail reason');

        /** @var array<int, string> $events */
        $events = invokeReflectionMethod($saga, 'raisedEvents');

        $latest = \end($events);

        static::assertInstanceOf(SagaClosed::class, $latest);
        static::assertNotNull($saga->closedAt());
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function compareStateVersion(): void
    {
        $id   = new TestSagaId('123456789', CorrectSaga::class);
        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());

        $startHash = $saga->stateHash();

        static::assertSame(\sha1(\serialize($saga)), $startHash);

        $saga->changeValue(\str_repeat('x', 100000));

        $newHash = $saga->stateHash();

        static::assertNotSame($startHash, $newHash);
        static::assertSame(\sha1(\serialize($saga)), $newHash);

        $saga->changeValue(\str_repeat('x', 10000000));

        $latestHash = $saga->stateHash();

        static::assertNotSame($newHash, $latestHash);
        static::assertSame(\sha1(\serialize($saga)), $latestHash);
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function createWithIncorrectExpireInterval(): void
    {
        $this->expectException(InvalidExpireDateInterval::class);
        $this->expectExceptionMessage('The expiration date of the saga can not be less than the current date');

        new CorrectSaga(
            new TestSagaId('123456789', CorrectSaga::class),
            datetimeInstantiator('-1 hour')
        );
    }
}
