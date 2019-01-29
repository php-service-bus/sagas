<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests;

use PHPUnit\Framework\TestCase;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Common\uuid;
use ServiceBus\Sagas\Contract\SagaClosed;
use ServiceBus\Sagas\Contract\SagaCreated;
use ServiceBus\Sagas\Contract\SagaStatusChanged;
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
     * @expectedException \ServiceBus\Sagas\Exceptions\InvalidSagaIdentifier
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function createWithNotEqualsSagaClass(): void
    {
        new CorrectSaga(new TestSagaId('123456789', \get_class($this)));
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
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

        static::assertEquals((string) $id, (string) $saga->id());

        $raisedEvents  = invokeReflectionMethod($saga, 'raisedEvents');
        $firedCommands = invokeReflectionMethod($saga, 'firedCommands');

        static::assertCount(1, $raisedEvents);
        static::assertCount(1, $firedCommands);

        $raisedEvents  = invokeReflectionMethod($saga, 'raisedEvents');
        $firedCommands = invokeReflectionMethod($saga, 'firedCommands');

        static::assertCount(0, $raisedEvents);
        static::assertCount(0, $firedCommands);

        static::assertEquals(
            (string) SagaStatus::create('in_progress'),
            (string) readReflectionPropertyValue($saga, 'status')
        );

        $saga->doSomethingElse();

        $raisedEvents  = invokeReflectionMethod($saga, 'raisedEvents');
        $firedCommands = invokeReflectionMethod($saga, 'firedCommands');

        static::assertCount(2, $raisedEvents);
        static::assertCount(0, $firedCommands);

        static::assertEquals(
            (string) SagaStatus::create('in_progress'),
            (string) readReflectionPropertyValue($saga, 'status')
        );
    }

    /**
     * @test
     * @expectedException  \ServiceBus\Sagas\Exceptions\ChangeSagaStateFailed
     * @expectedExceptionMessage  Changing the state of the saga is impossible: the saga is complete
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function changeStateOnClosedSaga(): void
    {
        $id = new TestSagaId('123456789', CorrectSaga::class);

        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());

        $saga->closeWithSuccessStatus();
        $saga->doSomethingElse();
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function changeStateToCompleted(): void
    {
        $id = new TestSagaId('123456789', CorrectSaga::class);

        $saga = new CorrectSaga($id);
        $saga->start(new EmptyCommand());
        $saga->closeWithSuccessStatus();

        static::assertEquals(
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
        static::assertEquals((string) $id, $changedStatusEvent->id);
        static::assertEquals(\get_class($id), $changedStatusEvent->idClass);
        static::assertEquals(CorrectSaga::class, $changedStatusEvent->sagaClass);
        static::assertTrue(SagaStatus::created()->equals(SagaStatus::create($changedStatusEvent->previousStatus)));
        static::assertTrue(SagaStatus::completed()->equals(SagaStatus::create($changedStatusEvent->newStatus)));
        static::assertNull($changedStatusEvent->withReason);

        /** @var \ServiceBus\Sagas\Contract\SagaClosed $sagaClosedEvent */
        $sagaClosedEvent = $events[2];

        static::assertInstanceOf(SagaClosed::class, $sagaClosedEvent);
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(\DateTimeImmutable::class, $sagaClosedEvent->datetime);
        static::assertEquals((string) $id, $sagaClosedEvent->id);
        static::assertEquals(\get_class($id), $sagaClosedEvent->idClass);
        static::assertEquals(CorrectSaga::class, $sagaClosedEvent->sagaClass);
        static::assertNull($sagaClosedEvent->withReason);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
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
        static::assertEquals((string) $id, $sagaCreatedEvent->id);
        static::assertEquals(\get_class($id), $sagaCreatedEvent->idClass);
        static::assertEquals(CorrectSaga::class, $sagaCreatedEvent->sagaClass);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
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
     * @return void
     *
     * @throws \Throwable
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
}
