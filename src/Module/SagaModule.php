<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Module;

use ServiceBus\AnnotationsReader\Reader;
use ServiceBus\Mutex\InMemory\InMemoryMutexFactory;
use ServiceBus\Sagas\SagaMessagesRouterConfigurator;
use ServiceBus\Sagas\SagasProvider;
use function ServiceBus\Common\canonicalizeFilesPath;
use function ServiceBus\Common\extractNamespaceFromFile;
use function ServiceBus\Common\searchFiles;
use ServiceBus\Common\Module\ServiceBusModule;
use ServiceBus\MessagesRouter\ChainRouterConfigurator;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\Mutex\MutexFactory;
use ServiceBus\Sagas\Configuration\Annotations\SagaAnnotationBasedConfigurationLoader;
use ServiceBus\Sagas\Configuration\DefaultEventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\EventListenerProcessorFactory;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\Store\SagasStore;
use ServiceBus\Sagas\Store\Sql\SQLSagaStore;
use ServiceBus\Storage\Common\DatabaseAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 */
final class SagaModule implements ServiceBusModule
{
    /** @var string */
    private $sagaStoreServiceId;

    /** @var string */
    private $databaseAdapterServiceId;

    /**
     * @psalm-var array<array-key, class-string<\ServiceBus\Sagas\Saga>>
     *
     * @var array
     */
    private $sagasToRegister = [];

    /** @var string|null */
    private $configurationLoaderServiceId;

    /**
     * @param string|null $configurationLoaderServiceId If not specified, the default annotation-based configurator
     *                                                  will be used
     *
     * @throws \LogicException The component "php-service-bus/storage-sql" was not installed
     * @throws \LogicException The component "php-service-bus/annotations-reader" was not installed
     */
    public static function withSqlStorage(
        string $databaseAdapterServiceId,
        ?string $configurationLoaderServiceId = null
    ): self {
        if (\interface_exists(DatabaseAdapter::class) === false)
        {
            throw new \LogicException('The component "php-service-bus/storage" was not installed');
        }

        if ($configurationLoaderServiceId === null &&  \interface_exists(Reader::class) === false)
        {
            throw new \LogicException('The component "php-service-bus/annotations-reader" was not installed');
        }

        return new self(
            SQLSagaStore::class,
            $databaseAdapterServiceId,
            $configurationLoaderServiceId
        );
    }

    /**
     * @param string|null $configurationLoaderServiceId If not specified, the default annotation-based configurator
     *                                                  will be used
     *
     * @throws \LogicException The component "php-service-bus/annotations-reader" was not installed
     */
    public static function withCustomStore(
        string $storeImplementationServiceId,
        string $databaseAdapterServiceId,
        ?string $configurationLoaderServiceId = null
    ): self {
        if ($configurationLoaderServiceId === null &&  \interface_exists(Reader::class) === false)
        {
            throw new \LogicException('The component "php-service-bus/annotations-reader" was not installed');
        }

        return new self(
            $storeImplementationServiceId,
            $databaseAdapterServiceId,
            $configurationLoaderServiceId
        );
    }

