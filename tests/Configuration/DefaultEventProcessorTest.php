<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration;

use Amp\Loop;
use ServiceBus\Sagas\Configuration\Attributes\SagaAttributeBasedConfigurationLoader;
use function Amp\call;
use function Amp\Promise\wait;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Common\writeReflectionPropertyValue;
use PHPUnit\Framework\TestCase;
use ServiceBus\Common\MessageHandler\MessageHandler;
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
        $this->configLoader    = new SagaAttributeBasedConfigurationLoader(
            new DefaultEventListenerProcessorFactory($this->store)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->adapter, $this->listenerFactory, $this->configLoader);
    }

    /**
     * @test
     */
    public function successExecute(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                /** @var bool $saved */
                $saved = yield call($handler->closure, new EventWithKey($id->toString()), $context);

                self::assertTrue($saved);

                $messages = $context->messages;

                /** @var SecondEventWithKey $event */
                $event = \end($messages);

                self::assertInstanceOf(SecondEventWithKey::class, $event, get_class($event));
                self::assertSame($id->toString(), $event->key);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function successExecuteWithHeaderValue(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSagaWithHeaderCorrelationId::class);
                $saga = new CorrectSagaWithHeaderCorrelationId($id);

                yield $this->store->save($saga);

                $context                                 = new TestContext();
                $context->headers['saga-correlation-id'] = $id->toString();

                $handlers = $this->configLoader->load(CorrectSagaWithHeaderCorrelationId::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                yield call($handler->closure, new EventWithKey('qwerty'), $context);

                $messages = $context->messages;

                /** @var SecondEventWithKey $event */
                $event = \end($messages);

                self::assertInstanceOf(SecondEventWithKey::class, $event);
                self::assertSame('qwerty', $event->key);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithoutHeaderValue(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSagaWithHeaderCorrelationId::class);
                $saga = new CorrectSagaWithHeaderCorrelationId($id);

                yield $this->store->save($saga);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSagaWithHeaderCorrelationId::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                yield call($handler->closure, new EventWithKey('qwerty'), $context);

                $records = $context->logger->records;

                self::assertCount(1, $records);
                self::assertSame(
                    'The value of the "saga-correlation-id" header key can\'t be empty, since it is the saga id',
                    $records[0]['context']['throwableMessage']
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithoutSaga(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                self::assertSame(EventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new EventWithKey($id->toString()), $context);

                $records = $context->logger->records;

                self::assertSame(EventWithKey::class, $handler->messageClass);
                self::assertCount(1, $records);
                self::assertSame(
                    'Attempt to apply event to non-existent saga (ID: 1b6d89ec-cf60-4e48-a253-fd57f844c07d)',
                    $records[0]['context']['throwableMessage']
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithoutCorrelationId(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[2];

                self::assertSame(EmptyEvent::class, $handler->messageClass);

                yield call($handler->closure, new EmptyEvent(), $context);

                $records = $context->logger->records;

                self::assertCount(1, $records);
                self::assertSame(
                    'A property that contains an identifier ("requestId") was not found in class "ServiceBus\\Sagas\\Tests\\stubs\\EmptyEvent"',
                    $records[0]['context']['throwableMessage']
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithEmptyCorrelationId(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                self::assertSame(EventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new EventWithKey(''), $context);

                $records = $context->logger->records;

                self::assertCount(1, $records);
                self::assertSame(
                    'The value of the "key" property of the "ServiceBus\\Sagas\\Tests\\stubs\\EventWithKey" event can\'t be empty, since it is the saga id',
                    $records[0]['context']['throwableMessage']
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithCompletedSaga(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                $saga = new CorrectSaga($id);

                invokeReflectionMethod($saga, 'expire', 'fail reason');

                yield $this->store->save($saga);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                self::assertSame(EventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new EventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

                $records = $context->logger->records;

                self::assertCount(1, $records);
                self::assertSame(
                    'Attempt to apply event to completed saga (ID: 1b6d89ec-cf60-4e48-a253-fd57f844c07d)',
                    $records[0]['context']['throwableMessage']
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithNoChanges(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[1];

                self::assertSame(SecondEventWithKey::class, $handler->messageClass);

                /** @var bool $stored */
                $stored = yield call(
                    $handler->closure,
                    new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'),
                    $context
                );

                self::assertFalse($stored);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithUnknownIdClass(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[1];

                /** @var \ServiceBus\Sagas\Configuration\SagaListenerOptions $options */
                $options = readReflectionPropertyValue($handler, 'options');

                /** @var SagaMetadata $metadata */
                $metadata = readReflectionPropertyValue($options, 'sagaMetadata');

                writeReflectionPropertyValue($metadata, 'identifierClass', 'SomeUnknownClass');

                self::assertSame(SecondEventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

                $records = $context->logger->records;

                self::assertCount(1, $records);

                /** @var array $record */
                $record = \reset($records);

                self::assertSame(
                    'Identifier class "SomeUnknownClass" specified in the saga "ServiceBus\Sagas\Tests\stubs\CorrectSaga" not found',
                    $record['message']
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithIncorrectIdClassType(): void
    {
        Loop::run(
            function (): \Generator
            {
                $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->handlerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[1];

                /** @var \ServiceBus\Sagas\Configuration\SagaListenerOptions $options */
                $options = readReflectionPropertyValue($handler, 'options');

                /** @var SagaMetadata $metadata */
                $metadata = readReflectionPropertyValue($options, 'sagaMetadata');

                writeReflectionPropertyValue($metadata, 'identifierClass', IncorrectSagaIdType::class);

                self::assertSame(SecondEventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

                $records = $context->logger->records;

                self::assertCount(1, $records);

                /** @var array $record */
                $record = \reset($records);

                self::assertSame(
                    'Saga identifier mus be type of "ServiceBus\Sagas\SagaId". "ServiceBus\Sagas\Tests\stubs\IncorrectSagaIdType" type specified',
                    $record['message']
                );

                Loop::stop();
            }
        );
    }
}
