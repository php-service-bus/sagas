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

use ServiceBus\Sagas\SagaMetadata;

/**
 * Configuration details
 *
 * @property-read SagaMetadata                                                      $metaData
 * @property-read \SplObjectStorage<\ServiceBus\Sagas\Configuration\EventProcessor> $processorCollection
 */
final class SagaConfiguration
{
    /**
     * @var SagaMetadata
     */
    public $metaData;

    /**
     * @var \SplObjectStorage<\ServiceBus\Sagas\Configuration\EventProcessor>
     */
    public $processorCollection;

    /**
     * @param SagaMetadata                                                      $metaData
     * @param \SplObjectStorage<\ServiceBus\Sagas\Configuration\EventProcessor> $processorCollection
     *
     * @return self
     */
    public static function create(SagaMetadata $metaData, \SplObjectStorage $processorCollection): self
    {
        return new self($metaData, $processorCollection);
    }

    /**
     * @param SagaMetadata                                                       $metaData
     * @param  \SplObjectStorage<\ServiceBus\Sagas\Configuration\EventProcessor> $processorCollection
     */
    private function __construct(SagaMetadata $metaData, \SplObjectStorage $processorCollection)
    {
        $this->metaData            = $metaData;
        $this->processorCollection = $processorCollection;
    }
}
