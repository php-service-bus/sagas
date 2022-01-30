<?php

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests;

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\Sagas\Module\SagaModule;
use ServiceBus\Sagas\SagaFinder;
use ServiceBus\Sagas\SagaId;
use ServiceBus\Sagas\SagaMessageExecutor;
use ServiceBus\Sagas\SagasProvider;
use ServiceBus\Sagas\Store\Exceptions\DuplicateSaga;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\CorrectSagaInitialCommand;
use ServiceBus\Sagas\Tests\stubs\TestContext;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function Amp\Promise\wait;

final class SagaInitialCommandTest extends TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $containerBuilder;

    /**
     * @var DatabaseAdapter
     */
    private $adapter;

    /**
     * @var SagaFinder
     */
    private $sagaFinder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->containerBuilder = new ContainerBuilder();

        $this->containerBuilder->addDefinitions([
            StorageConfiguration::class => new Definition(StorageConfiguration::class, ['sqlite:///:memory:']),
            DatabaseAdapter::class      => (new Definition(DoctrineDBALAdapter::class))
                ->setArguments([new Reference(StorageConfiguration::class)]),
        ]);

        SagaModule::withSqlStorage(DatabaseAdapter::class)
            ->enableAutoImportSagas([__DIR__ . '/stubs'])
            ->boot($this->containerBuilder);

        $this->containerBuilder->getDefinition(SagaFinder::class)->setPublic(true);
        $this->containerBuilder->getDefinition(DatabaseAdapter::class)->setPublic(true);
        $this->containerBuilder->getDefinition(Router::class)->setPublic(true);

        $this->containerBuilder->compile();

        $this->adapter    = $this->containerBuilder->get(DatabaseAdapter::class);
        $this->sagaFinder = $this->containerBuilder->get(SagaFinder::class);

        wait(
            $this->adapter->execute(
                \file_get_contents(
                    __DIR__ . '/../src/Store/Sql/schema/sagas_store.sql'
                )
            )
        );

        $indexQueries = \file(__DIR__ . '/../src/Store/Sql/schema/indexes.sql');

        foreach ($indexQueries as $query)
        {
            wait($this->adapter->execute($query));
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        try
        {
            wait($this->adapter->execute('DELETE FROM sagas_store'));
        }
        catch (\Throwable)
        {
        }

        unset($this->containerBuilder, $this->adapter, $this->sagaFinder);
    }

    /**
     * @test
     */
    public function successStartWithCommand(): void
    {
        /** @var Router $messageRouter */
        $messageRouter = $this->containerBuilder->get(Router::class);

        $sagaId = TestSagaId::new(CorrectSaga::class);

        $message = new CorrectSagaInitialCommand($sagaId->id);
        $context = new TestContext();

        $handlers = $messageRouter->match($message);

        self::assertNotEmpty($handlers);
        self::assertCount(1, $handlers);

        /** @var SagaMessageExecutor $commandHandler */
        $commandHandler = \end($handlers);

        Loop::run(
            function () use ($commandHandler, $sagaId, $message, $context): \Generator
            {
                yield $commandHandler($message, $context);
                yield $this->sagaFinder->load(
                    id: $sagaId,
                    context: $context,
                    onLoaded: static function (): void
                    {
                        self::assertTrue(true);

                        Loop::stop();
                    }
                );
            }
        );
    }

    /**
     * @test
     */
    public function startDuplicateSaga(): void
    {
        $this->expectException(DuplicateSaga::class);

        /** @var Router $messageRouter */
        $messageRouter = $this->containerBuilder->get(Router::class);

        $sagaId = TestSagaId::new(CorrectSaga::class);

        $message = new CorrectSagaInitialCommand($sagaId->id);
        $context = new TestContext();

        $handlers = $messageRouter->match($message);

        self::assertNotEmpty($handlers);
        self::assertCount(1, $handlers);

        /** @var SagaMessageExecutor $commandHandler */
        $commandHandler = \end($handlers);

        Loop::run(
            static function () use ($commandHandler, $message, $context): \Generator
            {
                yield $commandHandler($message, $context);
                yield $commandHandler($message, $context);

                Loop::stop();
            }
        );
    }
}
