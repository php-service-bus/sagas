<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations;

use function Amp\Promise\wait;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use ServiceBus\Sagas\Configuration\Annotations\SagaAnnotationBasedConfigurationLoader;
use ServiceBus\Sagas\Configuration\DefaultEventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\EventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\EventProcessor;
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

        wait($this->adapter->execute(\file_get_contents(__DIR__ . '/../../../src/Store/Sql/schema/sagas_store.sql')));

        foreach(\file(__DIR__ . '/../../../src/Store/Sql/schema/indexes.sql') as $indexQuery)
        {
            wait($this->adapter->execute($indexQuery));
        }

        $this->store           = new SQLSagaStore($this->adapter);
        $this->listenerFactory = new DefaultEventListenerProcessorFactory($this->store);
    }

    /**
     * @inheritdoc
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
     * @expectedException \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function sagaWithoutAnnotations(): void
    {
        $object = new class()
        {

        };

        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))->load(\get_class($object));
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     * @expectedExceptionMessage In the meta data of the saga "ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWrongIdClassSpecified",
     *                           an incorrect value of the "idClass"
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function sagaWithIncorrectHeaderAnnotationData(): void
    {
        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWrongIdClassSpecified::class);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function sagaWithoutListeners(): void
    {
        $result = (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(CorrectSagaWithoutListeners::class)
            ->processorCollection;

        static::assertEmpty($result);
    }

    /**
     * @test
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function correctSagaWithListeners(): void
    {
        $result = (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(CorrectSaga::class)
            ->processorCollection;

        static::assertNotEmpty($result);
        static::assertCount(3, $result);

        foreach($result as $messageHandler)
        {
            /** @var \ServiceBus\Sagas\Configuration\EventProcessor $messageHandler */
            static::assertInstanceOf(EventProcessor::class, $messageHandler);
            static::assertThat($messageHandler, new IsType('callable'));
        }
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function sagaWithUnExistsEventClass(): void
    {
        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithUnExistsEventListenerClass::class);
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function sagaWithToManyListenerArguments(): void
    {
        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithToManyArguments::class);
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     *
     * @return void
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
     * @expectedException \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     * @expectedExceptionMessage There are too many arguments for the "ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWithMultipleListenerArgs:onEmptyEvent"
     *                           method. A subscriber can only accept an argument: the class of the event he listens to
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function sagaWithMultipleListenerArgs(): void
    {
        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithMultipleListenerArgs::class);
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     * @expectedExceptionMessage The event handler "ServiceBus\Sagas\Tests\Configuration\Annotations\stubs\SagaWithInvalidListenerArg:onSomeEvent"
     *                           should take as the first argument an object that implements the "ServiceBus\Common\Messages\Event"
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function sagaWithInvalidListenerArgument(): void
    {
        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithInvalidListenerArg::class);
    }

    /**
     * @test
     * @expectedException \ServiceBus\Sagas\Configuration\Exceptions\InvalidSagaConfiguration
     * @expectedExceptionMessage Invalid method name of the event listener: "wrongEventListenerName". Expected: onEventWithKey
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function sagaWithIncorrectListenerName(): void
    {
        (new SagaAnnotationBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithIncorrectListenerName::class);
    }
}
