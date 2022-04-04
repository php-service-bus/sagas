<?php

declare(strict_types=1);

namespace ServiceBus\Sagas\Tests;

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\Sagas\Exceptions\SagaNotFound;
use ServiceBus\Sagas\Module\SagaModule;
use ServiceBus\Sagas\SagaFinder;
use ServiceBus\Sagas\Store\Exceptions\LoadedExpiredSaga;
use ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed;
use ServiceBus\Sagas\Store\SagasStore;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Sagas\Tests\stubs\TestContext;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function Amp\Promise\wait;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\updateQuery;

final class SagaFinderTest extends TestCase
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
        $this->containerBuilder->getDefinition(Router::class)->setPublic(true);

        $this->containerBuilder->compile();

        $this->adapter    = $this->containerBuilder->get(DatabaseAdapter::class);
        $this->sagaFinder = $this->containerBuilder->get(SagaFinder::class);
        $this->sagaStore  = $this->containerBuilder->get(SagasStore::class);

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
    public function obtainNonexistentSaga(): void
    {
        $this->expectException(SagaNotFound::class);

        Loop::run(
            function (): \Generator
            {
                yield $this->sagaFinder->load(
                    id: TestSagaId::new(CorrectSaga::class),
                    context: new TestContext(),
                    onLoaded: static function (): void
                    {
                    }
                );
            }
        );
    }

    /**
     * @test
     */
    public function obtainWithoutSchema(): void
    {
        $this->expectException(SagasStoreInteractionFailed::class);

        Loop::run(
            function (): \Generator
            {
                yield $this->adapter->execute('DROP TABLE sagas_store');
                yield $this->sagaFinder->load(
                    id: TestSagaId::new(CorrectSaga::class),
                    context: new TestContext(),
                    onLoaded: static function (): void
                    {
                    }
                );
            }
        );
    }

    /**
     * @test
     */
    public function obtainExpiredSaga(): void
    {
        $this->expectException(LoadedExpiredSaga::class);

        Loop::run(
            function (): \Generator
            {
                $saga = new CorrectSaga(
                    id: TestSagaId::new(CorrectSaga::class),
                    expireDate: datetimeInstantiator('+1 day'),
                    createdAt: datetimeInstantiator('now')
                );

                yield $this->sagaStore->save(
                    $saga,
                    static function ()
                    {
                    }
                );

                $query = updateQuery(
                    'sagas_store',
                    ['expiration_date' => \date('c', \strtotime('-1 hour'))]
                )
                    ->where(equalsCriteria("id", $saga->id()->id))
                    ->compile();

                yield $this->adapter->execute($query->sql(), $query->params());
                yield $this->sagaFinder->load(
                    id: $saga->id(),
                    context: new TestContext(),
                    onLoaded: static function ()
                    {
                    }
                );
            }
        );
    }
}
