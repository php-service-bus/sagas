<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation module.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests;

use Amp\Loop;
use ServiceBus\Sagas\Contract\SagaReopened;
use ServiceBus\Sagas\Exceptions\CantSaveUnStartedSaga;
use ServiceBus\Sagas\Exceptions\ReopenFailed;
use ServiceBus\Sagas\Exceptions\SagaMetaDataNotFound;
use ServiceBus\Sagas\SagasProvider;
use ServiceBus\Sagas\Tests\stubs\TestContext;
use ServiceBus\Sagas\Tests\stubs\TestSaga;
use ServiceBus\Sagas\Tests\stubs\TestSagaId;
use function Amp\Promise\wait;
use PHPUnit\Framework\TestCase;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\MessagesRouter\Tests\stubs\TestCommand;
use ServiceBus\Sagas\Module\SagaModule;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Store\Exceptions\DuplicateSaga;
use ServiceBus\Sagas\Store\Exceptions\LoadedExpiredSaga;
use ServiceBus\Sagas\Store\Exceptions\SagasStoreInteractionFailed;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function ServiceBus\Common\datetimeInstantiator;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\updateQuery;

/**
 *
 */
final class SagasProviderTest extends TestCase
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
     * @var SagasProvider
     */
    private $sagaProvider;

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

        $this->containerBuilder->getDefinition(SagasProvider::class)->setPublic(true);
        $this->containerBuilder->getDefinition(DatabaseAdapter::class)->setPublic(true);
        $this->containerBuilder->getDefinition(Router::class)->setPublic(true);

        $this->containerBuilder->compile();

        $this->adapter      = $this->containerBuilder->get(DatabaseAdapter::class);
        $this->sagaProvider = $this->containerBuilder->get(SagasProvider::class);

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

        unset($this->containerBuilder, $this->adapter, $this->sagaProvider);
    }

    /**
     * @test
     */
    public function updateNonexistentSaga(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(CantSaveUnStartedSaga::class);

                $testSaga = new TestSaga(TestSagaId::new(TestSaga::class));

                yield $this->sagaProvider->save($testSaga, new TestContext());

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function startWithoutMetadata(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(SagaMetaDataNotFound::class);

                $id = TestSagaId::new(TestSaga::class);

                yield $this->sagaProvider->start($id, new TestCommand(), new TestContext());

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function start(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->containerBuilder->get(Router::class);

                $id = TestSagaId::new(TestSaga::class);

                /** @var Saga $saga */
                $saga = yield $this->sagaProvider->start($id, new TestCommand(), new TestContext());

                self::assertNotNull($saga);
                self::assertInstanceOf(TestSaga::class, $saga);
                self::assertSame($id, $saga->id());

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function startDuplicate(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(DuplicateSaga::class);

                $this->containerBuilder->get(Router::class);

                $id = TestSagaId::new(TestSaga::class);

                yield $this->sagaProvider->start($id, new TestCommand(), new TestContext());
                yield $this->sagaProvider->start($id, new TestCommand(), new TestContext());

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function startWithoutSchema(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(SagasStoreInteractionFailed::class);

                yield $this->adapter->execute('DROP TABLE sagas_store');

                $this->containerBuilder->get(Router::class);

                yield $this->sagaProvider->start(TestSagaId::new(TestSaga::class), new TestCommand(), new TestContext());

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function obtainWithoutSchema(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(SagasStoreInteractionFailed::class);

                $this->containerBuilder->get(Router::class);

                yield $this->adapter->execute('DROP TABLE sagas_store');
                yield $this->sagaProvider->obtain(TestSagaId::new(TestSaga::class), new TestContext());

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function saveWithoutSchema(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(SagasStoreInteractionFailed::class);

                yield $this->adapter->execute('DROP TABLE sagas_store');

                $testSaga = new TestSaga(TestSagaId::new(TestSaga::class));

                yield $this->sagaProvider->save($testSaga, new TestContext());

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function obtainNonexistentSaga(): void
    {
        Loop::run(
            function (): \Generator
            {
                self::assertNull(
                    yield $this->sagaProvider->obtain(TestSagaId::new(TestSaga::class), new TestContext())
                );

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function obtainExpiredSaga(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(LoadedExpiredSaga::class);

                $this->containerBuilder->get(Router::class);

                $context = new TestContext();

                $id = TestSagaId::new(TestSaga::class);

                yield $this->sagaProvider->start($id, new TestCommand(), $context);

                $query = updateQuery(
                    'sagas_store',
                    ['expiration_date' => \date('c', \strtotime('-1 hour'))]
                )
                    ->where(equalsCriteria("id", $id->id))
                    ->compile();

                yield $this->adapter->execute($query->sql(), $query->params());
                yield $this->sagaProvider->obtain($id, $context);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function obtain(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->containerBuilder->get(Router::class);

                $context = new TestContext();

                $id = TestSagaId::new(TestSaga::class);

                /** @var Saga $saga */
                $saga = yield $this->sagaProvider->start($id, new TestCommand(), $context);

                yield $this->sagaProvider->save($saga, $context);

                /** @var Saga $loadedSaga */
                $loadedSaga = yield $this->sagaProvider->obtain($id, $context);

                self::assertSame($saga->id()->id, $loadedSaga->id()->id);

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function successReopen(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->containerBuilder->get(Router::class);

                $context = new TestContext();

                $id = TestSagaId::new(TestSaga::class);

                /** @var Saga $saga */
                $saga = yield $this->sagaProvider->start($id, new TestCommand(), $context);

                invokeReflectionMethod($saga, 'makeFailed');

                self::assertNotNull($saga->closedAt());

                yield $this->sagaProvider->save($saga, $context);

                /** @var \DateTimeImmutable $newExpireDate */
                $newExpireDate = datetimeInstantiator('+2 day');

                yield $this->sagaProvider->reopen($id, $context, $newExpireDate, 'testing');

                $messages = $context->messages;

                /** @var \ServiceBus\Sagas\Contract\SagaReopened $latestEvent */
                $latestEvent = \end($messages);

                self::assertInstanceOf(SagaReopened::class, $latestEvent);

                /** @var Saga $loadedSaga */
                $loadedSaga = yield $this->sagaProvider->obtain($id, $context);

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
        Loop::run(
            function (): \Generator
            {
                $this->expectException(ReopenFailed::class);
                $this->expectExceptionMessage('Unable to open unfinished saga `ccfd1b8e-be8b-4f69-b1ca-c92a14379558`');

                $this->containerBuilder->get(Router::class);

                $context = new TestContext();

                $id = new TestSagaId('ccfd1b8e-be8b-4f69-b1ca-c92a14379558', TestSaga::class);

                yield $this->sagaProvider->start($id, new TestCommand(), $context);
                yield $this->sagaProvider->reopen($id, $context, datetimeInstantiator('+1 day'), 'testing');

                Loop::stop();
            }
        );
    }

    /**
     * @test
     */
    public function reopenUnknownSaga(): void
    {
        Loop::run(
            function (): \Generator
            {
                $this->expectException(ReopenFailed::class);
                $this->expectExceptionMessage('Saga `ccfd1b8e-be8b-4f69-b1ca-c92a14379558` doesn\'t exists');

                $this->containerBuilder->get(Router::class);

                $context = new TestContext();

                $id = new TestSagaId('ccfd1b8e-be8b-4f69-b1ca-c92a14379558', TestSaga::class);

                yield $this->sagaProvider->reopen($id, $context, datetimeInstantiator('+1 day'), 'testing');

                Loop::stop();
            }
        );
    }
}