    /**
     * All sagas from the specified directories will be registered automatically.
     *
     * @noinspection PhpDocMissingThrowsInspection
     *
     * Note: All files containing user-defined functions must be excluded
     * Note: Increases start time because of the need to scan files
     *
     * @psalm-param  array<array-key, string> $directories
     * @psalm-param  array<array-key, string> $excludedFiles
     */
    public function enableAutoImportSagas(array $directories, array $excludedFiles = []): self
    {
        $excludedFiles = canonicalizeFilesPath($excludedFiles);
        $files         = searchFiles($directories, '/\.php/i');

        /** @var \SplFileInfo $file */
        foreach ($files as $file)
        {
            $filePath = $file->getRealPath();

            if ($filePath === false || \in_array($filePath, $excludedFiles, true))
            {
                continue;
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            $class = extractNamespaceFromFile($filePath);

            if ($class !== null && \is_a($class, Saga::class, true))
            {
                /** @psalm-var class-string<\ServiceBus\Sagas\Saga> $class */

                $this->configureSaga($class);
            }
        }

        return $this;
    }

    /**
     * Enable sagas.
     *
     * @psalm-param array<array-key, class-string<\ServiceBus\Sagas\Saga>> $sagas
     */
    public function configureSagas(array $sagas): self
    {
        foreach ($sagas as $saga)
        {
            $this->configureSaga($saga);
        }

        return $this;
    }

    /**
     * Enable specified saga.
     *
     * @psalm-param class-string<\ServiceBus\Sagas\Saga> $sagaClass
     */
    public function configureSaga(string $sagaClass): self
    {
        $this->sagasToRegister[\sha1($sagaClass)] = $sagaClass;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->setParameter('service_bus.sagas.list', $this->sagasToRegister);

        $this->registerSagaStore($containerBuilder);
        $this->registerMutexFactory($containerBuilder);
        $this->registerSagasProvider($containerBuilder);

        if ($this->configurationLoaderServiceId === null)
        {
            $this->registerDefaultConfigurationLoader($containerBuilder);

            $this->configurationLoaderServiceId = SagaConfigurationLoader::class;
        }

        $this->registerRoutesConfigurator($containerBuilder);
    }

    private function registerMutexFactory(ContainerBuilder $containerBuilder): void
    {
        if ($containerBuilder->hasDefinition(MutexFactory::class) === false)
        {
            $containerBuilder->setDefinition(MutexFactory::class, new Definition(InMemoryMutexFactory::class));
        }
    }

    private function registerRoutesConfigurator(ContainerBuilder $containerBuilder): void
    {
        if ($containerBuilder->hasDefinition(ChainRouterConfigurator::class) === false)
        {
            $containerBuilder->setDefinition(ChainRouterConfigurator::class, new Definition(ChainRouterConfigurator::class));
        }

        $routerConfiguratorDefinition = $containerBuilder->getDefinition(ChainRouterConfigurator::class);

        if ($containerBuilder->hasDefinition(Router::class) === false)
        {
            $containerBuilder->setDefinition(Router::class, new Definition(Router::class));
        }

        $routerDefinition = $containerBuilder->getDefinition(Router::class);
        $routerDefinition->setConfigurator(
            [new Reference(ChainRouterConfigurator::class), 'configure']
        );

        $sagaRoutingConfiguratorDefinition = (new Definition(SagaMessagesRouterConfigurator::class))
            ->setArguments([
                new Reference(SagasProvider::class),
                new Reference(SagaConfigurationLoader::class),
                '%service_bus.sagas.list%',
            ]);

        $containerBuilder->setDefinition(SagaMessagesRouterConfigurator::class, $sagaRoutingConfiguratorDefinition);

        $routerConfiguratorDefinition->addMethodCall(
            'addConfigurator',
            [new Reference(SagaMessagesRouterConfigurator::class)]
        );
    }

    private function registerSagasProvider(ContainerBuilder $containerBuilder): void
    {
        $sagasProviderDefinition = (new Definition(SagasProvider::class))
            ->setArguments(
                [
                    new Reference(SagasStore::class),
                    new Reference(MutexFactory::class)
                ]
            );

        $containerBuilder->setDefinition(SagasProvider::class, $sagasProviderDefinition);
    }

    private function registerSagaStore(ContainerBuilder $containerBuilder): void
    {
        if ($containerBuilder->hasDefinition(SagasStore::class) === true)
        {
            return;
        }

        $sagaStoreDefinition = (new Definition($this->sagaStoreServiceId))
            ->setArguments([new Reference($this->databaseAdapterServiceId)]);

        $containerBuilder->setDefinition(SagasStore::class, $sagaStoreDefinition);
    }

    private function registerDefaultConfigurationLoader(ContainerBuilder $containerBuilder): void
    {
        if ($containerBuilder->hasDefinition(SagaConfigurationLoader::class) === true)
        {
            return;
        }

        if ($containerBuilder->hasDefinition(EventListenerProcessorFactory::class) === true)
        {
            return;
        }

        /** Event listener factory */
        $listenerFactoryDefinition = (new Definition(DefaultEventListenerProcessorFactory::class))
            ->setArguments([new Reference(SagasStore::class)]);

        $containerBuilder->setDefinition(EventListenerProcessorFactory::class, $listenerFactoryDefinition);

        /** Configuration loader */
        $configurationLoaderDefinition = (new Definition(SagaAnnotationBasedConfigurationLoader::class))
            ->setArguments([new Reference(EventListenerProcessorFactory::class)]);

        $containerBuilder->setDefinition(SagaConfigurationLoader::class, $configurationLoaderDefinition);

        $this->configurationLoaderServiceId = SagaConfigurationLoader::class;
    }

    private function __construct(
        string $sagaStoreServiceId,
        string $databaseAdapterServiceId,
        ?string $configurationLoaderServiceId = null
    ) {
        $this->sagaStoreServiceId           = $sagaStoreServiceId;
        $this->databaseAdapterServiceId     = $databaseAdapterServiceId;
        $this->configurationLoaderServiceId = $configurationLoaderServiceId;
    }
}
