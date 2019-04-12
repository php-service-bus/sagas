<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration;

use function Amp\call;
use function Amp\Promise\wait;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Common\writeReflectionPropertyValue;
use PHPUnit\Framework\TestCase;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Sagas\Configuration\Annotations\SagaAnnotationBasedConfigurationLoader;
use ServiceBus\Sagas\Configuration\DefaultEventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\EventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use ServiceBus\Sagas\Configuration\SagaMetadata;
use ServiceBus\Sagas\Store\Sql\SQLSagaStore;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\CorrectSagaWithHeaderCorrelationId;
use ServiceBus\Sagas\Tests\stubs\EmptyEvent;
use ServiceBus\Sagas\Tests\stubs\EventWithKey;
use ServiceBus\Sagas\Tests\stubs\IncorrectSagaIdType;
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
     * {@inheritdoc}
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

        foreach (\file(__DIR__ . '/../../src/Store/Sql/schema/indexes.sql') as $indexQuery)
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
     * {@inheritdoc}
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
     * @throws \Throwable
     *
     * @return void
     */
    public function successExecute(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = TestSagaId::new(CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSaga::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[0];

                    /** @var bool $saved */
                    $saved = yield call($handler->closure, new EventWithKey($id->toString()), $context);

                    static::assertTrue($saved);

                    $messages = $context->messages;

                    /** @var SecondEventWithKey $event */
                    $event = \end($messages);

                    static::assertInstanceOf(SecondEventWithKey::class, $event);
                    static::assertSame($id->toString(), $event->key);
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function successExecuteWithHeaderValue(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = TestSagaId::new(CorrectSagaWithHeaderCorrelationId::class);
                    $saga = new CorrectSagaWithHeaderCorrelationId($id);

                    yield $store->save($saga);

                    $context                                 = new TestContext();
                    $context->headers['saga-correlation-id'] = $id->toString();

                    $handlers = $configLoader->load(CorrectSagaWithHeaderCorrelationId::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[0];

                    yield call($handler->closure, new EventWithKey('qwerty'), $context);

                    $messages = $context->messages;

                    /** @var SecondEventWithKey $event */
                    $event = \end($messages);

                    static::assertInstanceOf(SecondEventWithKey::class, $event);
                    static::assertSame('qwerty', $event->key);
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function executeWithoutHeaderValue(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = TestSagaId::new(CorrectSagaWithHeaderCorrelationId::class);
                    $saga = new CorrectSagaWithHeaderCorrelationId($id);

                    yield $store->save($saga);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSagaWithHeaderCorrelationId::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[0];

                    yield call($handler->closure, new EventWithKey('qwerty'), $context);

                    $records = $context->logger->records;

                    static::assertCount(1, $records);
                    static::assertSame(
                        'The value of the "saga-correlation-id" header key can\'t be empty, since it is the saga id',
                        $records[0]['context']['throwableMessage']
                    );
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function executeWithoutSaga(): void
    {
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($configLoader): \Generator
                {
                    $id = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSaga::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[0];

                    static::assertSame(EventWithKey::class, $handler->messageClass);

                    yield call($handler->closure, new EventWithKey($id->toString()), $context);

                    $records = $context->logger->records;

                    static::assertSame(EventWithKey::class, $handler->messageClass);
                    static::assertCount(1, $records);
                    static::assertSame(
                        'Attempt to apply event to non-existent saga (ID: 1b6d89ec-cf60-4e48-a253-fd57f844c07d)',
                        $records[0]['context']['throwableMessage']
                    );
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function executeWithoutCorrelationId(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = TestSagaId::new(CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSaga::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[2];

                    static::assertSame(EmptyEvent::class, $handler->messageClass);

                    yield call($handler->closure, new EmptyEvent(), $context);

                    $records = $context->logger->records;

                    static::assertCount(1, $records);
                    static::assertSame(
                        'A property that contains an identifier ("requestId") was not found in class "ServiceBus\\Sagas\\Tests\\stubs\\EmptyEvent"',
                        $records[0]['context']['throwableMessage']
                    );
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function executeWithEmptyCorrelationId(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = TestSagaId::new(CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSaga::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[0];

                    static::assertSame(EventWithKey::class, $handler->messageClass);

                    yield call($handler->closure, new EventWithKey(''), $context);

                    $records = $context->logger->records;

                    static::assertCount(1, $records);
                    static::assertSame(
                        'The value of the "key" property of the "ServiceBus\\Sagas\\Tests\\stubs\\EventWithKey" event can\'t be empty, since it is the saga id',
                        $records[0]['context']['throwableMessage']
                    );
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function executeWithCompletedSaga(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    invokeReflectionMethod($saga, 'makeExpired', 'fail reason');

                    yield $store->save($saga);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSaga::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[0];

                    static::assertSame(EventWithKey::class, $handler->messageClass);

                    yield call($handler->closure, new EventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

                    $records = $context->logger->records;

                    static::assertCount(1, $records);
                    static::assertSame(
                        'Attempt to apply event to completed saga (ID: 1b6d89ec-cf60-4e48-a253-fd57f844c07d)',
                        $records[0]['context']['throwableMessage']
                    );
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function executeWithNoChanges(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSaga::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[1];

                    static::assertSame(SecondEventWithKey::class, $handler->messageClass);

                    /** @var bool $stored */
                    $stored = yield call(
                        $handler->closure,
                        new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'),
                        $context
                    );

                    static::assertFalse($stored);
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function executeWithUnknownIdClass(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSaga::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[1];

                    /** @var \ServiceBus\Sagas\Configuration\SagaListenerOptions $options */
                    $options = readReflectionPropertyValue($handler, 'options');

                    /** @var SagaMetadata $metadata */
                    $metadata = readReflectionPropertyValue($options, 'sagaMetadata');

                    writeReflectionPropertyValue($metadata, 'identifierClass', 'SomeUnknownClass');

                    static::assertSame(SecondEventWithKey::class, $handler->messageClass);

                    yield call($handler->closure, new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

                    $records = $context->logger->records;

                    static::assertCount(1, $records);

                    /** @var array $record */
                    $record = \reset($records);

                    static::assertSame(
                        'Identifier class "SomeUnknownClass" specified in the saga "ServiceBus\Sagas\Tests\stubs\CorrectSaga" not found',
                        $record['message']
                    );
                }
            )
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function executeWithIncorrectIdClassType(): void
    {
        $store        = $this->store;
        $configLoader = $this->configLoader;

        wait(
            call(
                static function() use ($store, $configLoader): \Generator
                {
                    $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                    $saga = new CorrectSaga($id);

                    yield $store->save($saga);

                    $context = new TestContext();

                    $handlers = $configLoader->load(CorrectSaga::class)->handlerCollection;

                    /** @var MessageHandler $handler */
                    $handler = \iterator_to_array($handlers)[1];

                    /** @var \ServiceBus\Sagas\Configuration\SagaListenerOptions $options */
                    $options = readReflectionPropertyValue($handler, 'options');

                    /** @var SagaMetadata $metadata */
                    $metadata = readReflectionPropertyValue($options, 'sagaMetadata');

                    writeReflectionPropertyValue($metadata, 'identifierClass', IncorrectSagaIdType::class);

                    static::assertSame(SecondEventWithKey::class, $handler->messageClass);

                    yield call($handler->closure, new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

                    $records = $context->logger->records;

                    static::assertCount(1, $records);

                    /** @var array $record */
                    $record = \reset($records);

                    static::assertSame(
                        'Saga identifier mus be type of "ServiceBus\Sagas\SagaId". "ServiceBus\Sagas\Tests\stubs\IncorrectSagaIdType" type specified',
                        $record['message']
                    );
                }
            )
        );
    }
}
