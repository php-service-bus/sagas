<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas\Module;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ServiceBus\AnnotationsReader\Reader;
use ServiceBus\ArgumentResolver\ChainArgumentResolver;
use ServiceBus\ArgumentResolver\ContainerArgumentResolver;
use ServiceBus\ArgumentResolver\MessageArgumentResolver;
use ServiceBus\Common\Module\ServiceBusModule;
use ServiceBus\MessagesRouter\ChainRouterConfigurator;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\Mutex\InMemory\InMemoryMutexService;
use ServiceBus\Mutex\MutexService;
use ServiceBus\Sagas\Configuration\Attributes\SagaAttributeBasedConfigurationLoader;
use ServiceBus\Sagas\Configuration\MessageProcessor\DefaultSagaMessageProcessorFactory;
use ServiceBus\Sagas\Configuration\MessageProcessor\SagaMessageProcessorFactory;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;
use ServiceBus\Sagas\Configuration\SagaIdLocator;
use ServiceBus\Sagas\Saga;
use ServiceBus\Sagas\SagaFinder;
use ServiceBus\Sagas\SagaLifecycleManager;
use ServiceBus\Sagas\SagaMessagesRouterConfigurator;
use ServiceBus\Sagas\Store\SagasStore;
use ServiceBus\Sagas\Store\Sql\SQLSagaStore;
use ServiceBus\Storage\Common\DatabaseAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use function ServiceBus\Common\canonicalizeFilesPath;
use function ServiceBus\Common\extractNamespaceFromFile;
use function ServiceBus\Common\searchFiles;

/**
 *
 */
final class SagaModule implements ServiceBusModule
{
    /**
     * @var string
     */
    private $sagaStoreServiceId;

    /**
     * @var string
     */
    private $databaseAdapterServiceId;

    /**
     * @psalm-var array<array-key, class-string<\ServiceBus\Sagas\Saga>>
     *
     * @var array
     */
    private $sagasToRegister = [];

    /**
     * @var string|null
     */
    private $configurationLoaderServiceId;

