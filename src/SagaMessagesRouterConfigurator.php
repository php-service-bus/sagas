<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Sagas;

use ServiceBus\MessagesRouter\Exceptions\MessageRouterConfigurationFailed;
use ServiceBus\MessagesRouter\Router;
use ServiceBus\MessagesRouter\RouterConfigurator;
use ServiceBus\Sagas\Configuration\SagaConfigurationLoader;

/**
 * Register saga event listeners.
 */
final class SagaMessagesRouterConfigurator implements RouterConfigurator
{
    /**
     * @var SagaConfigurationLoader
     */
    private $sagaConfigurationLoader;

    /**
     * List of registered services.
     *
     * @psalm-var array<array-key, string>
     *
     * @var array
     */
    private $sagasList;

    /**
     * @psalm-param array<array-key, class-string<\ServiceBus\Sagas\Saga>> $sagasList
     */
    public function __construct(
        SagaConfigurationLoader $sagaConfigurationLoader,
        array                   $sagasList
    ) {
        $this->sagaConfigurationLoader = $sagaConfigurationLoader;
        $this->sagasList               = $sagasList;
    }

    public function configure(Router $router): void
    {
        try
        {
            /**
             * @psalm-var class-string<\ServiceBus\Sagas\Saga> $sagaClass
             */
            foreach ($this->sagasList as $sagaClass)
            {
                $sagaConfiguration = $this->sagaConfigurationLoader->load($sagaClass);

                /** @todo: more beautiful solution */
                SagaMetadataStore::instance()->add($sagaConfiguration->metadata);

                /** @var \ServiceBus\Common\MessageHandler\MessageHandler $handler */
                foreach ($sagaConfiguration->listenerCollection as $handler)
                {
                    $router->registerListener($handler->messageClass, new SagaMessageExecutor($handler));
                }

                $router->registerHandler(
                    $sagaConfiguration->initialCommandHandler->messageClass,
                    new SagaMessageExecutor($sagaConfiguration->initialCommandHandler)
                );
            }
        }
        catch (\Throwable $throwable)
        {
            throw new MessageRouterConfigurationFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
    }
}
