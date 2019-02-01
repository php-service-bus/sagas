<?php

/**
 * Saga pattern implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration;

use function Amp\Promise\wait;
use PHPUnit\Framework\TestCase;
use function ServiceBus\Common\invokeReflectionMethod;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Sagas\Configuration\Annotations\SagaAnnotationBasedConfigurationLoader;
use ServiceBus\Sagas\Configuration\DefaultEventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\EventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use ServiceBus\Sagas\Store\Sql\SQLSagaStore;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\EmptyEvent;
use ServiceBus\Sagas\Tests\stubs\EventWithKey;
use ServiceBus\Sagas\Tests\stubs\SecondEventWithKey;
use ServiceBus\Sagas\Tests\stubs\TestContext;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;

/**
 *
 */
final class DefaultEventProcessorTest extends TestCase
{
    /**
     * @var DatabaseAdapter
     */
    private $adapter;

    /**
     * @var SQLSagaStore
     */
    private $store;

    /**
     * @var EventListenerProcessorFactory
     */
    private $listenerFactory;

    /**
     * @var SagaConfigurationLoader
     */
    private $configLoader;

    /**
     * @inheritdoc
     *
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new DoctrineDBALAdapter(
            new StorageConfiguration('sqlite:///:memory:')
        );

        wait($this->adapter->execute(\file_get_contents(__DIR__ . '/../../src/Store/Sql/schema/sagas_store.sql')));

        foreach(\file(__DIR__ . '/../../src/Store/Sql/schema/indexes.sql') as $indexQuery)
        {
            wait($this->adapter->execute($indexQuery));
        }

        $this->store           = new SQLSagaStore($this->adapter);
        $this->listenerFactory = new DefaultEventListenerProcessorFactory($this->store);
        $this->configLoader    = new SagaAnnotationBasedConfigurationLoader(
            new DefaultEventListenerProcessorFactory($this->store)
        );
    }

    /**
     * @inheritdoc
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->adapter, $this->listenerFactory, $this->configLoader);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function successExecute(): void
    {
        $id   = TestSagaId::new(CorrectSaga::class);
        $saga = new CorrectSaga($id);

        wait($this->store->save($saga));

        $context = new TestContext;

        $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

        /** @var MessageHandler $handler */
        $handler = \iterator_to_array($handlers)[0];

        wait(($handler->closure)(new EventWithKey((string) $id), $context));

        $messages = $context->messages;

        /** @var SecondEventWithKey $event */
        $event = \end($messages);

        static::assertInstanceOf(SecondEventWithKey::class, $event);
        static::assertSame((string) $id, $event->key);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function executeWithoutSaga(): void
    {
        $id = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);

        $context = new TestContext;

        $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

        /** @var MessageHandler $handler */
        $handler = \iterator_to_array($handlers)[0];

        wait(($handler->closure)(new EventWithKey((string) $id), $context));

        $records = $context->logger->records;

        static::assertSame(EventWithKey::class, $handler->messageClass);
        static::assertCount(1, $records);
        static::assertEquals('Error in applying event to saga: "{throwableMessage}"', $records[0]['message']);
        static::assertEquals(
            'Attempt to apply event to non-existent saga (ID: 1b6d89ec-cf60-4e48-a253-fd57f844c07d)',
            $records[0]['context']['throwableMessage']
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function executeWithoutCorrelationId(): void
    {
        $id   = TestSagaId::new(CorrectSaga::class);
        $saga = new CorrectSaga($id);

        wait($this->store->save($saga));

        $context = new TestContext;

        $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

        /** @var MessageHandler $handler */
        $handler = \iterator_to_array($handlers)[2];

        wait(($handler->closure)(new EmptyEvent(), $context));

        $records = $context->logger->records;

        static::assertCount(1, $records);
        static::assertSame(
            'A property that contains an identifier ("requestId") was not found in class "ServiceBus\\Sagas\\Tests\\stubs\\EmptyEvent"',
            $records[0]['context']['throwableMessage']
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function executeWithEmptyCorrelationId(): void
    {
        $id   = TestSagaId::new(CorrectSaga::class);
        $saga = new CorrectSaga($id);

        wait($this->store->save($saga));

        $context = new TestContext;

        $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

        /** @var MessageHandler $handler */
        $handler = \iterator_to_array($handlers)[0];

        wait(($handler->closure)(new EventWithKey(''), $context));

        $records = $context->logger->records;

        static::assertCount(1, $records);
        static::assertSame(
            'The value of the "key" property of the "ServiceBus\\Sagas\\Tests\\stubs\\EventWithKey" event can\'t be empty, since it is the saga id',
            $records[0]['context']['throwableMessage']
        );
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function executeWithCompletedSaga(): void
    {
        $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
        $saga = new CorrectSaga($id);

        invokeReflectionMethod($saga, 'makeExpired', 'fail reason');

        wait($this->store->save($saga));

        $context = new TestContext;

        $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

        /** @var MessageHandler $handler */
        $handler = \iterator_to_array($handlers)[0];

        wait(($handler->closure)(new EventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context));

        $records = $context->logger->records;

        static::assertCount(1, $records);
        static::assertSame(
            'Attempt to apply event to completed saga (ID: 1b6d89ec-cf60-4e48-a253-fd57f844c07d)',
            $records[0]['context']['throwableMessage']
        );
    }
}