    /**
     * @throws \LogicException The component "php-service-bus/storage-sql" was not installed
     * @throws \LogicException The component "php-service-bus/annotations-reader" was not installed
     */
    public static function withSqlStorage(
        string  $databaseAdapterServiceId,
        ?string $configurationLoaderServiceId = null
    ): self {
        if (\interface_exists(DatabaseAdapter::class) === false)
        {
            throw new \LogicException('The component "php-service-bus/storage" was not installed');
        }

        if ($configurationLoaderServiceId === null && \interface_exists(Reader::class) === false)
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
     * @throws \LogicException The component "php-service-bus/annotations-reader" was not installed
     */
    public static function withCustomStore(
        string  $storeImplementationServiceId,
        string  $databaseAdapterServiceId,
        ?string $configurationLoaderServiceId = null
    ): self {
        if ($configurationLoaderServiceId === null && \interface_exists(Reader::class) === false)
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
     * @psalm-param list<non-empty-string> $directories
     * @psalm-param list<non-empty-string> $excludedFiles
     */
    public function enableAutoImportSagas(array $directories, array $excludedFiles = []): self
    {
        $excludedFiles = canonicalizeFilesPath($excludedFiles);

        $files = searchFiles($directories, '/\.php/i');

        /** @var \SplFileInfo $file */
        foreach ($files as $file)
        {
            /** @psalm-var non-empty-string|bool $filePath */
            $filePath = $file->getRealPath();

            if (\is_string($filePath) === false || \in_array($filePath, $excludedFiles, true))
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

    public function boot(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->setParameter('service_bus.sagas.list', $this->sagasToRegister);

        if ($containerBuilder->hasDefinition(LoggerInterface::class) === false)
        {
            $containerBuilder->addDefinitions([
                LoggerInterface::class => new Definition(NullLogger::class)
            ]);
        }

        $this->registerDefaultArgumentResolver($containerBuilder);
        $this->registerSagaStore($containerBuilder);
        $this->registerMutexFactory($containerBuilder);
        $this->registerSagaFinder($containerBuilder);
        $this->registerSagasLifecycleManager($containerBuilder);

        if ($this->configurationLoaderServiceId === null)
        {
            $this->registerDefaultConfigurationLoader($containerBuilder);

            $this->configurationLoaderServiceId = SagaConfigurationLoader::class;
        }

        $this->registerRoutesConfigurator($containerBuilder);
        $this->collectSagasDependencies($containerBuilder);
    }

    private function registerSagasLifecycleManager(ContainerBuilder $containerBuilder): void
    {
        $sagasFinderDefinition = (new Definition(SagaLifecycleManager::class))
            ->setArguments(
                [
                    new Reference(SagasStore::class),
                    new Reference(MutexService::class)
                ]
            );

        $containerBuilder->setDefinition(SagaLifecycleManager::class, $sagasFinderDefinition);
    }

    private function registerMutexFactory(ContainerBuilder $containerBuilder): void
    {
        if ($containerBuilder->hasDefinition(MutexService::class) === false)
        {
            $containerBuilder->setDefinition(MutexService::class, new Definition(InMemoryMutexService::class));
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
                new Reference(SagaConfigurationLoader::class),
                '%service_bus.sagas.list%',
            ]);

        $containerBuilder->setDefinition(SagaMessagesRouterConfigurator::class, $sagaRoutingConfiguratorDefinition);

        $routerConfiguratorDefinition->addMethodCall(
            'addConfigurator',
            [new Reference(SagaMessagesRouterConfigurator::class)]
        );
    }

    private function registerSagaFinder(ContainerBuilder $containerBuilder): void
    {
        $sagasFinderDefinition = (new Definition(SagaFinder::class))
            ->setArguments(
                [
                    new Reference(SagasStore::class),
                    new Reference(MutexService::class)
                ]
            );

        $containerBuilder->setDefinition(SagaFinder::class, $sagasFinderDefinition);
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

    private function registerDefaultArgumentResolver(ContainerBuilder $containerBuilder): void
    {
        if ($containerBuilder->hasDefinition('service_bus.services_locator') === false)
        {
            $definition = (new Definition(ServiceLocator::class, [[]]))->setPublic(true);

            $containerBuilder->addDefinitions(['service_bus.services_locator' => $definition]);
        }

        if ($containerBuilder->hasDefinition(ChainArgumentResolver::class) === false)
        {
            $containerBuilder->addDefinitions(
                [
                    /** Passing message to arguments */
                    MessageArgumentResolver::class   => new Definition(MessageArgumentResolver::class),
                    /** Autowiring of registered services in arguments */
                    ContainerArgumentResolver::class => (new Definition(
                        class: ContainerArgumentResolver::class,
                        arguments: ['$serviceLocator' => new Reference('service_bus.services_locator')]
                    ))->addTag('service_bus_argument_resolver', [])
                ]
            );

            $definition = new Definition(
                ChainArgumentResolver::class,
                [
                    '$resolvers' => [
                        new Reference(MessageArgumentResolver::class),
                        new Reference(ContainerArgumentResolver::class)
                    ]
                ]
            );

            $containerBuilder->addDefinitions(['service_bus.saga.argument_resolver' => $definition]);
        }
    }

    private function registerDefaultConfigurationLoader(ContainerBuilder $containerBuilder): void
    {
        if ($containerBuilder->hasDefinition(SagaConfigurationLoader::class) === true)
        {
            return;
        }

        if ($containerBuilder->hasDefinition(SagaMessageProcessorFactory::class) === true)
        {
            return;
        }

        $idAllocatorDefinition = (new Definition(SagaIdLocator::class))
            ->setArguments([new Reference(SagasStore::class)]);

        $containerBuilder->setDefinition(SagaIdLocator::class, $idAllocatorDefinition);

        /** Event listener factory */
        $listenerFactoryDefinition = (new Definition(DefaultSagaMessageProcessorFactory::class))
            ->setArguments(
                [
                    new Reference(SagasStore::class),
                    new Reference('service_bus.saga.argument_resolver'),
                    new Reference(SagaIdLocator::class),
                    new Reference(MutexService::class)
                ]
            );

        $containerBuilder->setDefinition(SagaMessageProcessorFactory::class, $listenerFactoryDefinition);

        /** Configuration loader */
        $configurationLoaderDefinition = (new Definition(SagaAttributeBasedConfigurationLoader::class))
            ->setArguments([new Reference(SagaMessageProcessorFactory::class)]);

        $containerBuilder->setDefinition(SagaConfigurationLoader::class, $configurationLoaderDefinition);

        $this->configurationLoaderServiceId = SagaConfigurationLoader::class;
    }

    private function collectSagasDependencies(ContainerBuilder $containerBuilder): void
    {
        $externalDependencies = [];

        foreach ($this->sagasToRegister as $sagaClass)
        {
            $reflectionClass = new \ReflectionClass($sagaClass);

            foreach ($reflectionClass->getMethods() as $reflectionMethod)
            {
                foreach ($reflectionMethod->getParameters() as $reflectionParameter)
                {
                    $reflectionType = $reflectionParameter->getType();

                    if (($reflectionType instanceof \ReflectionNamedType) === false)
                    {
                        continue;
                    }

                    $className = $reflectionType->getName();

                    if ($containerBuilder->hasDefinition($className))
                    {
                        $containerBuilder->getDefinition($className)->setPublic(true);

                        $externalDependencies[] = $className;
                    }
                }
            }
        }

        $containerBuilder->setParameter('saga_dependencies', $externalDependencies);
    }

    private function __construct(
        string  $sagaStoreServiceId,
        string  $databaseAdapterServiceId,
        ?string $configurationLoaderServiceId = null
    ) {
        $this->sagaStoreServiceId           = $sagaStoreServiceId;
        $this->databaseAdapterServiceId     = $databaseAdapterServiceId;
        $this->configurationLoaderServiceId = $configurationLoaderServiceId;
    }
}
