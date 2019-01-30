<?php

/**
 * PHP Service Bus Saga (Process Manager) implementation
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Sagas\Configuration;

/**
 * Configuration details
 *
 * @property-read SagaMetadata                                                        $metaData
 * @property-read \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler> $handlerCollection
 */
final class SagaConfiguration
{
    /**
     * @var SagaMetadata
     */
    public $metaData;

    /**
     * @var \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler>
     */
    public $handlerCollection;

    /**
     * @param SagaMetadata                                                        $metaData
     * @param \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler> $handlerCollection
     *
     * @return self
     */
    public static function create(SagaMetadata $metaData, \SplObjectStorage $handlerCollection): self
    {
        return new self($metaData, $handlerCollection);
    }

    /**
     * @param SagaMetadata                                                        $metaData
     * @param \SplObjectStorage<\ServiceBus\Common\MessageHandler\MessageHandler> $handlerCollection
     */
    private function __construct(SagaMetadata $metaData, \SplObjectStorage $handlerCollection)
    {
        $this->metaData          = $metaData;
        $this->handlerCollection = $handlerCollection;
    }
}
