<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests\Configuration;

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use ServiceBus\ArgumentResolver\ChainArgumentResolver;
use ServiceBus\ArgumentResolver\MessageArgumentResolver;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Sagas\Configuration\Attributes\SagaAttributeBasedConfigurationLoader;
use ServiceBus\Sagas\Configuration\MessageProcessor\DefaultSagaMessageProcessorFactory;
use ServiceBus\Sagas\Configuration\Metadata\SagaMetadata;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use ServiceBus\Sagas\Configuration\SagaIdLocator;
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
use function Amp\call;
use function Amp\Promise\wait;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\readReflectionPropertyValue;
use function ServiceBus\Common\writeReflectionPropertyValue;

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
     * @var SagaConfigurationLoader
     */
    private $configLoader;

    /**
     * @var callable
     */
    private $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new DoctrineDBALAdapter(
            new StorageConfiguration('sqlite:///:memory:')
        );

        $queries = \explode(
            ';',
            \file_get_contents(__DIR__ . '/../../src/Store/Sql/schema/sagas_store.sql')
        );

        foreach ($queries as $tableQuery)
        {
            wait($this->adapter->execute($tableQuery));
        }

        foreach (\file(__DIR__ . '/../../src/Store/Sql/schema/indexes.sql') as $indexQuery)
        {
            wait($this->adapter->execute($indexQuery));
        }

        $this->publisher = static function ()
        {
        };

        $this->store        = new SQLSagaStore($this->adapter);
        $this->configLoader = new SagaAttributeBasedConfigurationLoader(
            new DefaultSagaMessageProcessorFactory(
                sagaStore: $this->store,
                argumentResolver: new ChainArgumentResolver([  new MessageArgumentResolver()]),
                sagaIdLocator: new SagaIdLocator(
                    sagaStore: $this->store
                )
            )
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->adapter, $this->configLoader);
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

                yield $this->store->save($saga, $this->publisher);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                yield call($handler->closure, new EventWithKey($id->toString()), $context);

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

                yield $this->store->save($saga, $this->publisher);

                $context                                 = new TestContext();
                $context->headers['saga-correlation-id'] = $id->toString();

                $handlers = $this->configLoader->load(CorrectSagaWithHeaderCorrelationId::class)->listenerCollection;

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
        $this->expectExceptionMessage(
            'The value of the "saga-correlation-id" header key can\'t be empty, since it is the saga id'
        );

        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSagaWithHeaderCorrelationId::class);
                $saga = new CorrectSagaWithHeaderCorrelationId($id);

                yield $this->store->save($saga, $this->publisher);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSagaWithHeaderCorrelationId::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                yield call($handler->closure, new EventWithKey('qwerty'), $context);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithoutSaga(): void
    {
        $this->expectExceptionMessage(
            'Attempt to apply event to non-existent saga (ID: 1b6d89ec-cf60-4e48-a253-fd57f844c07d)'
        );

        Loop::run(
            function (): \Generator
            {
                $id = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                self::assertSame(EventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new EventWithKey($id->toString()), $context);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithoutCorrelationId(): void
    {
        $this->expectExceptionMessage(
            'A property that contains an identifier ("requestId") was not found in class "ServiceBus\\Sagas\\Tests\\stubs\\EmptyEvent"'
        );

        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga, $this->publisher);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[2];

                self::assertSame(EmptyEvent::class, $handler->messageClass);

                yield call($handler->closure, new EmptyEvent(), $context);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithEmptyCorrelationId(): void
    {
        $this->expectExceptionMessage(
            'The value of the "key" property of the "ServiceBus\Sagas\Tests\stubs\EventWithKey" event can\'t be empty. The property must contain either a string or an object with the `toString()` method'
        );

        Loop::run(
            function (): \Generator
            {
                $id   = TestSagaId::new(CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga, $this->publisher);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                self::assertSame(EventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new EventWithKey(''), $context);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithCompletedSaga(): void
    {
        $this->expectExceptionMessage(
            'Attempt to apply event to completed saga (ID: 1b6d89ec-cf60-4e48-a253-fd57f844c07d)'
        );

        Loop::run(
            function (): \Generator
            {
                $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                $saga = new CorrectSaga($id);

                invokeReflectionMethod($saga, 'expire', 'fail reason');

                yield $this->store->save($saga, $this->publisher);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[0];

                self::assertSame(EventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new EventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

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

                yield $this->store->save($saga, $this->publisher);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[1];

                self::assertSame(SecondEventWithKey::class, $handler->messageClass);

                yield call(
                    $handler->closure,
                    new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'),
                    $context
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithUnknownIdClass(): void
    {
        $this->expectExceptionMessage('Class "SomeUnknownClass" not found');

        Loop::run(
            function (): \Generator
            {
                $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga, $this->publisher);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[1];

                /** @var \ServiceBus\Sagas\Configuration\Metadata\SagaHandlerOptions $options */
                $options = readReflectionPropertyValue($handler, 'options');

                /** @var SagaMetadata $metadata */
                $metadata = readReflectionPropertyValue($options, 'sagaMetadata');

                writeReflectionPropertyValue($metadata, 'identifierClass', 'SomeUnknownClass');

                self::assertSame(SecondEventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function executeWithIncorrectIdClassType(): void
    {
        $this->expectExceptionMessage(
            'Saga identifier must be type of "ServiceBus\Sagas\SagaId". "ServiceBus\Sagas\Tests\stubs\IncorrectSagaIdType" type specified'
        );

        Loop::run(
            function (): \Generator
            {
                $id   = new TestSagaId('1b6d89ec-cf60-4e48-a253-fd57f844c07d', CorrectSaga::class);
                $saga = new CorrectSaga($id);

                yield $this->store->save($saga, $this->publisher);

                $context = new TestContext();

                $handlers = $this->configLoader->load(CorrectSaga::class)->listenerCollection;

                /** @var MessageHandler $handler */
                $handler = \iterator_to_array($handlers)[1];

                /** @var \ServiceBus\Sagas\Configuration\Metadata\SagaHandlerOptions $options */
                $options = readReflectionPropertyValue($handler, 'options');

                /** @var SagaMetadata $metadata */
                $metadata = readReflectionPropertyValue($options, 'sagaMetadata');

                writeReflectionPropertyValue($metadata, 'identifierClass', IncorrectSagaIdType::class);

                self::assertSame(SecondEventWithKey::class, $handler->messageClass);

                yield call($handler->closure, new SecondEventWithKey('1b6d89ec-cf60-4e48-a253-fd57f844c07d'), $context);

                Loop::stop();
            }
        );
    }
}
