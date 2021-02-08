<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Configuration\Annotations;

use ServiceBus\Sagas\Configuration\Attributes\SagaAttributeBasedConfigurationLoader;
use function Amp\Promise\wait;
use PHPUnit\Framework\TestCase;
use ServiceBus\Common\MessageHandler\MessageHandler;
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
    /**
     * @var DatabaseAdapter
     */
    private $adapter;

    /**
     * @var EventListenerProcessorFactory
     */
    private $listenerFactory;

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

        $store                 = new SQLSagaStore($this->adapter);
        $this->listenerFactory = new DefaultEventListenerProcessorFactory($store);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->adapter);
    }

    /**
     * @test
     */
    public function sagaWithoutAnnotations(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);

        $object = new class()
        {
        };

        (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))->load(\get_class($object));
    }

    /**
     * @test
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

        (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWrongIdClassSpecified::class);
    }

    /**
     * @test
     */
    public function sagaWithoutListeners(): void
    {
        $result = (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(CorrectSagaWithoutListeners::class)
            ->handlerCollection;

        self::assertEmpty($result);
    }

    /**
     * @test
     */
    public function correctSagaWithListeners(): void
    {
        $result = (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(CorrectSaga::class)
            ->handlerCollection;

        self::assertNotEmpty($result);
        self::assertCount(3, $result);

        foreach ($result as $messageHandler)
        {
            self::assertInstanceOf(MessageHandler::class, $messageHandler);
        }
    }

    /**
     * @test
     */
    public function sagaWithUnExistsEventClass(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);

        (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithUnExistsEventListenerClass::class);
    }

    /**
     * @test
     */
    public function sagaWithToManyListenerArguments(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);

        (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithToManyArguments::class);
    }

    /**
     * @test
     */
    public function sagaWithIncorrectListenerClass(): void
    {
        (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithIncorrectEventListenerClass::class);
    }

    /**
     * @test
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

        (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithMultipleListenerArgs::class);
    }

    /**
     * @test
     */
    public function sagaWithInvalidListenerArgument(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);
        $this->expectExceptionMessage('Invalid method name of the event listener: "onSomeEvent". Expected: onEmptyCommand');

        (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithInvalidListenerArg::class);
    }

    /**
     * @test
     */
    public function sagaWithIncorrectListenerName(): void
    {
        $this->expectException(InvalidSagaConfiguration::class);
        $this->expectExceptionMessage(
            'Invalid method name of the event listener: "wrongEventListenerName". Expected: onEventWithKey'
        );

        (new SagaAttributeBasedConfigurationLoader($this->listenerFactory))
            ->load(SagaWithIncorrectListenerName::class);
    }
}
