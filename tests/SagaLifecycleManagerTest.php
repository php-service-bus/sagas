<?php

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests;

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\Sagas\Contract\SagaReopened;
use ServiceBus\Sagas\Exceptions\ReopenFailed;
use ServiceBus\Sagas\Module\SagaModule;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaFinder;
use ServiceBus\Sagas\SagaLifecycleManager;
use ServiceBus\Sagas\Store\SagasStore;
use ServiceBus\Sagas\Tests\stubs\TestContext;
use ServiceBus\Sagas\Tests\stubs\TestSaga;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function Amp\Promise\wait;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\invokeReflectionMethod;

final class SagaLifecycleManagerTest extends TestCase
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

    /**
     * @var SagasStore
     */
    private $sagaStore;

    /**
     * @var SagaLifecycleManager
     */
    private $lifecycleManager;

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
        $this->containerBuilder->getDefinition(SagasStore::class)->setPublic(true);
        $this->containerBuilder->getDefinition(SagaLifecycleManager::class)->setPublic(true);
        $this->containerBuilder->getDefinition(Router::class)->setPublic(true);

        $this->containerBuilder->compile();

        $this->adapter          = $this->containerBuilder->get(DatabaseAdapter::class);
        $this->sagaFinder       = $this->containerBuilder->get(SagaFinder::class);
        $this->sagaStore        = $this->containerBuilder->get(SagasStore::class);
        $this->lifecycleManager = $this->containerBuilder->get(SagaLifecycleManager::class);

        $queries = \explode(
            ';',
            \file_get_contents(__DIR__ . '/../src/Store/Sql/schema/sagas_store.sql')
        );

        foreach ($queries as $tableQuery)
        {
            wait($this->adapter->execute($tableQuery));
        }

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
    public function successReopen(): void
    {
        Loop::run(
            function (): \Generator
            {
                $context = new TestContext();

                $id   = TestSagaId::new(TestSaga::class);
                $saga = new TestSaga(
                    id: $id,
                    expireDate: datetimeInstantiator('+1 day'),
                    createdAt: datetimeInstantiator('now')
                );

                invokeReflectionMethod($saga, 'fail');

                self::assertNotNull($saga->closedAt());

                yield $this->sagaStore->save(
                    $saga,
                    static function ()
                    {
                    }
                );

                /** @var \DateTimeImmutable $newExpireDate */
                $newExpireDate = datetimeInstantiator('+2 day');

                yield $this->lifecycleManager->reopen($id, $context, $newExpireDate, 'testing');

                $messages = $context->messages;

                /** @var \ServiceBus\Sagas\Contract\SagaReopened $latestEvent */
                $latestEvent = \end($messages);

                self::assertInstanceOf(SagaReopened::class, $latestEvent);

                /** @var Saga $loadedSaga */
                $loadedSaga = yield $this->sagaStore->obtain($id);

                self::assertNull($loadedSaga->closedAt());
                self::assertSame(
                    $newExpireDate->format('Y-m-d H:i:s'),
                    $loadedSaga->expireDate()->format('Y-m-d H:i:s')
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function reopenInProgressSaga(): void
    {
        $this->expectException(ReopenFailed::class);
        $this->expectExceptionMessage('Unable to open unfinished saga `ccfd1b8e-be8b-4f69-b1ca-c92a14379558`');

        Loop::run(
            function (): \Generator
            {
                $context = new TestContext();

                $id   = new TestSagaId('ccfd1b8e-be8b-4f69-b1ca-c92a14379558', TestSaga::class);
                $saga = new TestSaga(
                    id: $id,
                    expireDate: datetimeInstantiator('+1 day'),
                    createdAt: datetimeInstantiator('now')
                );

                yield $this->sagaStore->save(
                    $saga,
                    static function ()
                    {
                    }
                );

                yield $this->lifecycleManager->reopen(
                    $id,
                    $context,
                    datetimeInstantiator('+1 day'),
                    'testing'
                );
            }
        );
    }

    /**
     * @test
     */
    public function reopenUnknownSaga(): void
    {
        $this->expectException(ReopenFailed::class);
        $this->expectExceptionMessage('Saga `ccfd1b8e-be8b-4f69-b1ca-c92a14379558` doesn\'t exists');

        Loop::run(
            function (): \Generator
            {
                $context = new TestContext();

                $id = new TestSagaId('ccfd1b8e-be8b-4f69-b1ca-c92a14379558', TestSaga::class);

                yield $this->lifecycleManager->reopen(
                    $id,
                    $context,
                    datetimeInstantiator('+1 day'),
                    'testing'
                );
            }
        );
    }
}
