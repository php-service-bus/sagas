<?php

/**
 * Saga pattern implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration;

/**
 * Configuration details.
 *
 * @psalm-readonly
 */
final class SagaConfiguration
{
    public SagaMetadata $metaData;

    /**
     * @psalm-var \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler, string>
     */
    public \SplObjectStorage $handlerCollection;

    /**
     * @psalm-param \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler, string> $handlerCollection
     */
    public function __construct(SagaMetadata $metaData, \SplObjectStorage $handlerCollection)
    {
        $this->metaData          = $metaData;
        $this->handlerCollection = $handlerCollection;
    }
}
