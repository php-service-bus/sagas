<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation module.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Tests\Module;

use PHPUnit\Framework\TestCase;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\Sagas\Module\SagaModule;
use ServiceBus\Sagas\SagasProvider;
use ServiceBus\Sagas\Tests\stubs\CorrectSaga;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 */
final class SagaModuleTest extends TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $containerBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->containerBuilder = new ContainerBuilder();

        $this->containerBuilder->addDefinitions([
            StorageConfiguration::class => new Definition(StorageConfiguration::class, ['sqlite:///:memory:']),
            DatabaseAdapter::class      => (new Definition(DoctrineDBALAdapter::class))
                ->setArguments([new Reference(StorageConfiguration::class)]),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->containerBuilder);
    }

    /**
     * @test
     */
    public function withSqlStorage(): void
    {
        $module = SagaModule::withSqlStorage(DatabaseAdapter::class)
            ->configureSaga(CorrectSaga::class);

        $module->boot($this->containerBuilder);

        $this->containerBuilder->getDefinition(SagasProvider::class)->setPublic(true);
        $this->containerBuilder->getDefinition(Router::class)->setPublic(true);

        $this->containerBuilder->compile();

        /** @var SagasProvider $sagasProvider */
        $sagasProvider = $this->containerBuilder->get(SagasProvider::class);

        self::assertInstanceOf(SagasProvider::class, $sagasProvider);

        /** @var Router $router */
        $router = $this->containerBuilder->get(Router::class);

        self::assertCount(3, $router);
    }

    /**
     * @test
     */
    public function withCustomStore(): void
    {
        $configDefinition = (new Definition(StorageConfiguration::class))
            ->setArguments([\getenv('TEST_POSTGRES_DSN')]);

        $this->containerBuilder->setDefinition('database_adapter_config', $configDefinition);

        $adapterDefinition = (new Definition(AmpPostgreSQLAdapter::class))
            ->setArguments([new Reference('database_adapter_config')]);

        $this->containerBuilder->setDefinition(AmpPostgreSQLAdapter::class, $adapterDefinition);

        $sagaStoreDefinition = new Definition(CustomSagaStore::class);

        $this->containerBuilder->setDefinition(CustomSagaStore::class, $sagaStoreDefinition);

        SagaModule::withCustomStore(
            CustomSagaStore::class,
            AmpPostgreSQLAdapter::class
        )->boot($this->containerBuilder);

        $this->containerBuilder->getDefinition(SagasProvider::class)->setPublic(true);
        $this->containerBuilder->compile();

        $sagasProvider = $this->containerBuilder->get(SagasProvider::class);

        self::assertInstanceOf(SagasProvider::class, $sagasProvider);
    }
}
