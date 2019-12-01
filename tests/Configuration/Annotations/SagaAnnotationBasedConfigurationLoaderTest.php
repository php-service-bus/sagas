<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations;

use function Amp\Promise\wait;
use PHPUnit\Framework\TestCase;
use ServiceBus\Common\MessageHandler\MessageHandler;
use ServiceBus\Sagas\Configuration\Annotations\SagaAnnotationBasedConfigurationLoader;
use ServiceBus\Sagas\Configuration\DefaultEventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\EventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration;
use ServiceBus\Sagas\Store\Sql\SQLSagaStore;
use ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWithIncorrectEventListenerClass;
use ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWithIncorrectListenerName;
use ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWithInvalidListenerArg;
use ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWithMultipleListenerArgs;
use ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWithToManyArguments;
use ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWithUnExistsEventListenerClass;
use ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWrongIdClassSpecified;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\CorrectSagaWithoutListeners;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;

/**
 *
 */
final class SagaAnnotationBasedConfigurationLoaderTest extends TestCase
{
    private DatabaseAdapter $adapter;

    private SQLSagaStore $store;

    private EventListenerProcessorFactory $listenerFactory;

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

        wait($this->adapter->execute(\file_get_contents(__DIR__ . '/../../../src/Store/Sql/schema/sagas_store.sql')));

        foreach (\file(__DIR__ . '/../../../src/Store/Sql/schema/indexes.sql') as $indexQuery)
        {
            wait($this->adapter->execute($indexQuery));
        }

        $this->store           = new SQLSagaStore($this->adapter);
        $this->listenerFactory = new DefaultEventListenerProcessorFactory($this->store);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->adapter);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithoutAnnotations(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);

        $object = new class()
        {
        };

        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))->load(\get_class($object));
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithIncorrectHeaderAnnotationData(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);
        $this->expectExceptionMessage(
            \sprintf(
                'In the meta data of the saga "%s" an incorrect value of the "idClass"',
                SagaWrongIdClassSpecified::class
            )
        );

        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWrongIdClassSpecified::class);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithoutListeners(): void
    {
        $result = (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(CorrectSagaWithoutListeners::class)
            ->handlerCollection;

        static::assertEmpty($result);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function correctSagaWithListeners(): void
    {
        $result = (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(CorrectSaga::class)
            ->handlerCollection;

        static::assertNotEmpty($result);
        static::assertCount(3, $result);

        foreach ($result as $messageHandler)
        {
            static::assertInstanceOf(MessageHandler::class, $messageHandler);
        }
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithUnExistsEventClass(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);

        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithUnExistsEventListenerClass::class);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithToManyListenerArguments(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);

        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithToManyArguments::class);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithIncorrectListenerClass(): void
    {
        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithIncorrectEventListenerClass::class);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithMultipleListenerArgs(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);
        $this->expectExceptionMessage(
            \sprintf(
                'There are too many arguments for the "%s:onEmptyEvent" method. A subscriber can only accept an '
                . 'argument: the class of the event he listens to',
                SagaWithMultipleListenerArgs::class
            )
        );

        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithMultipleListenerArgs::class);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithInvalidListenerArgument(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);
        $this->expectExceptionMessage('Invalid method name of the event listener: "onSomeEvent". Expected: onEmptyCommand');

        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithInvalidListenerArg::class);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function sagaWithIncorrectListenerName(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);
        $this->expectExceptionMessage(
            'Invalid method name of the event listener: "wrongEventListenerName". Expected: onEventWithKey'
        );

        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithIncorrectListenerName::class);
    }
}
